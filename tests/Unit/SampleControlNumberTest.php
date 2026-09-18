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
    public function out_of_range_and_empty_reference_return_null(): void
    {
        $this->assertNull(SampleControlNumber::forIndex('2026-08', 1, 1));
        $this->assertNull(SampleControlNumber::forIndex('', 0, 1));
        $this->assertNull(SampleControlNumber::forIndex('2026-08', 0, 0));
    }
}
