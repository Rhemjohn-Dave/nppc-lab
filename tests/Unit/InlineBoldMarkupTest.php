<?php

namespace Tests\Unit;

use App\Support\InlineBoldMarkup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InlineBoldMarkupTest extends TestCase
{
    #[Test]
    public function segments_split_bold_markers(): void
    {
        $segments = InlineBoldMarkup::segments("**Test Methods and References:**\nBody line");

        $this->assertSame(
            [
                ['text' => 'Test Methods and References:', 'bold' => true],
                ['text' => "\nBody line", 'bold' => false],
            ],
            $segments,
        );
    }

    #[Test]
    public function strip_removes_markers(): void
    {
        $this->assertSame(
            'Title body',
            InlineBoldMarkup::strip('**Title** body'),
        );
    }

    #[Test]
    public function plain_text_is_a_single_non_bold_segment(): void
    {
        $this->assertSame(
            [['text' => 'plain', 'bold' => false]],
            InlineBoldMarkup::segments('plain'),
        );
    }
}
