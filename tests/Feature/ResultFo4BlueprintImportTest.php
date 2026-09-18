<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledFormService;
use Database\Seeders\ControlledFormDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultFo4BlueprintImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_fo4_blueprint_fields(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();
        $revision = $form->revisions()
            ->where('revision', ControlledFormDefaultsSeeder::DEFAULT_REVISION)
            ->firstOrFail();

        $blueprint = config('result_fo4_form_fields');
        $expectedCustomerX = (float) collect($blueprint['fields'])
            ->firstWhere('name', 'results.customer')['x'];

        $this->assertSame(count($blueprint['fields']), $revision->fields()->count());
        $this->assertTrue(
            $revision->fields()->where('data_source_key', 'test_1_measurement')->exists(),
        );
        $this->assertTrue(
            $revision->fields()->where('data_source_key', 'test_2_measurement')->exists(),
        );
        $this->assertEqualsWithDelta(
            $expectedCustomerX,
            (float) $revision->fields()->where('name', 'results.customer')->value('x'),
            0.01,
        );
    }

    public function test_import_blueprint_scales_fo4_coordinates_to_target_page_size(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::query()->where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();

        $targetWidth = 210.0;
        $targetHeight = 297.0;

        Storage::disk('local')->put('tmp/fo4-canonical.pdf', '%PDF-1.4');

        $revision = ControlledFormRevision::query()->create([
            'controlled_form_id' => $form->id,
            'revision' => '99',
            'status' => 'draft',
            'canonical_pdf_path' => 'tmp/fo4-canonical.pdf',
            'page_width_mm' => $targetWidth,
            'page_height_mm' => $targetHeight,
            'fill_mode' => 'overlay',
            'page_count' => 1,
            'created_by' => $admin->id,
        ]);

        app(ControlledFormService::class)->importBlueprint($revision);

        $field = $revision->fields()->where('name', 'results.customer')->firstOrFail();

        $blueprint = config('result_fo4_form_fields');
        $blueprintWidth = (float) $blueprint['page']['width'];
        $blueprintHeight = (float) $blueprint['page']['height'];
        $customer = collect($blueprint['fields'])->firstWhere('name', 'results.customer');
        $scaleX = $targetWidth / $blueprintWidth;
        $scaleY = $targetHeight / $blueprintHeight;

        $this->assertEqualsWithDelta(((float) $customer['x']) * $scaleX, (float) $field->x, 0.01);
        $this->assertEqualsWithDelta(((float) $customer['y']) * $scaleY, (float) $field->y, 0.01);
        $this->assertSame('results.customer', $field->data_source_key);
        $this->assertSame('Customer', $field->label);
    }

    public function test_seeder_imports_fo5_blueprint_fields(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $revision = $form->revisions()
            ->where('revision', ControlledFormDefaultsSeeder::DEFAULT_REVISION)
            ->firstOrFail();

        $blueprint = config('result_fo5_form_fields');
        $expectedCustomerX = (float) collect($blueprint['fields'])
            ->firstWhere('name', 'results.customer')['x'];

        $this->assertSame(count($blueprint['fields']), $revision->fields()->count());
        $this->assertTrue(
            $revision->fields()->where('data_source_key', 'test_3_result')->exists(),
        );
        $this->assertTrue(
            $revision->fields()->where('data_source_key', 'test_1_measurement')->exists(),
        );
        $this->assertTrue(
            $revision->fields()->where('data_source_key', 'test_3_measurement')->exists(),
        );
        $this->assertTrue(
            $revision->fields()->where('data_source_key', 'results.water_supply')->exists(),
        );
        $this->assertEqualsWithDelta(
            $expectedCustomerX,
            (float) $revision->fields()->where('name', 'results.customer')->value('x'),
            0.01,
        );
    }
}
