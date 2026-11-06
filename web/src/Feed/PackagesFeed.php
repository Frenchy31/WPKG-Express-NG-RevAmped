<?php

declare(strict_types=1);

namespace App\Feed;

use App\Model\Enum\ActionType;
use App\Model\Enum\CheckCondition;
use App\Model\Enum\CheckType;
use App\Model\Enum\ExitCodeReboot;
use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use XMLWriter;

/**
 * Generates packages.xml.
 */
class PackagesFeed extends XmlFeed
{
    /**
     * @inheritDoc
     */
    protected function rootElement(): string
    {
        return 'packages';
    }

    /**
     * @inheritDoc
     */
    protected function table(): Table
    {
        return $this->fetchTable('Packages');
    }

    /**
     * @inheritDoc
     */
    protected function writeRecord(XMLWriter $writer, EntityInterface $record): void
    {
        $writer->startElement('package');
        $writer->writeAttribute('id', $record->get('id_text'));
        $writer->writeAttribute('name', $record->get('name'));
        $writer->writeAttribute('revision', (string)$record->get('revision'));
        $writer->writeAttribute('priority', (string)$record->get('priority'));
        $writer->writeAttribute(
            'reboot',
            (PackageReboot::tryFrom($record->get('reboot')) ?? PackageReboot::False)->xmlValue(),
        );
        if ($record->get('notify')) {
            $writer->writeAttribute('notify', 'true');
        }
        $execute = PackageExecute::tryFrom($record->get('execute'));
        if ($execute !== PackageExecute::Normal) {
            $writer->writeAttribute('execute', ($execute ?? PackageExecute::Once)->xmlValue());
        }

        $this->writeVariables($writer, $record);
        foreach ($record->get('package_dependencies') as $dependency) {
            $writer->startElement('depends');
            $writer->writeAttribute('package-id', $dependency->get('id_text'));
            $writer->endElement();
        }
        $this->writeChecks($writer, $record->get('package_checks'));
        foreach ($record->get('package_actions') as $action) {
            $this->writeAction($writer, $action);
        }
        $writer->endElement();
    }

    /**
     * Writes the check tree of a package. Records come sorted by `lft`.
     *
     * @param \XMLWriter $writer Writer.
     * @param array<\App\Model\Entity\PackageCheck> $checks Checks of the package.
     * @return void
     */
    private function writeChecks(XMLWriter $writer, array $checks): void
    {
        $childrenByParent = [];
        foreach ($checks as $check) {
            $childrenByParent[(int)$check->get('parent_id')][] = $check;
        }

        $this->writeCheckLevel($writer, $childrenByParent, 0);
    }

    /**
     * Writes the checks of one parent and, recursively, their children.
     *
     * @param \XMLWriter $writer Writer.
     * @param array<int, array<\App\Model\Entity\PackageCheck>> $childrenByParent Checks grouped by parent id.
     * @param int $parentId Parent id, 0 for the root level.
     * @return void
     */
    private function writeCheckLevel(XMLWriter $writer, array $childrenByParent, int $parentId): void
    {
        foreach ($childrenByParent[$parentId] ?? [] as $check) {
            $type = CheckType::tryFrom($check->get('type'));
            $condition = CheckCondition::tryFrom($check->get('condition'));

            $writer->startElement('check');
            $writer->writeAttribute('type', $type?->xmlValue() ?? 'unknown');
            $writer->writeAttribute('condition', $condition?->xmlValue() ?? 'unknown');
            if (!in_array($type, [CheckType::Logical, CheckType::Host], true) && $check->get('path') !== null) {
                $writer->writeAttribute('path', $check->get('path'));
            }
            if ($condition?->usesValue() && $check->get('value') !== null) {
                $writer->writeAttribute('value', $check->get('value'));
            }
            $this->writeCheckLevel($writer, $childrenByParent, $check->get('id'));
            $writer->endElement();
        }
    }

    /**
     * Writes an action and its exit codes.
     *
     * @param \XMLWriter $writer Writer.
     * @param \App\Model\Entity\PackageAction $action Action.
     * @return void
     */
    private function writeAction(XMLWriter $writer, EntityInterface $action): void
    {
        $writer->startElement(ActionType::tryFrom($action->get('type'))?->xmlElement() ?? 'unknown_action');
        $writer->writeAttribute('cmd', $action->get('command'));
        foreach (['workdir', 'timeout'] as $attribute) {
            $attributeValue = $action->get($attribute);
            if (!empty($attributeValue) && $attributeValue !== 'NULL') {
                $writer->writeAttribute($attribute, (string)$attributeValue);
            }
        }
        foreach ($action->get('exit_codes') as $exitCode) {
            $writer->startElement('exit');
            $writer->writeAttribute('code', $exitCode->get('code'));
            $reboot = ExitCodeReboot::tryFrom($exitCode->get('reboot'))?->xmlValue();
            if ($reboot !== null) {
                $writer->writeAttribute('reboot', $reboot);
            }
            $writer->endElement();
        }
        $writer->endElement();
    }
}
