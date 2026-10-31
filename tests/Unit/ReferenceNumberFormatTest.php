<?php

namespace Tests\Unit;

use App\Services\ReferenceNumberService;
use ReflectionMethod;
use Tests\TestCase;

class ReferenceNumberFormatTest extends TestCase
{
    public function test_pads_to_four_digits_until_9999_then_grows(): void
    {
        $service = app(ReferenceNumberService::class);
        $format = new ReflectionMethod($service, 'format');
        $format->setAccessible(true);

        $year = '26';

        $this->assertSame('26-0001', $format->invoke($service, $year, 1));
        $this->assertSame('26-0005', $format->invoke($service, $year, 5));
        $this->assertSame('26-0500', $format->invoke($service, $year, 500));
        $this->assertSame('26-9999', $format->invoke($service, $year, 9999));
        $this->assertSame('26-10000', $format->invoke($service, $year, 10000));
        $this->assertSame('26-10001', $format->invoke($service, $year, 10001));
    }
}
