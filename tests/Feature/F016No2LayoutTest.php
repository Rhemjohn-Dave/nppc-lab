<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class F016No2LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_f016_no2_blueprint_deploys_max_level_notes_and_footer_chrome(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-NO2')->firstOrFail();
        $this->assertSame(1, (int) $form->analyst_signatory_slots);
        $this->assertTrue((bool) $form->analyst_require_prc);

        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertSame('1', $revision->revision);
        $revision->load('fields');

        $names = $revision->fields->pluck('name')->all();
        $this->assertContains('nitrite_f016_matrix', $names);
        $this->assertContains('no2_f016_max_level', $names);
        $this->assertContains('no2_f016_notes', $names);
        $this->assertContains('no2_f016_label_analyzed_by', $names);
        $this->assertContains('no2_f016_label_approved_by', $names);
        $this->assertContains('results.analyst_name', $names);
        $this->assertContains('results.analyst_prc', $names);
        $this->assertContains('no2_f016_approved_by_name', $names);
        $this->assertContains('no2_f016_lic_approved', $names);
        $this->assertContains('no2_f016_form_meta', $names);

        $maxLevel = $revision->fields->firstWhere('name', 'no2_f016_max_level');
        $this->assertStringContainsString(
            '167 mg/kg',
            (string) (($maxLevel?->options['static_text'] ?? '')),
        );
        $this->assertStringContainsString(
            'DOH-BFAD',
            (string) (($maxLevel?->options['static_text'] ?? '')),
        );

        $notes = $revision->fields->firstWhere('name', 'no2_f016_notes');
        $this->assertSame('numbered', $notes?->options['list_style'] ?? null);
        $this->assertStringContainsString(
            'Results of examination specifically related',
            (string) (($notes?->options['static_text'] ?? '')),
        );

        $analyst = $revision->fields->firstWhere('name', 'results.analyst_name');
        $this->assertStringContainsString(
            'Ferresa Mae O. Caberoy',
            (string) (($analyst?->options['static_text'] ?? '')),
        );

        $prc = $revision->fields->firstWhere('name', 'results.analyst_prc');
        $this->assertStringContainsString(
            '16125',
            (string) (($prc?->options['static_text'] ?? '')),
        );

        $approved = $revision->fields->firstWhere('name', 'no2_f016_approved_by_name');
        $this->assertStringContainsString(
            'Wendy A. Tayson, RCh, MEE',
            (string) (($approved?->options['static_text'] ?? '')),
        );

        $licApproved = $revision->fields->firstWhere('name', 'no2_f016_lic_approved');
        $this->assertStringContainsString(
            '15131',
            (string) (($licApproved?->options['static_text'] ?? '')),
        );

        $meta = $revision->fields->firstWhere('name', 'no2_f016_form_meta');
        $metaText = (string) (($meta?->options['static_text'] ?? ''));
        $this->assertStringContainsString('NPPC ADL', $metaText);
        $this->assertStringContainsString('LSP 7.8 F016', $metaText);
        $this->assertStringContainsString('Rev 03 / Issue 04', $metaText);
        $this->assertStringContainsString('07/15/2026', $metaText);

        $this->assertSame(1, $form->revisions()->count());
    }
}
