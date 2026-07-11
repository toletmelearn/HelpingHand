<?php

namespace Tests\Unit\Services\Imports;

use App\Services\Imports\ClassNameNormalizer;
use PHPUnit\Framework\TestCase;

class ClassNameNormalizerTest extends TestCase
{
    private ClassNameNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ClassNameNormalizer();
    }

    /**
     * @dataProvider primaryClassProvider
     */
    public function test_it_resolves_common_primary_class_formats(string $raw, string $expected)
    {
        $this->assertEquals($expected, $this->normalizer->guessCanonicalName($raw));
    }

    public static function primaryClassProvider(): array
    {
        return [
            'plain digit' => ['1', 'Class 1'],
            'ordinal' => ['1st', 'Class 1'],
            'roman numeral' => ['I', 'Class 1'],
            'roman numeral lowercase' => ['iv', 'Class 4'],
            'word number' => ['One', 'Class 1'],
            'word number mixed case' => ['Seven', 'Class 7'],
            'with Class prefix' => ['Class 5', 'Class 5'],
            'with Grade prefix' => ['Grade 5', 'Class 5'],
            'with Std prefix' => ['Std 5', 'Class 5'],
            'with Std. prefix' => ['Std. 5', 'Class 5'],
            'with Standard prefix' => ['Standard 5', 'Class 5'],
            'ordinal with prefix' => ['Class 2nd', 'Class 2'],
            'roman with prefix' => ['Class X', 'Class 10'],
            'extra whitespace' => ['  1  ', 'Class 1'],
            'double digit' => ['10', 'Class 10'],
            'double digit roman' => ['x', 'Class 10'],
        ];
    }

    /**
     * @dataProvider prePrimaryProvider
     */
    public function test_it_resolves_pre_primary_aliases(string $raw, string $expected)
    {
        $this->assertEquals($expected, $this->normalizer->guessCanonicalName($raw));
    }

    public static function prePrimaryProvider(): array
    {
        return [
            'nursery' => ['Nursery', 'Nursery'],
            'nursery lowercase' => ['nursery', 'Nursery'],
            'pn' => ['PN', 'Nursery'],
            'lkg' => ['LKG', 'LKG'],
            'l.k.g' => ['L.K.G', 'LKG'],
            'kg1' => ['KG1', 'LKG'],
            'junior kg' => ['Junior KG', 'LKG'],
            'ukg' => ['UKG', 'UKG'],
            'kg2' => ['KG2', 'UKG'],
            'senior kg' => ['Senior KG', 'UKG'],
        ];
    }

    /**
     * @dataProvider streamedClassProvider
     */
    public function test_it_resolves_11th_and_12th_streams_when_given_and_offers_plain_class_when_not(string $raw, ?string $expected)
    {
        $this->assertEquals($expected, $this->normalizer->guessCanonicalName($raw));
    }

    public static function streamedClassProvider(): array
    {
        return [
            '11 with science' => ['11 Science', 'Class 11 Science'],
            'XI with commerce' => ['XI Commerce', 'Class 11 Commerce'],
            'twelve with arts' => ['Twelve Arts', 'Class 12 Arts'],
            '12th with sci abbreviation' => ['12th Sci', 'Class 12 Science'],
            // A stream is never guessed when not specified -- but the plain,
            // unstreamed class name IS offered as a candidate, for schools
            // that configure 11th/12th without splitting by stream. Whether
            // this actually resolves depends on whether that plain class
            // exists in a given school's configuration (a DB-layer concern,
            // not the normalizer's) -- the normalizer's job is only to never
            // suggest a specific stream that wasn't given.
            'bare 11 has no stream -- offers the plain class, never a stream' => ['11', 'Class 11'],
            'bare XI has no stream -- offers the plain class, never a stream' => ['XI', 'Class 11'],
            'bare 12 has no stream -- offers the plain class, never a stream' => ['Class 12', 'Class 12'],
        ];
    }

    public function test_it_returns_null_for_unrecognizable_values()
    {
        $this->assertNull($this->normalizer->guessCanonicalName('Robotics Club'));
        $this->assertNull($this->normalizer->guessCanonicalName('13')); // out of range
        $this->assertNull($this->normalizer->guessCanonicalName('0'));  // out of range
        $this->assertNull($this->normalizer->guessCanonicalName(''));
    }
}
