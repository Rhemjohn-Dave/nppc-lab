<?php

namespace Tests\Unit;

use App\Support\TcpdfCalibriFont;
use Tests\TestCase;

class TcpdfCalibriFontTest extends TestCase
{
    public function test_ensure_registers_calibri_when_the_ttf_is_present(): void
    {
        if (TcpdfCalibriFont::locateTtf() === null) {
            $this->assertFalse(TcpdfCalibriFont::ensure());

            return;
        }

        $this->assertTrue(TcpdfCalibriFont::ensure());
        $this->assertFileExists(TcpdfCalibriFont::phpPath());
    }
}
