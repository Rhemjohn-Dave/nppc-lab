<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fo27LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_fo27_blueprint_deploys_methods_notes_and_footer_chrome(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO27')->firstOrFail();
        $this->assertSame(1, (int) $form->analyst_signatory_slots);
        $this->assertFalse((bool) $form->analyst_require_prc);

        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertSame('1', $revision->revision);
        $revision->load('fields');

        $names = $revision->fields->pluck('name')->all();
        $this->assertContains('food_micro_sugar_fo27_matrix', $names);
        $this->assertContains('results.test_methods_references', $names);
        $this->assertContains('fo27_notes', $names);
        $this->assertContains('fo27_label_analyzed_by', $names);
        $this->assertContains('fo27_label_approved_by', $names);
        $this->assertContains('fo27_approved_by_name', $names);
        $this->assertContains('fo27_form_meta', $names);
        $this->assertContains('results.analyst_name', $names);
        $this->assertNotContains('results.analyst_prc', $names);

        $matrix = $revision->fields->firstWhere('name', 'food_micro_sugar_fo27_matrix');
        $this->assertFalse((bool) (($matrix?->table_config['stretch_body'] ?? true)));

        $methods = $revision->fields->firstWhere('name', 'results.test_methods_references');
        $this->assertTrue((bool) (($methods?->options['cover'] ?? false)));
        $this->assertTrue((bool) (($methods?->options['prefer_static'] ?? false)));
        $this->assertNull($methods?->data_source_key);
        $methodsText = (string) (($methods?->options['static_text'] ?? ''));
        $this->assertStringContainsString('**Test Methods and References:**', $methodsText);
        $this->assertStringContainsString('Mesophilic Bacteria Count', $methodsText);
        $this->assertStringContainsString('Yeast & Molds', $methodsText);

        $notes = $revision->fields->firstWhere('name', 'fo27_notes');
        $this->assertSame('numbered', $notes?->options['list_style'] ?? null);
        $this->assertStringContainsString(
            'Result of examination specifically related',
            (string) (($notes?->options['static_text'] ?? '')),
        );

        $analyst = $revision->fields->firstWhere('name', 'results.analyst_name');
        $this->assertSame(
            'Riz Mae Fernandez',
            (string) (($analyst?->options['static_text'] ?? '')),
        );

        $approved = $revision->fields->firstWhere('name', 'fo27_approved_by_name');
        $this->assertStringContainsString(
            'Wendy A. Tayson',
            (string) (($approved?->options['static_text'] ?? '')),
        );

        $meta = $revision->fields->firstWhere('name', 'fo27_form_meta');
        $metaText = (string) (($meta?->options['static_text'] ?? ''));
        $this->assertStringContainsString('NPPC-ADL LSP 7.8 FO27', $metaText);
        $this->assertStringContainsString('Rev.03/Issue04', $metaText);
        $this->assertStringContainsString('07/15/2026', $metaText);

        $this->assertSame(1, $form->revisions()->count());
    }
}
