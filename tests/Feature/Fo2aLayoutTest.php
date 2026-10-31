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

        $reviewed = $revision->fields->firstWhere('name', 'fo2_label_reviewed');
        $certified = $revision->fields->firstWhere('name', 'fo2_label_certified');
        $noted = $revision->fields->firstWhere('name', 'fo2_label_noted');
        $this->assertNotNull($reviewed);
        $this->assertNotNull($certified);
        $this->assertNotNull($noted);

        $reviewedOffset = (float) ($reviewed->options['anchor']['offset_mm'] ?? 0);
        $certifiedOffset = (float) ($certified->options['anchor']['offset_mm'] ?? 0);
        $notedOffset = (float) ($noted->options['anchor']['offset_mm'] ?? 0);

        $this->assertEqualsWithDelta($reviewedOffset, $certifiedOffset, 0.01);
        $this->assertGreaterThan($reviewedOffset, $notedOffset);

        $this->assertNull($revision->fields->firstWhere('name', 'results.analyst_prc'));

        $roleReviewed = $revision->fields->firstWhere('name', 'fo2_role_reviewed');
        $licReviewed = $revision->fields->firstWhere('name', 'fo2_lic_reviewed');
        $this->assertNotNull($roleReviewed);
        $this->assertNotNull($licReviewed);
        $this->assertSame('Laboratory Analyst', (string) ($roleReviewed->options['static_text'] ?? ''));
        $this->assertStringContainsString(
            'Registered Chemical Technician, License #00761',
            (string) ($licReviewed->options['static_text'] ?? ''),
        );

        $nameReviewed = $revision->fields->firstWhere('name', 'results.analyst_name');
        $this->assertStringContainsString(
            'LANACA',
            (string) ($nameReviewed?->options['static_text'] ?? ''),
        );
    }
}
