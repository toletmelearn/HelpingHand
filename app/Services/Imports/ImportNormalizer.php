<?php

namespace App\Services\Imports;

use Carbon\Carbon;

class ImportNormalizer
{
    /**
     * Clean and normalize a full row of data.
     */
    public function normalizeRow(array $row): array
    {
        // 1. Intelligently split multiple phone/mobile numbers if present in the 'mobile' field
        if (isset($row['mobile']) && !empty($row['mobile'])) {
            // Replace newlines, commas, slashes, semicolons with space
            $mobileVal = str_replace(["\r", "\n", ",", "/", ";"], ' ', (string)$row['mobile']);
            $parts = array_filter(array_map('trim', explode(' ', $mobileVal)));
            
            $numbers = [];
            foreach ($parts as $part) {
                $digits = preg_replace('/[^0-9]/', '', $part);
                if (strlen($digits) >= 8) {
                    $numbers[] = $digits;
                }
            }

            if (count($numbers) > 1) {
                $row['mobile'] = $numbers[0];
                if (!isset($row['phone']) || empty($row['phone'])) {
                    $row['phone'] = $numbers[1];
                }
            } else if (count($numbers) === 1) {
                $row['mobile'] = $numbers[0];
            }
        }

        // 2. Normalize values
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$key] = $this->normalizeValue($key, $value);
        }
        return $normalized;
    }

    /**
     * Normalize individual value based on field type.
     */
    public function normalizeValue(string $key, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $valStr = is_string($value) ? trim($value) : (string)$value;

        // Clean emojis, control characters, and Unicode invisible whitespaces
        $valStr = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $valStr);
        $valStr = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $valStr);
        $valStr = trim($valStr);

        $lowerKey = strtolower($key);

        // Date of Birth or general dates normalization
        if (str_contains($lowerKey, 'date') || str_contains($lowerKey, 'dob')) {
            return $this->normalizeDate($valStr);
        }

        // Phone/mobile numbers normalization
        if (str_contains($lowerKey, 'phone') || str_contains($lowerKey, 'mobile') || str_contains($lowerKey, 'contact')) {
            return $this->normalizePhone($valStr);
        }

        // Gender normalization
        if ($lowerKey === 'gender') {
            return $this->normalizeGender($valStr);
        }

        // Blood group normalization
        if (str_contains($lowerKey, 'blood')) {
            return $this->normalizeBloodGroup($valStr);
        }

        return $valStr;
    }

    private function normalizeDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') {
            return null;
        }

        // Excel serial date (numeric day-count, e.g. 43601 for 2019-05-15).
        if (is_numeric($val) && (int) $val > 10000 && !str_contains($val, '.')) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $val))->format('Y-m-d');
            } catch (\Exception $e) {
                // Fall through to text-based parsing below.
            }
        }

        // Normalize every common separator schools actually use for a purely
        // numeric date -- dots, slashes, and stray spaces (e.g. "15 05 2019")
        // -- to a single dash, rather than only handling '/' and '.' as
        // before. Only do this when the value is purely numeric-with-
        // separators: a month-name date like "15 May 2019" or "May 15, 2019"
        // relies on its original spaces/commas for Carbon's native parser to
        // read correctly, and collapsing those would break it.
        $cleaned = preg_match('/^[\d.\/\s-]+$/', $val)
            ? trim(preg_replace('/[.\/\s]+/', '-', $val), '-')
            : $val;

        // A numeric D-M-Y or D-M-YY value is genuinely ambiguous to a generic
        // parser (Carbon::parse() defaults to US-style M-D-Y), which silently
        // produces the WRONG date rather than failing loudly. This system is
        // used by Indian schools where D-M-Y is the standard convention, so
        // parse numeric dates explicitly against that assumption instead of
        // guessing via a generic parser.
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $cleaned, $m)) {
            [, $day, $month, $year] = $m;
            if (checkdate((int) $month, (int) $day, (int) $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            // Fall through -- e.g. day/month were swapped in a way that isn't
            // a valid D-M-Y date; let the generic parser below have a try.
        }

        // ISO-style Y-M-D is unambiguous regardless of convention.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $cleaned, $m)) {
            [, $year, $month, $day] = $m;
            if (checkdate((int) $month, (int) $day, (int) $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Month-name formats ("15 May 2019", "May 15, 2019") aren't
        // ambiguous -- safe to let Carbon's flexible parser handle these.
        try {
            return Carbon::parse($cleaned)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizePhone(string $val): string
    {
        // Strip non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $val);
        // Take last 10 digits
        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }
        return $digits;
    }

    private function normalizeGender(string $val): string
    {
        $cleaned = strtolower(trim($val));
        if (in_array($cleaned, ['m', 'male', 'boy'])) {
            return 'male';
        }
        if (in_array($cleaned, ['f', 'female', 'girl'])) {
            return 'female';
        }
        return 'other';
    }

    private function normalizeBloodGroup(string $val): string
    {
        $cleaned = strtoupper(trim($val));
        $cleaned = str_replace(['VE', ' ', 'POS', 'NEG'], '', $cleaned);
        
        $valid = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        foreach ($valid as $bg) {
            if ($cleaned === $bg || str_starts_with($cleaned, $bg)) {
                return $bg;
            }
        }

        // Default missing sign to positive
        if (in_array($cleaned, ['A', 'B', 'AB', 'O'])) {
            return $cleaned . '+';
        }

        return $val;
    }
}
