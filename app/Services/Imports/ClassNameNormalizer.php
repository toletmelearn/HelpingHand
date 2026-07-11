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
     * confidently resolved (e.g. "11"/"XI" with no stream, when this school
     * only has streamed 11th/12th classes -- guessing which stream would be
     * silently misplacing a student into the wrong section).
     */
    public function guessCanonicalName(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        $value = preg_replace('/\s+/', ' ', $value);
        if ($value === '') {
            return null;
        }

        if (isset(self::PRE_PRIMARY_ALIASES[$value])) {
            return self::PRE_PRIMARY_ALIASES[$value];
        }

        // Strip a leading "class"/"grade"/"standard"/"std"/"year" label.
        $stripped = preg_replace('/^(class|grade|standard|std\.?|yr\.?|year)\s*[-:]?\s*/i', '', $value);
        $stripped = trim($stripped);

        // Strip a trailing ordinal suffix on a leading number (1st, 2nd, 3rd, 4th...).
        $stripped = preg_replace('/^(\d+)\s*(st|nd|rd|th)\b/i', '$1', $stripped);
        $stripped = trim($stripped, " -.");

        if (!preg_match('/^([a-z0-9]+)\s*([a-z]+)?\.?$/i', $stripped, $matches)) {
            return null;
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
            return null;
        }

        if ($number < 1 || $number > 12) {
            return null;
        }

        if ($number < 11) {
            return "Class {$number}";
        }

        // 11th/12th are ambiguous without a stream on this school's setup
        // (Science/Commerce/Arts are separate configured classes) -- don't
        // guess which one a bare "11"/"XI" means.
        if ($streamToken !== null && isset(self::STREAM_ALIASES[$streamToken])) {
            return "Class {$number} " . self::STREAM_ALIASES[$streamToken];
        }

        return null;
    }
}
