<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class F016ProxLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_prox_blueprint_deploys_footer_chrome_on_sole_revision_one(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-PROX')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertSame('1', $revision->revision);
        $revision->load('fields');

        $names = $revision->fields->pluck('name')->all();
        $this->assertContains('proximate_f016_matrix', $names);
        $this->assertContains('prox_f016_notes', $names);
        $this->assertContains('prox_f016_label_analyzed_by', $names);
        $this->assertContains('prox_f016_label_approved_by', $names);
        $this->assertContains('prox_f016_approved_by_name', $names);
        $this->assertContains('prox_f016_form_meta', $names);
        $this->assertContains('results.analyst_name', $names);
        $this->assertContains('results.analyst_name_2', $names);
        $this->assertNotContains('dynamic_test_matrix_6', $names);

        $matrix = $revision->fields->firstWhere('name', 'proximate_f016_matrix');
        $this->assertFalse((bool) (($matrix?->table_config['stretch_body'] ?? true)));

        $notes = $revision->fields->firstWhere('name', 'prox_f016_notes');
        $notesText = (string) (($notes?->options['static_text'] ?? ''));
        $this->assertSame('numbered', $notes?->options['list_style'] ?? null);
        $this->assertStringContainsString("NOTES :\n1. ", $notesText);
        $this->assertStringContainsString('Results of examination specifically related', $notesText);
        $this->assertStringContainsString('Laboratory Head', $notesText);

        $approved = $revision->fields->firstWhere('name', 'prox_f016_approved_by_name');
        $this->assertStringContainsString(
            'Wendy A. Tayson',
            (string) (($approved?->options['static_text'] ?? '')),
        );

        $meta = $revision->fields->firstWhere('name', 'prox_f016_form_meta');
        $metaText = (string) (($meta?->options['static_text'] ?? ''));
        $this->assertStringContainsString('NPPC ADL', $metaText);
        $this->assertStringContainsString('LSP 7.8 F016', $metaText);
        $this->assertStringContainsString('Rev 05 / Issue 06', $metaText);
        $this->assertStringContainsString('07/15/2026', $metaText);

        $this->assertSame(1, $form->revisions()->count());
    }
}
