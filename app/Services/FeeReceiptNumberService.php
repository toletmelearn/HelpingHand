<?php

namespace App\Services;

use App\Models\FeeCollection;

class FeeReceiptNumberService
{
    const PREFIX = 'SCH-REC-';

    /**
     * Generate the next unique receipt number using the canonical pattern.
     * It ignores incompatible formats (like RCPT-xxx) by filtering only matching prefixes
     * and calculating the max numeric value from the latest records.
     */
    public static function generateNextReceiptNumber(): string
    {
        $prefix = self::PREFIX;
        
        // Fetch up to 50 latest matching records by ID to determine the maximum sequence number
        $latestRecords = FeeCollection::where('receipt_no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $maxNumber = 0;
        foreach ($latestRecords as $record) {
            $numericPart = substr($record->receipt_no, strlen($prefix));
            if (is_numeric($numericPart)) {
                $num = intval($numericPart);
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
