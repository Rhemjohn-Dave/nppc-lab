<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use Database\Seeders\ControlledFormDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilkFormBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_milk_f016_blueprint_with_matrix(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->firstOrFail();
        $revision = $form->revisions()
            ->where('revision', ControlledFormDefaultsSeeder::DEFAULT_REVISION)
            ->firstOrFail();

        $blueprint = config('result_milk_form_fields');
        $this->assertSame(count($blueprint['fields']), $revision->fields()->count());
        $this->assertTrue(
            $revision->fields()->where('name', 'milk_f016_matrix')->exists(),
        );
        $this->assertTrue($revision->hasCanonicalPdf());
    }
}
