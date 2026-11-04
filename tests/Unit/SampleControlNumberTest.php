<?php

namespace Tests\Unit;

use App\Support\SampleControlNumber;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SampleControlNumberTest extends TestCase
{
    #[Test]
    public function single_sample_has_no_letter_suffix(): void
    {
        $this->assertSame(
            '2026-08',
            SampleControlNumber::forIndex('2026-08', 0, 1),
        );
    }

    #[Test]
    public function multiple_samples_use_letter_suffixes(): void
    {
        $this->assertSame('2026-08A', SampleControlNumber::forIndex('2026-08', 0, 2));
        $this->assertSame('2026-08B', SampleControlNumber::forIndex('2026-08', 1, 2));
        $this->assertSame('2026-08C', SampleControlNumber::forIndex('2026-08', 2, 3));
    }

    #[Test]
    public function suffixes_wrap_to_double_letters_after_z(): void
    {
        $this->assertSame('Z', SampleControlNumber::letterSuffix(25));
        $this->assertSame('AA', SampleControlNumber::letterSuffix(26));
        $this->assertSame('AB', SampleControlNumber::letterSuffix(27));
        $this->assertSame('2026-08Z', SampleControlNumber::forIndex('2026-08', 25, 28));
        $this->assertSame('2026-08AA', SampleControlNumber::forIndex('2026-08', 26, 28));
        $this->assertSame('2026-08AB', SampleControlNumber::forIndex('2026-08', 27, 28));
    }

    #[Test]
    public function out_of_range_and_empty_reference_return_null(): void
    {
        $this->assertNull(SampleControlNumber::forIndex('2026-08', 1, 1));
        $this->assertNull(SampleControlNumber::forIndex('', 0, 1));
        $this->assertNull(SampleControlNumber::forIndex('2026-08', 0, 0));
    }
}
