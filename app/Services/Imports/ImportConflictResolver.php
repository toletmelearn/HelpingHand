<?php

namespace App\Services\Imports;

use Illuminate\Support\Facades\DB;

class ImportConflictResolver
{
    /**
     * Compute duplicate match score based on weights.
     * Returns an array: ['status' => 'new|duplicate|warning', 'matched_id' => $id, 'score' => $score]
     */
    public function detectDuplicate(string $table, array $data, array $weights): array
    {
        $hasSoftDeletes = \Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at');

        // 1. Check for primary unique key hits (Aadhar, Admission No)
        if (isset($data['admission_no']) && !empty($data['admission_no'])) {
            $query = DB::table($table)->where('admission_no', $data['admission_no']);
            if ($hasSoftDeletes) {
                $query->whereNull('deleted_at');
            }
            $matched = $query->first();
            if ($matched) {
                return ['status' => 'duplicate', 'matched_id' => $matched->id, 'score' => 100, 'reason' => 'Duplicate Admission Number'];
            }
        }

        // students standardized on "aadhaar_number"; teachers/guardians/etc.
        // still use the original "aadhar_number" spelling -- this resolver
        // is shared across all of them, so pick the key/column that
        // actually matches the table being imported.
        $aadhaarKey = $table === 'students' ? 'aadhaar_number' : 'aadhar_number';

        if (isset($data[$aadhaarKey]) && !empty($data[$aadhaarKey])) {
            $query = DB::table($table)->where($aadhaarKey, $data[$aadhaarKey]);
            if ($hasSoftDeletes) {
                $query->whereNull('deleted_at');
            }
            $matched = $query->first();
            if ($matched) {
                return ['status' => 'duplicate', 'matched_id' => $matched->id, 'score' => 95, 'reason' => 'Duplicate Aadhaar Number'];
            }
        }

        // 2. Compute weighted score lookup for Name + DOB + Parent contact
        $score = 0;
        $potentialMatchId = null;
        $matchedReasons = [];

        // Check name + DOB combo
        if (isset($data['name']) && isset($data['date_of_birth'])) {
            // date_of_birth is normalized to a bare 'Y-m-d' string, but the
            // stored column may carry a time component ('Y-m-d H:i:s') depending
            // on the DB driver -- compare via DATE() so a stray time component
            // never silently defeats duplicate matching.
            $query = DB::table($table)
                ->where('name', 'like', $data['name'])
                ->whereRaw('DATE(date_of_birth) = ?', [$data['date_of_birth']]);
            if ($hasSoftDeletes) {
                $query->whereNull('deleted_at');
            }
            $matched = $query->first();
            if ($matched) {
                $score += $weights['name_dob'] ?? 80;
                $potentialMatchId = $matched->id;
                $matchedReasons[] = 'Identical Name & DOB';
            }
        }

        // Check primary contact mobile number
        if (isset($data['mobile']) && !empty($data['mobile'])) {
            $query = DB::table($table)->where('mobile', $data['mobile']);
            if ($hasSoftDeletes) {
                $query->whereNull('deleted_at');
            }
            $matched = $query->first();
            if ($matched) {
                $score += $weights['mobile'] ?? 75;
                if (!$potentialMatchId) {
                    $potentialMatchId = $matched->id;
                }
                $matchedReasons[] = 'Matching Parent Phone Number';
            }
        }

        if ($score >= 90) {
            return [
                'status' => 'duplicate',
                'matched_id' => $potentialMatchId,
                'score' => $score,
                'reason' => implode(', ', $matchedReasons)
            ];
        }

        if ($score >= 60) {
            return [
                'status' => 'warning',
                'matched_id' => $potentialMatchId,
                'score' => $score,
                'reason' => 'Potential duplicate detected: ' . implode(', ', $matchedReasons)
            ];
        }

        return ['status' => 'new', 'matched_id' => null, 'score' => $score, 'reason' => 'New record'];
    }
}
