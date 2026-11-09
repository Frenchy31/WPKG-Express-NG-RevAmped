<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\Model\Entity\PackageCheck;
use App\Model\Enum\CheckCondition;
use App\Model\Enum\CheckType;
use App\View\Language;
use Cake\I18n\I18n;
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
        $language = Language::fromLocale(I18n::getLocale()) ?? Language::English;

        return $date?->format($language->dateFormat()) ?? '';
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
        $labels = [
            'top' => __('Move top'),
            'up' => __('Move up'),
            'down' => __('Move down'),
            'bottom' => __('Move bottom'),
        ];
        $icons = [];
        foreach (array_keys($labels) as $direction) {
            $icons[] = $this->postIcon('go-' . $direction, [...$url, $direction], $labels[$direction]);
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
            CheckType::Logical => $condition->usesValue()
                ? __('Logical {0} {1}', ucfirst($condition->xmlValue()), $value)
                : __('Logical {0}', ucfirst($condition->xmlValue())),
            CheckType::Registry => $condition === CheckCondition::RegistryExists
                ? __('Registry Path "{0}" Exists', $path)
                : __('Registry Path "{0}" Equals "{1}"', $path, $value),
            CheckType::Uninstall => $condition === CheckCondition::UninstallExists
                ? __('Uninstall Exists {0}', $path)
                : __('Uninstall {0} {1} {2}', $path, $this->comparison($condition), $value),
            CheckType::Host => __('Host {0} {1}', ucfirst($condition->xmlValue()), $value),
            CheckType::Execute => __(
                'Execute "{0}" and ensure the returned exit code {1} {2}',
                $path,
                $this->comparison($condition),
                $value,
            ),
            CheckType::File => match ($condition) {
                CheckCondition::FileExists => __('File "{0}" Exists', $path),
                CheckCondition::FileSizeEquals => __(
                    'File "{0}" Has a File Size = {1}',
                    $path,
                    Number::toReadableSize((float)$value),
                ),
                default => __('File "{0}" Has a Version {1} {2}', $path, $this->comparison($condition), $value),
            },
            default => __('Unknown'),
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
                CheckType::Uninstall => __('Add/Remove Name:'),
                CheckType::File => __('File Path:'),
                CheckType::Execute => __('Executable Path:'),
                default => __('Path:'),
            },
            'valueLabel' => match (true) {
                $type === CheckType::Execute => __('Exit Code:'),
                $condition === CheckCondition::FileSizeEquals => __('Size (in bytes):'),
                $type === CheckType::File => __('Version:'),
                $type === CheckType::Logical => __('Count:'),
                default => __('Value:'),
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
