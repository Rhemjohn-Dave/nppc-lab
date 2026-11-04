<?php

namespace Tests\Unit;

use App\Support\InlineBoldMarkup;
use App\Support\OfficialAnalysisCatalog;
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

    #[Test]
    public function fo2_footnote_stars_are_not_treated_as_bold_pairs(): void
    {
        $notes = OfficialAnalysisCatalog::fo2FullNotesText();

        $segments = InlineBoldMarkup::segments($notes);
        $this->assertCount(1, $segments);
        $this->assertFalse($segments[0]['bold']);
        $this->assertStringContainsString('5. **Not EMB', $segments[0]['text']);
        $this->assertStringContainsString('6. ***Not PAB', $segments[0]['text']);

        $stripped = InlineBoldMarkup::strip($notes);
        $this->assertStringContainsString('5. **Not EMB', $stripped);
        $this->assertStringContainsString('6. ***Not PAB', $stripped);
        $this->assertSame($notes, $stripped);
    }

    #[Test]
    public function paired_bold_does_not_steal_from_triple_star_footnote(): void
    {
        $text = "5. **Not EMB –DENR but PAB accredited.\n6. ***Not PAB and EMB - DENR Accredited.";

        $this->assertSame(
            [['text' => $text, 'bold' => false]],
            InlineBoldMarkup::segments($text),
        );
        $this->assertSame($text, InlineBoldMarkup::strip($text));
    }
}
