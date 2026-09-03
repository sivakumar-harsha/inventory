<?php

/**
 * Calculate GST figures for a single line item.
 * Single source of truth for the taxable/GST/total formula used by
 * Sales and Purchases store/update/insert paths.
 */
function gst_calculate_line($quantity, $unitPrice, $gstPercent, $gstApplicable): array
{
    $quantity      = (float) $quantity;
    $unitPrice     = (float) $unitPrice;
    $gstPercent    = (float) $gstPercent;
    $gstApplicable = (bool) $gstApplicable;

    $taxable   = round($quantity * $unitPrice, 2);
    $gstAmount = $gstApplicable ? round(($taxable * $gstPercent) / 100, 2) : 0.0;
    $total     = round($taxable + $gstAmount, 2);

    return [
        'gst_percent'    => $gstPercent,
        'gst_applicable' => $gstApplicable ? 1 : 0,
        'total'          => $taxable,
        'gst_amount'     => $gstAmount,
        'total_with_gst' => $total,
    ];
}

/**
 * Sum taxable amount / GST amount / grand total across a set of line items
 * (each with 'total' and 'gst_amount' keys). Works correctly for mixed
 * GST/non-GST invoices since non-applicable lines simply carry gst_amount = 0.
 */
function gst_summarize_items(array $items): array
{
    $taxable = 0.0;
    $gst     = 0.0;

    foreach ($items as $item) {
        $taxable += (float) ($item['total'] ?? 0);
        $gst     += (float) ($item['gst_amount'] ?? 0);
    }

    return [
        'taxable_amount' => round($taxable, 2),
        'gst_amount'     => round($gst, 2),
        'grand_total'    => round($taxable + $gst, 2),
    ];
}
