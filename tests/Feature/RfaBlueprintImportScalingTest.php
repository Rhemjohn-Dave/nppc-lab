<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Services\ControlledFormService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RfaBlueprintImportScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_blueprint_scales_coordinates_to_target_page_size(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::query()->where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', ControlledForm::RFA_FORM_CODE)->firstOrFail();

        // Target dimensions intentionally differ from blueprint dimensions.
        $targetWidth = 230.0;
        $targetHeight = 340.0;

        Storage::disk('local')->put('tmp/canonical.pdf', '%PDF-1.4');

        $revision = ControlledFormRevision::query()->create([
            'controlled_form_id' => $form->id,
            'revision' => '01',
            'status' => 'draft',
            'canonical_pdf_path' => 'tmp/canonical.pdf',
            'page_width_mm' => $targetWidth,
            'page_height_mm' => $targetHeight,
            'fill_mode' => 'overlay',
            'page_count' => 1,
            'created_by' => $admin->id,
        ]);

        app(ControlledFormService::class)->importRfaBlueprint($revision);

        $field = $revision->fields()->where('name', 'reference_no')->firstOrFail();

        $blueprintPage = config('rfa_form_fields.page');
        $blueprintWidth = (float) ($blueprintPage['width'] ?? 215.9);
        $blueprintHeight = (float) ($blueprintPage['height'] ?? 355.6);
        $blueprintField = collect(config('rfa_form_fields.fields', []))
            ->first(fn (array $row): bool => ($row['name'] ?? '') === 'reference_no');
        $this->assertIsArray($blueprintField);

        $scaleX = $targetWidth / $blueprintWidth;
        $scaleY = $targetHeight / $blueprintHeight;

        $expectedX = round(((float) $blueprintField['x']) * $scaleX, 3);
        $expectedY = round(((float) $blueprintField['y']) * $scaleY, 3);
        $expectedW = round(((float) $blueprintField['w']) * $scaleX, 3);
        $expectedH = round(((float) $blueprintField['h']) * $scaleY, 3);

        $this->assertEquals($expectedX, round((float) $field->x, 3));
        $this->assertEquals($expectedY, round((float) $field->y, 3));
        $this->assertEquals($expectedW, round((float) $field->width, 3));
        $this->assertEquals($expectedH, round((float) $field->height, 3));
    }
}
