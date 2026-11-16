<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fo2aLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_fo2a_footer_matches_abstracted_paper_layout(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2A')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $revision->load('fields');

        $meta = $revision->fields->firstWhere('name', 'fo2_form_meta');
        $this->assertNotNull($meta);
        $metaText = (string) (($meta->options['static_text'] ?? ''));
        $this->assertStringContainsString('LSP 7.8_FO2', $metaText);
        $this->assertStringContainsString('CHE WW', $metaText);
        $this->assertStringContainsString('Rev. 17/Issue 18', $metaText);
        $this->assertSame('fo2_notes', $meta->options['anchor']['below'] ?? null);

        $reviewed = $revision->fields->firstWhere('name', 'fo2_signatory_reviewed');
        $certified = $revision->fields->firstWhere('name', 'fo2_signatory_certified');
        $noted = $revision->fields->firstWhere('name', 'fo2_signatory_noted');
        $this->assertNotNull($reviewed);
        $this->assertNotNull($certified);
        $this->assertNotNull($noted);

        $reviewedOffset = (float) ($reviewed->options['anchor']['offset_mm'] ?? 0);
        $certifiedOffset = (float) ($certified->options['anchor']['offset_mm'] ?? 0);
        $notedOffset = (float) ($noted->options['anchor']['offset_mm'] ?? 0);

        $this->assertSame('fo2_notes', $reviewed->options['anchor']['below'] ?? null);
        $this->assertEqualsWithDelta(13.0, $reviewedOffset, 0.01);
        $this->assertEqualsWithDelta($reviewedOffset, $certifiedOffset, 0.01);
        $this->assertGreaterThan($reviewedOffset, $notedOffset);
        // Noted = Reviewed offset + box h + 4mm clearance (designer baseline).
        $this->assertEqualsWithDelta(39.0, $notedOffset, 0.01);
        $this->assertEqualsWithDelta(
            $reviewedOffset + (float) $reviewed->height + 4.0,
            $notedOffset,
            0.01,
        );

        $this->assertIsArray($reviewed->options['signatory_block'] ?? null);
        $this->assertStringContainsString(
            'LANACA',
            (string) ($reviewed->options['static_text'] ?? ''),
        );
        $this->assertStringContainsString(
            'Laboratory Analyst',
            (string) ($reviewed->options['static_text'] ?? ''),
        );
        $this->assertStringContainsString(
            'Registered Chemical Technician, License #00761',
            (string) ($reviewed->options['static_text'] ?? ''),
        );
    }
}
