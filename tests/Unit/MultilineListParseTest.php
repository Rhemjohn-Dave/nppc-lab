<?php

namespace Tests\Unit;

use App\Services\ControlledPdfFiller;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class MultilineListParseTest extends TestCase
{
    #[Test]
    public function numbered_list_splits_header_and_items(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'parseMultilineList');
        $method->setAccessible(true);

        [$headers, $items] = $method->invoke(
            $filler,
            "NOTES :\n1. First note body.\n2. Second note body.",
            'numbered',
        );

        $this->assertSame(['NOTES :'], $headers);
        $this->assertCount(2, $items);
        $this->assertSame('1.', $items[0]['marker']);
        $this->assertSame('First note body.', $items[0]['body']);
        $this->assertSame('2.', $items[1]['marker']);
        $this->assertSame('Second note body.', $items[1]['body']);
    }

    #[Test]
    public function bullet_list_normalizes_markers(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'parseMultilineList');
        $method->setAccessible(true);

        [, $items] = $method->invoke(
            $filler,
            "- Alpha\n* Beta",
            'bullet',
        );

        $this->assertCount(2, $items);
        $this->assertSame('•', $items[0]['marker']);
        $this->assertSame('Alpha', $items[0]['body']);
        $this->assertSame('•', $items[1]['marker']);
        $this->assertSame('Beta', $items[1]['body']);
    }
}
