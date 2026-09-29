<?php

/**
 * Release 4.8.7A: small formatting helpers used inside app/Views/pdf/*.php
 * templates. Read-only, presentation-only — no query logic lives here.
 */

if (! function_exists('pdf_currency')) {
    /** 2-decimal, thousands-separated amount, e.g. 1,234.56. */
    function pdf_currency($amount): string
    {
        return number_format((float) $amount, 2);
    }
}

if (! function_exists('pdf_date')) {
    /** Y-m-d (or any strtotime-parsable value) -> dd-mm-yyyy; blank/unparsable input returns '-'. */
    function pdf_date(?string $ymd): string
    {
        if ($ymd === null || trim($ymd) === '') {
            return '-';
        }

        $ts = strtotime($ymd);

        return $ts ? date('d-m-Y', $ts) : '-';
    }
}

if (! function_exists('pdf_datetime')) {
    /** Y-m-d H:i:s -> dd-mm-yyyy hh:mm AM/PM; blank/unparsable input returns '-'. */
    function pdf_datetime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        $ts = strtotime($value);

        return $ts ? date('d-m-Y h:i A', $ts) : '-';
    }
}

if (! function_exists('pdf_filter_line')) {
    /**
     * Renders an "Applied filters" line from a simple label => value array.
     * Empty/null values are skipped; returns 'All records' when nothing was applied.
     */
    function pdf_filter_line(array $filters): string
    {
        $parts = [];

        foreach ($filters as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $label . ': ' . $value;
        }

        return $parts ? implode('  |  ', $parts) : 'All records';
    }
}

if (! function_exists('pdf_text')) {
    /** Plain text fallback for blank/null values in PDF table cells. */
    function pdf_text($value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '-';
    }
}
