<?php

namespace App\Services\Imports;

/**
 * Different schools write the same class in wildly different ways in their
 * own spreadsheets -- "1", "1st", "I", "One", "Class 1", "Grade 1", "Std 1"
 * all mean the same thing to a human. This maps any of those forms to this
 * school's actual configured class name (e.g. "Class 1"), so bulk import
 * doesn't force every school to retype their roster to match one exact
 * string.
 */
class ClassNameNormalizer
{
    private const WORD_NUMBERS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'eleven' => 11, 'twelve' => 12,
    ];

    private const ROMAN_NUMBERS = [
        'xii' => 12, 'xi' => 11, 'x' => 10, 'ix' => 9, 'viii' => 8,
        'vii' => 7, 'vi' => 6, 'v' => 5, 'iv' => 4, 'iii' => 3, 'ii' => 2, 'i' => 1,
    ];

    private const PRE_PRIMARY_ALIASES = [
        'nursery' => 'Nursery', 'pre-nursery' => 'Nursery', 'prenursery' => 'Nursery',
        'pre nursery' => 'Nursery', 'pn' => 'Nursery', 'play group' => 'Nursery', 'playgroup' => 'Nursery',
        'lkg' => 'LKG', 'l.k.g' => 'LKG', 'kg1' => 'LKG', 'kg-1' => 'LKG', 'kg 1' => 'LKG',
        'junior kg' => 'LKG', 'jr kg' => 'LKG', 'jr. kg' => 'LKG',
        'ukg' => 'UKG', 'u.k.g' => 'UKG', 'kg2' => 'UKG', 'kg-2' => 'UKG', 'kg 2' => 'UKG',
        'senior kg' => 'UKG', 'sr kg' => 'UKG', 'sr. kg' => 'UKG',
    ];

    private const STREAM_ALIASES = [
        'science' => 'Science', 'sci' => 'Science',
        'commerce' => 'Commerce', 'com' => 'Commerce', 'comm' => 'Commerce',
        'arts' => 'Arts', 'hum' => 'Arts', 'humanities' => 'Arts',
    ];

    /**
     * Best-guess canonical class name for a raw value, or null if it can't be
     * confidently resolved to a single candidate (e.g. gibberish, or a number
     * outside 1-12). For legacy callers that only want one name; prefer
     * guessCanonicalNames() when the caller can try several candidates.
     */
    public function guessCanonicalName(string $raw): ?string
    {
        return $this->guessCanonicalNames($raw)[0] ?? null;
    }

    /**
     * Best-guess canonical class name(s) for a raw value, most specific
     * first, or an empty array if it can't be resolved at all. Schools
     * differ in how finely they configure classes: some school's "XI"/"11"
     * (no stream given) should resolve straight to a plain "Class 11"; a
     * DIFFERENT school with only streamed 11th-grade classes configured
     * (Science/Commerce/Arts, no plain "Class 11") has no safe single
     * answer for a bare "11" -- guessing a specific stream would silently
     * misplace the student. Returning candidates in priority order lets the
     * caller try each against what THIS school actually has configured,
     * without ever guessing a stream that wasn't actually specified.
     */
    public function guessCanonicalNames(string $raw): array
    {
        $value = strtolower(trim($raw));
        $value = preg_replace('/\s+/', ' ', $value);
        if ($value === '') {
            return [];
        }

        if (isset(self::PRE_PRIMARY_ALIASES[$value])) {
            return [self::PRE_PRIMARY_ALIASES[$value]];
        }

        // Strip a leading "class"/"grade"/"standard"/"std"/"year" label.
        $stripped = preg_replace('/^(class|grade|standard|std\.?|yr\.?|year)\s*[-:]?\s*/i', '', $value);
        $stripped = trim($stripped);

        // Strip a trailing ordinal suffix on a leading number (1st, 2nd, 3rd, 4th...).
        $stripped = preg_replace('/^(\d+)\s*(st|nd|rd|th)\b/i', '$1', $stripped);
        $stripped = trim($stripped, " -.");

        if (!preg_match('/^([a-z0-9]+)\s*([a-z]+)?\.?$/i', $stripped, $matches)) {
            return [];
        }

        $numberToken = strtolower($matches[1]);
        $streamToken = isset($matches[2]) ? strtolower($matches[2]) : null;

        if (ctype_digit($numberToken)) {
            $number = (int) $numberToken;
        } elseif (isset(self::WORD_NUMBERS[$numberToken])) {
            $number = self::WORD_NUMBERS[$numberToken];
        } elseif (isset(self::ROMAN_NUMBERS[$numberToken])) {
            $number = self::ROMAN_NUMBERS[$numberToken];
        } else {
            return [];
        }

        if ($number < 1 || $number > 12) {
            return [];
        }

        if ($number < 11) {
            return ["Class {$number}"];
        }

        // A stream was actually specified ("XI Science") -- that's the most
        // specific, confident candidate.
        if ($streamToken !== null && isset(self::STREAM_ALIASES[$streamToken])) {
            return ["Class {$number} " . self::STREAM_ALIASES[$streamToken]];
        }

        // No stream given ("11", "XI"). Never guess which of
        // Science/Commerce/Arts that means -- but DO offer the plain,
        // unstreamed "Class 11"/"Class 12" as a candidate, for schools that
        // configure 11th/12th without splitting by stream at all. The caller
        // only succeeds with this if that plain class actually exists.
        return ["Class {$number}"];
    }
}
