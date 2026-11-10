<?php

declare(strict_types=1);

namespace App\Report;

/**
 * Result of the last wpkg.js run of a workstation.
 *
 * The string values are persisted in host_reports.status: never rename them.
 */
enum ReportStatus: string
{
    case Success = 'success';
    case Unknown = 'unknown';
    case Warning = 'warning';
    case Failed = 'failed';

    /**
     * Returns the text shown next to the color.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Success => __('Success'),
            self::Warning => __('Warning'),
            self::Unknown => __('Unknown'),
            self::Failed => __('Failed'),
        };
    }

    /**
     * Returns the CSS class carrying the color.
     *
     * @return string
     */
    public function cssClass(): string
    {
        return 'status-' . $this->value;
    }

    /**
     * Returns the more severe of two statuses.
     *
     * @param \App\Report\ReportStatus $other Other status.
     * @return \App\Report\ReportStatus
     */
    public function worst(ReportStatus $other): ReportStatus
    {
        return $this->severity() >= $other->severity() ? $this : $other;
    }

    /**
     * Ranks the statuses from success to failure.
     *
     * @return int
     */
    private function severity(): int
    {
        return match ($this) {
            self::Success => 0,
            self::Unknown => 1,
            self::Warning => 2,
            self::Failed => 3,
        };
    }
}
