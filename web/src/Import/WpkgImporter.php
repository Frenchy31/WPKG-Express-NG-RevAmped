<?php

declare(strict_types=1);

namespace App\Import;

use App\Model\Entity\Package;
use App\Model\Enum\ActionType;
use App\Model\Enum\CheckCondition;
use App\Model\Enum\CheckType;
use App\Model\Enum\ExitCodeReboot;
use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;
use App\Model\Enum\VariableType;
use Cake\ORM\Locator\LocatorAwareTrait;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Imports hosts.xml, profiles.xml and packages.xml documents.
 *
 * Records depending on a missing or invalid record are skipped and reported. A package already
 * present is replaced only by a higher revision, and keeps its associations.
 */
class WpkgImporter
{
    use LocatorAwareTrait;

    /**
     * Schema namespaces, by document kind. Files without a namespace are accepted too.
     */
    private const array NAMESPACES = [
        'packages' => 'http://www.wpkg.org/packages',
        'profiles' => 'http://www.wpkg.org/profiles',
        'hosts' => 'http://www.wpkg.org/hosts',
    ];

    /**
     * Client features the database cannot store.
     */
    private const array UNSUPPORTED_ELEMENTS = ['download', 'condition', 'chain', 'include'];

    /**
     * Imports a document.
     *
     * @param string $kind One of packages, profiles, hosts.
     * @param string $xml Document.
     * @return \App\Import\ImportReport
     * @throws \InvalidArgumentException When the kind is unknown.
     */
    public function import(string $kind, string $xml): ImportReport
    {
        if (!isset(self::NAMESPACES[$kind])) {
            throw new InvalidArgumentException(sprintf('Unknown document kind `%s`.', $kind));
        }

        $report = new ImportReport();
        $document = $this->parseAndValidate($kind, $xml, $report);
        if ($document === null) {
            return $report;
        }

        match ($kind) {
            'packages' => $this->importPackages($document, $report),
            'profiles' => $this->importProfiles($document, $report),
            'hosts' => $this->importHosts($document, $report),
        };

        return $report;
    }

    /**
     * Parses a document and validates it against the schemas shipped with wpkg.js.
     *
     * @param string $kind Document kind.
     * @param string $xml Document.
     * @param \App\Import\ImportReport $report Report.
     * @return \DOMDocument|null Null when the document is rejected.
     */
    private function parseAndValidate(string $kind, string $xml, ImportReport $report): ?DOMDocument
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument();
        $isValid = $document->loadXML($xml, LIBXML_NONET)
            && $this->withNamespacedRoot($document, $kind)->schemaValidate(
                RESOURCES . 'xsd' . DS . $kind . '.xsd',
            );

        if (!$isValid) {
            $report->rejected = true;
            foreach (libxml_get_errors() as $error) {
                $report->errors[] = __('{0} (line {1})', trim($error->message), (string)$error->line);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        return $isValid ? $document : null;
    }

    /**
     * Returns a copy of a document whose root element carries the schema namespace.
     *
     * @param \DOMDocument $document Document.
     * @param string $kind Document kind.
     * @return \DOMDocument
     */
    private function withNamespacedRoot(DOMDocument $document, string $kind): DOMDocument
    {
        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== null) {
            return $document;
        }

        $copy = new DOMDocument();
        $newRoot = $copy->createElementNS(self::NAMESPACES[$kind], 'wpkg:' . $root->localName);
        $copy->appendChild($newRoot);
        foreach ($root->childNodes as $child) {
            $newRoot->appendChild($copy->importNode($child, true));
        }

        // Children must parse as unqualified elements, as the schemas declare.
        $reloaded = new DOMDocument();
        $reloaded->loadXML((string)$copy->saveXML(), LIBXML_NONET);

        return $reloaded;
    }

    /**
     * Imports the packages of a document, then their dependencies.
     *
     * @param \DOMDocument $document Document.
     * @param \App\Import\ImportReport $report Report.
     * @return void
     */
    private function importPackages(DOMDocument $document, ImportReport $report): void
    {
        $packages = $this->fetchTable('Packages');
        $elements = $this->children($document->documentElement, 'package');
        $report->total = count($elements);

        $identifiersInFile = array_map(fn(DOMElement $element): string => $element->getAttribute('id'), $elements);
        $importedIds = [];
        foreach ($elements as $element) {
            $identifier = $element->getAttribute('id');
            $existing = $packages->find()->where(['id_text' => $identifier])->first();
            if ($existing !== null && !$this->isHigherRevision($element, $existing, $report)) {
                continue;
            }

            $missing = $this->missingDependencies($element, $identifiersInFile);
            if ($missing) {
                $report->errors[] = __(
                    '{0}: could not find existing dependency package: {1}',
                    $identifier,
                    implode(', ', $missing),
                );
                continue;
            }
            $this->warnAboutUnsupportedElements($element, $report);

            // A false result rolls back, restoring a replaced package when its successor is invalid.
            $savedId = null;
            $packages->getConnection()->transactional(function () use ($element, $existing, $report, &$savedId): bool {
                $savedId = $this->savePackage($element, $existing, $report);

                return $savedId !== null;
            });
            if ($savedId !== null) {
                $importedIds[$identifier] = $savedId;
                $report->saved++;
            }
        }

        foreach ($elements as $element) {
            if (isset($importedIds[$element->getAttribute('id')])) {
                $this->linkPackageDependencies($element, $importedIds[$element->getAttribute('id')], $report);
            }
        }
    }

    /**
     * Tells whether a package replaces the stored revision, and reports the decision.
     *
     * @param \DOMElement $element Package element.
     * @param \App\Model\Entity\Package $existing Stored package.
     * @param \App\Import\ImportReport $report Report.
     * @return bool
     */
    private function isHigherRevision(DOMElement $element, Package $existing, ImportReport $report): bool
    {
        $identifier = $element->getAttribute('id');
        $revision = $element->getAttribute('revision');
        if ($this->compareRevisions($revision, (string)$existing->revision) > 0) {
            $report->notices[] = __(
                '{0}: upgraded from revision {1} to {2}.',
                $identifier,
                $existing->revision,
                $revision,
            );

            return true;
        }

        $report->warnings[] = __(
            '{0}: a package with the same ID and an equal or higher revision ({1}) already exists. Skipped.',
            $identifier,
            $existing->revision,
        );

        return false;
    }

    /**
     * Compares two dotted revisions numerically.
     *
     * @param string $left Revision.
     * @param string $right Revision.
     * @return int Negative, zero or positive.
     */
    private function compareRevisions(string $left, string $right): int
    {
        $leftParts = explode('.', $left);
        $rightParts = explode('.', $right);
        for ($index = 0; $index < max(count($leftParts), count($rightParts)); $index++) {
            $difference = (int)($leftParts[$index] ?? 0) <=> (int)($rightParts[$index] ?? 0);
            if ($difference !== 0) {
                return $difference;
            }
        }

        return 0;
    }

    /**
     * Lists the dependencies found neither in the file nor in the database.
     *
     * @param \DOMElement $element Package element.
     * @param array<string> $identifiersInFile Identifiers of the file.
     * @return array<string>
     */
    private function missingDependencies(DOMElement $element, array $identifiersInFile): array
    {
        $missing = [];
        foreach ($this->children($element, 'depends') as $dependency) {
            $identifier = $dependency->getAttribute('package-id');
            if (
                !in_array($identifier, $identifiersInFile, true)
                && !$this->fetchTable('Packages')->exists(['id_text' => $identifier])
            ) {
                $missing[] = $identifier;
            }
        }

        return $missing;
    }

    /**
     * Reports what the database cannot store, because a silent loss would change client behaviour.
     *
     * @param \DOMElement $element Package element.
     * @param \App\Import\ImportReport $report Report.
     * @return void
     */
    private function warnAboutUnsupportedElements(DOMElement $element, ImportReport $report): void
    {
        foreach (self::UNSUPPORTED_ELEMENTS as $elementName) {
            if ($element->getElementsByTagName($elementName)->length > 0) {
                $report->warnings[] = __(
                    '{0}: <{1}> elements are not stored and were dropped.',
                    $element->getAttribute('id'),
                    $elementName,
                );
            }
        }
    }

    /**
     * Saves a package with its checks, actions and variables, replacing a previous revision.
     *
     * @param \DOMElement $element Package element.
     * @param \App\Model\Entity\Package|null $existing Package being replaced.
     * @param \App\Import\ImportReport $report Report.
     * @return int|null Package id, null when it is invalid.
     */
    private function savePackage(DOMElement $element, ?Package $existing, ImportReport $report): ?int
    {
        $packages = $this->fetchTable('Packages');
        $identifier = $element->getAttribute('id');

        if ($existing !== null) {
            $packages->deleteKeepingAssociations($existing);
        }

        $package = $packages->newEntity($this->packageFields($element));
        if ($package->hasErrors() || !$packages->save($package)) {
            $report->errors[] = __('{0}: {1}', $identifier, $this->firstError($package->getErrors()));

            return null;
        }

        if ($existing !== null) {
            $packages->transferAssociations($existing->id, $package->id);
        }

        foreach ($this->children($element, 'check') as $check) {
            if (!$this->saveCheck($check, $package->id, null, $identifier, $report)) {
                return null;
            }
        }
        foreach (ActionType::cases() as $actionType) {
            foreach ($this->children($element, $actionType->xmlElement()) as $action) {
                if (!$this->saveAction($action, $actionType, $package->id, $identifier, $report)) {
                    return null;
                }
            }
        }
        $this->saveVariables($element, VariableType::Package, $package->id);

        return $package->id;
    }

    /**
     * Maps the attributes of a package element to table fields.
     *
     * @param \DOMElement $element Package element.
     * @return array<string, mixed>
     */
    private function packageFields(DOMElement $element): array
    {
        $execute = strtolower($element->getAttribute('execute'));
        $reboot = strtolower($element->getAttribute('reboot'));
        $notify = strtolower($element->getAttribute('notify'));

        return [
            'id_text' => $element->getAttribute('id'),
            'name' => $element->getAttribute('name'),
            'revision' => $element->getAttribute('revision'),
            'priority' => $element->getAttribute('priority'),
            'reboot' => match ($reboot) {
                'true' => PackageReboot::True->value,
                'postponed' => PackageReboot::Postponed->value,
                default => PackageReboot::False->value,
            },
            'execute' => match ($execute) {
                'always' => PackageExecute::Always->value,
                'once' => PackageExecute::Once->value,
                default => PackageExecute::Normal->value,
            },
            'notify' => $notify === 'true' || $notify === '1',
        ];
    }

    /**
     * Saves a check and its children.
     *
     * @param \DOMElement $element Check element.
     * @param int $packageId Package id.
     * @param int|null $parentId Parent check id.
     * @param string $identifier Package identifier, for messages.
     * @param \App\Import\ImportReport $report Report.
     * @return bool
     */
    private function saveCheck(
        DOMElement $element,
        int $packageId,
        ?int $parentId,
        string $identifier,
        ImportReport $report,
    ): bool {
        $checks = $this->fetchTable('PackageChecks');
        $type = CheckType::fromXml($element->getAttribute('type'));
        $condition = $type === null ? null : CheckCondition::fromXml($type, $element->getAttribute('condition'));
        if ($type === null || $condition === null) {
            $report->errors[] = __(
                '{0}: unsupported check type="{1}" condition="{2}".',
                $identifier,
                $element->getAttribute('type'),
                $element->getAttribute('condition'),
            );

            return false;
        }

        $check = $checks->newEntity([
            'type' => $type->value,
            'condition' => $condition->value,
            'path' => $element->hasAttribute('path') ? $element->getAttribute('path') : null,
            'value' => $element->hasAttribute('value') ? $element->getAttribute('value') : null,
            'parent_id' => $parentId,
        ]);
        $check->package_id = $packageId;
        if ($check->hasErrors() || !$checks->saveInPackage($check)) {
            $report->errors[] = __('{0}: {1}', $identifier, $this->firstError($check->getErrors()));

            return false;
        }

        foreach ($this->children($element, 'check') as $child) {
            if (!$this->saveCheck($child, $packageId, $check->id, $identifier, $report)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Saves an action and its exit codes.
     *
     * @param \DOMElement $element Action element.
     * @param \App\Model\Enum\ActionType $actionType Action type.
     * @param int $packageId Package id.
     * @param string $identifier Package identifier, for messages.
     * @param \App\Import\ImportReport $report Report.
     * @return bool
     */
    private function saveAction(
        DOMElement $element,
        ActionType $actionType,
        int $packageId,
        string $identifier,
        ImportReport $report,
    ): bool {
        $actions = $this->fetchTable('PackageActions');
        if (!$element->hasAttribute('cmd')) {
            $report->warnings[] = __(
                '{0}: <{1}> without a cmd attribute was dropped.',
                $identifier,
                $actionType->xmlElement(),
            );

            return true;
        }

        $action = $actions->newEntity([
            'type' => $actionType->value,
            'command' => $element->getAttribute('cmd'),
            'timeout' => $element->hasAttribute('timeout') ? $element->getAttribute('timeout') : null,
            'workdir' => $element->hasAttribute('workdir') ? $element->getAttribute('workdir') : null,
        ]);
        $action->package_id = $packageId;
        if ($action->hasErrors() || !$actions->save($action)) {
            $report->errors[] = __('{0}: {1}', $identifier, $this->firstError($action->getErrors()));

            return false;
        }

        $exitCodes = $this->fetchTable('ExitCodes');
        foreach ($this->children($element, 'exit') as $exit) {
            $exitCode = $exitCodes->newEntity([
                'code' => $exit->getAttribute('code'),
                'reboot' => ExitCodeReboot::fromXml($exit->getAttribute('reboot'))->value,
            ]);
            $exitCode->package_action_id = $action->id;
            if ($exitCode->hasErrors() || !$exitCodes->save($exitCode)) {
                $report->errors[] = __('{0}: {1}', $identifier, $this->firstError($exitCode->getErrors()));

                return false;
            }
        }

        return true;
    }

    /**
     * Stores the dependencies of an imported package.
     *
     * @param \DOMElement $element Package element.
     * @param int $packageId Package id.
     * @param \App\Import\ImportReport $report Report.
     * @return void
     */
    private function linkPackageDependencies(DOMElement $element, int $packageId, ImportReport $report): void
    {
        $packages = $this->fetchTable('Packages');
        $identifier = $element->getAttribute('id');
        $package = $packages->get($packageId);

        foreach ($this->children($element, 'depends') as $dependencyElement) {
            $dependencyIdentifier = $dependencyElement->getAttribute('package-id');
            if ($dependencyIdentifier === $identifier) {
                $report->warnings[] = __('{0}: detected package dependency on self. Skipping.', $identifier);
                continue;
            }

            $dependency = $packages->find()->where(['id_text' => $dependencyIdentifier])->first();
            if ($dependency === null) {
                $report->errors[] = __(
                    '{0}: dependency {1} was not imported.',
                    $identifier,
                    $dependencyIdentifier,
                );
                continue;
            }
            $packages->PackageDependencies->link($package, [$dependency]);
        }
    }

    /**
     * Imports the profiles of a document, then their dependencies.
     *
     * @param \DOMDocument $document Document.
     * @param \App\Import\ImportReport $report Report.
     * @return void
     */
    private function importProfiles(DOMDocument $document, ImportReport $report): void
    {
        $profiles = $this->fetchTable('Profiles');
        $elements = $this->children($document->documentElement, 'profile');
        $report->total = count($elements);

        $importedIds = [];
        foreach ($elements as $element) {
            $identifier = $element->getAttribute('id');
            $missing = [];
            $packages = [];
            foreach ($this->children($element, 'package') as $packageElement) {
                $packageIdentifier = $packageElement->getAttribute('package-id');
                $package = $this->fetchTable('Packages')->find()->where(['id_text' => $packageIdentifier])->first();
                if ($package === null) {
                    $missing[] = $packageIdentifier;
                } else {
                    $packages[] = $package;
                }
            }
            if ($missing) {
                $report->errors[] = __(
                    '{0}: could not find existing profile package: {1}',
                    $identifier,
                    implode(', ', $missing),
                );
                continue;
            }

            $profile = $profiles->newEntity(['id_text' => $identifier], ['associated' => []]);
            if ($profile->hasErrors() || !$profiles->save($profile)) {
                $report->errors[] = __('{0}: {1}', $identifier, $this->firstError($profile->getErrors()));
                continue;
            }
            if ($packages) {
                $profiles->Packages->link($profile, $packages);
            }
            $this->saveVariables($element, VariableType::Profile, $profile->id);

            $importedIds[$identifier] = $profile->id;
            $report->saved++;
        }

        foreach ($elements as $element) {
            if (isset($importedIds[$element->getAttribute('id')])) {
                $this->linkProfileDependencies($element, $importedIds[$element->getAttribute('id')], $report);
            }
        }
    }

    /**
     * Stores the dependencies of an imported profile.
     *
     * @param \DOMElement $element Profile element.
     * @param int $profileId Profile id.
     * @param \App\Import\ImportReport $report Report.
     * @return void
     */
    private function linkProfileDependencies(DOMElement $element, int $profileId, ImportReport $report): void
    {
        $profiles = $this->fetchTable('Profiles');
        $identifier = $element->getAttribute('id');
        $profile = $profiles->get($profileId);

        foreach ($this->children($element, 'depends') as $dependencyElement) {
            $dependencyIdentifier = $dependencyElement->getAttribute('profile-id');
            if ($dependencyIdentifier === $identifier) {
                $report->warnings[] = __('{0}: detected profile dependency on self. Skipping.', $identifier);
                continue;
            }

            $dependency = $profiles->find()->where(['id_text' => $dependencyIdentifier])->first();
            if ($dependency === null) {
                $report->errors[] = __(
                    '{0}: could not find existing dependency profile: {1}',
                    $identifier,
                    $dependencyIdentifier,
                );
                continue;
            }
            $profiles->ProfileDependencies->link($profile, [$dependency]);
        }
    }

    /**
     * Imports the hosts of a document, in document order.
     *
     * @param \DOMDocument $document Document.
     * @param \App\Import\ImportReport $report Report.
     * @return void
     */
    private function importHosts(DOMDocument $document, ImportReport $report): void
    {
        $hosts = $this->fetchTable('Hosts');
        $profiles = $this->fetchTable('Profiles');
        $elements = $this->children($document->documentElement, 'host');
        $report->total = count($elements);

        foreach ($elements as $element) {
            $name = $element->getAttribute('name');
            $mainProfileIdentifier = $element->getAttribute('profile-id');
            $mainProfile = $profiles->find()->where(['id_text' => $mainProfileIdentifier])->first();
            if ($mainProfile === null) {
                $report->errors[] = __(
                    '{0}: could not find existing host\'\'s main profile: {1}',
                    $name,
                    $mainProfileIdentifier,
                );
                continue;
            }

            $missing = [];
            $extraProfiles = [];
            foreach ($this->children($element, 'profile') as $profileElement) {
                $profile = $profiles->find()->where(['id_text' => $profileElement->getAttribute('id')])->first();
                if ($profile === null) {
                    $missing[] = $profileElement->getAttribute('id');
                } else {
                    $extraProfiles[] = $profile;
                }
            }
            if ($missing) {
                $report->errors[] = __(
                    '{0}: could not find existing host profile: {1}',
                    $name,
                    implode(', ', $missing),
                );
                continue;
            }

            $host = $hosts->newEntity(['name' => $name, 'mainprofile_id' => $mainProfile->id], ['associated' => []]);
            if ($host->hasErrors() || !$hosts->save($host)) {
                $report->errors[] = __('{0}: {1}', $name, $this->firstError($host->getErrors()));
                continue;
            }
            if ($extraProfiles) {
                $hosts->Profiles->link($host, $extraProfiles);
            }
            $this->saveVariables($element, VariableType::Host, $host->id);
            $report->saved++;
        }
    }

    /**
     * Saves the <variable> children of an element.
     *
     * @param \DOMElement $element Owner element.
     * @param \App\Model\Enum\VariableType $variableType Owner type.
     * @param int $recordId Owner id.
     * @return void
     */
    private function saveVariables(DOMElement $element, VariableType $variableType, int $recordId): void
    {
        $variables = $this->fetchTable('Variables');
        foreach ($this->children($element, 'variable') as $variableElement) {
            $variable = $variables->newEntity([
                'name' => $variableElement->getAttribute('name'),
                'value' => $variableElement->getAttribute('value'),
            ]);
            $variable->ref_type = $variableType->value;
            $variable->ref_id = $recordId;
            $variables->saveOrFail($variable);
        }
    }

    /**
     * Returns the direct child elements with a given name.
     *
     * @param \DOMElement $parent Parent element.
     * @param string $name Element name.
     * @return array<\DOMElement>
     */
    private function children(DOMElement $parent, string $name): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Extracts the first message of an entity error structure.
     *
     * @param array<mixed> $errors Entity errors.
     * @return string
     */
    private function firstError(array $errors): string
    {
        foreach ($errors as $field => $messages) {
            $message = is_array($messages) ? reset($messages) : $messages;
            if (is_string($message)) {
                return $message;
            }

            return __('invalid {0}.', (string)$field);
        }

        return __('could not be saved.');
    }
}
