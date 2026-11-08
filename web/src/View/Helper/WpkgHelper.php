<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\Model\Entity\PackageCheck;
use App\Model\Enum\CheckCondition;
use App\Model\Enum\CheckType;
use Cake\I18n\Number;
use Cake\View\Helper;
use DateTimeInterface;

/**
 * Rendering shortcuts for the WPKG administration pages.
 *
 * @property \Cake\View\Helper\FormHelper $Form
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\UrlHelper $Url
 */
class WpkgHelper extends Helper
{
    /**
     * Helpers used by this helper.
     *
     * @var array<string>
     */
    protected array $helpers = ['Form', 'Html', 'Url'];

    /**
     * Formats a timestamp.
     *
     * @param \DateTimeInterface|null $date Timestamp.
     * @return string
     */
    public function date(?DateTimeInterface $date): string
    {
        return $date?->format('Y-m-d h:i:s A') ?? '';
    }

    /**
     * Builds the label option of a form control.
     *
     * @param string $text Label text.
     * @param string|null $title Tooltip.
     * @param bool $required Show the required marker.
     * @return array<string, mixed>
     */
    public function label(string $text, ?string $title = null, bool $required = false): array
    {
        $label = [
            'text' => ($required ? '<span class="required">*</span>' : '') . h($text),
            'escape' => false,
        ];
        if ($title !== null) {
            $label['title'] = $title;
        }

        return $label;
    }

    /**
     * Renders a symbol of the SVG sprite, coloured by the text colour of its parent.
     *
     * @param string $name Symbol identifier in `webroot/img/icons.svg`.
     * @return string
     */
    public function symbol(string $name): string
    {
        return sprintf(
            '<svg class="icon" aria-hidden="true" focusable="false"><use href="%s#%s"/></svg>',
            h($this->Url->image('icons.svg')),
            h($name),
        );
    }

    /**
     * Renders an icon linking to a page.
     *
     * @param string $name Symbol identifier.
     * @param array<string, mixed> $url Target.
     * @param string $alt Accessible name and tooltip.
     * @return string
     */
    public function icon(string $name, array $url, string $alt): string
    {
        return $this->Html->link($this->symbol($name), $url, [
            'escape' => false,
            'title' => $alt,
            'aria-label' => $alt,
            'class' => 'icon-link icon-' . $name,
        ]);
    }

    /**
     * Renders an icon submitting a POST request, for actions changing data.
     *
     * @param string $name Symbol identifier.
     * @param array<string, mixed> $url Target.
     * @param string $alt Accessible name and tooltip.
     * @param string|null $confirm Confirmation question.
     * @return string
     */
    public function postIcon(string $name, array $url, string $alt, ?string $confirm = null): string
    {
        return $this->Form->postLink($this->symbol($name), $url, [
            'escapeTitle' => false,
            'title' => $alt,
            'aria-label' => $alt,
            'class' => 'icon-link icon-' . $name,
            'confirm' => $confirm,
        ]);
    }

    /**
     * Renders the four move buttons of an ordered record.
     *
     * @param array<string, mixed> $url Move action, the direction gets appended.
     * @return string
     */
    public function moveIcons(array $url): string
    {
        $icons = [];
        foreach (['top', 'up', 'down', 'bottom'] as $direction) {
            $icons[] = $this->postIcon('go-' . $direction, [...$url, $direction], 'Move ' . $direction);
        }

        return implode('&nbsp;', $icons);
    }

    /**
     * Returns the CSS classes of a menu entry.
     *
     * @param string $path Path of the entry.
     * @return string
     */
    public function navigationClass(string $path): string
    {
        $isCurrent = str_starts_with($this->getView()->getRequest()->getPath(), $path);

        return 'navLink' . ($isCurrent ? ' navLinkSelected' : '');
    }

    /**
     * Describes a check in one line of plain text.
     *
     * @param \App\Model\Entity\PackageCheck $check Check.
     * @return string
     */
    public function checkSummary(PackageCheck $check): string
    {
        $condition = CheckCondition::tryFrom($check->condition);
        $path = (string)$check->path;
        $value = (string)$check->value;

        return match ($condition?->type()) {
            CheckType::Logical => 'Logical ' . ucfirst($condition->xmlValue())
                . ($condition->usesValue() ? ' ' . $value : ''),
            CheckType::Registry => sprintf('Registry Path "%s" ', $path)
                . ($condition === CheckCondition::RegistryExists ? 'Exists' : sprintf('Equals "%s"', $value)),
            CheckType::Uninstall => $condition === CheckCondition::UninstallExists
                ? 'Uninstall Exists ' . $path
                : sprintf('Uninstall %s %s %s', $path, $this->comparison($condition), $value),
            CheckType::Host => sprintf('Host %s %s', ucfirst($condition->xmlValue()), $value),
            CheckType::Execute => sprintf(
                'Execute "%s" and ensure the returned exit code %s %s',
                $path,
                $this->comparison($condition),
                $value,
            ),
            CheckType::File => sprintf('File "%s" ', $path) . match ($condition) {
                CheckCondition::FileExists => 'Exists',
                CheckCondition::FileSizeEquals => 'Has a File Size = ' . Number::toReadableSize((float)$value),
                default => sprintf('Has a Version %s %s', $this->comparison($condition), $value),
            },
            default => 'Unknown',
        };
    }

    /**
     * Decides which fields the check form shows for a condition.
     *
     * @param \App\Model\Enum\CheckCondition $condition Condition.
     * @return array{showPath: bool, showValue: bool, pathLabel: string, valueLabel: string}
     */
    public function checkFormFields(CheckCondition $condition): array
    {
        $type = $condition->type();

        return [
            'showPath' => !in_array($type, [CheckType::Logical, CheckType::Host], true),
            'showValue' => $condition->usesValue(),
            'pathLabel' => match ($type) {
                CheckType::Uninstall => 'Add/Remove Name:',
                CheckType::File => 'File Path:',
                CheckType::Execute => 'Executable Path:',
                default => 'Path:',
            },
            'valueLabel' => match (true) {
                $type === CheckType::Execute => 'Exit Code:',
                $condition === CheckCondition::FileSizeEquals => 'Size (in bytes):',
                $type === CheckType::File => 'Version:',
                $type === CheckType::Logical => 'Count:',
                default => 'Value:',
            },
        ];
    }

    /**
     * Returns the comparison sign of a condition.
     *
     * @param \App\Model\Enum\CheckCondition $condition Condition.
     * @return string
     */
    private function comparison(CheckCondition $condition): string
    {
        return match (true) {
            str_contains($condition->xmlValue(), 'smallerthan') => '<',
            str_contains($condition->xmlValue(), 'lessorequal') => '≤',
            str_contains($condition->xmlValue(), 'equalto') => '=',
            str_contains($condition->xmlValue(), 'greaterorequal') => '≥',
            default => '>',
        };
    }
}
