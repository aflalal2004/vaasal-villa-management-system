<?php

namespace App\Modules\Core\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free sequential numbering per document type per year, e.g. INV-26-000123.
 * Uses a row lock so concurrent requests never receive the same number.
 */
class DocumentNumberService
{
    public const PREFIXES = [
        'booking' => 'VV',
        'folio' => 'FOL',
        'invoice' => 'INV',
        'receipt' => 'RCT',
        'proforma' => 'PRO',
        'credit_note' => 'CRN',
        'operator_invoice' => 'OINV',
        'payment' => 'PAY',
        'pos_order' => 'ORD',
        'pos_invoice' => 'PINV',
        'kot' => 'KOT',
        'maintenance' => 'MNT',
        'lost_found' => 'LF',
        'employee' => 'EMP',
        'operator' => 'TO',
    ];

    public static function next(string $type): string
    {
        $year = (int) now()->format('Y');
        $prefix = self::PREFIXES[$type] ?? strtoupper($type);

        return DB::transaction(function () use ($type, $year, $prefix) {
            $seq = DocumentSequence::where('type', $type)->where('year', $year)->lockForUpdate()->first();
            if (! $seq) {
                DocumentSequence::insertOrIgnore(['type' => $type, 'prefix' => $prefix, 'year' => $year, 'next_number' => 1]);
                $seq = DocumentSequence::where('type', $type)->where('year', $year)->lockForUpdate()->first();
            }
            $number = $seq->next_number;
            $seq->increment('next_number');

            $width = in_array($type, ['employee', 'operator'], true) ? 4 : 6;
            return sprintf('%s-%s-%s', $prefix, substr((string) $year, -2), str_pad((string) $number, $width, '0', STR_PAD_LEFT));
        });
    }
}
