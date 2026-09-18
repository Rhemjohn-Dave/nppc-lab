<?php

namespace Tests\Feature;

use App\Console\Commands\ExportControlledFormBlueprintsCommand;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\ControlledForm;
use App\Models\User;
use Database\Seeders\ControlledFormDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledFormDefaultsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_seeder_leaves_sole_active_revision_one_with_fields_and_pdf(): void
    {
        $this->seed();

        $codes = array_keys(ExportControlledFormBlueprintsCommand::TARGETS);

        foreach ($codes as $formCode) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            $this->assertNotNull($form, "Missing form {$formCode}");

            $this->assertSame(
                1,
                $form->revisions()->count(),
                "{$formCode} should have exactly one revision",
            );

            $revision = $form->revisions()->first();
            $this->assertNotNull($revision);
            $this->assertSame(ControlledFormDefaultsSeeder::DEFAULT_REVISION, $revision->revision);
            $this->assertSame(ControlledFormRevisionStatus::Active, $revision->status);
            $this->assertTrue($revision->fields()->exists(), "{$formCode} revision 1 should have fields");
            $this->assertTrue($revision->hasCanonicalPdf(), "{$formCode} revision 1 should have PDF");
            $this->assertSame((int) $revision->id, (int) $form->current_revision_id);
        }
    }

    public function test_defaults_seeder_is_idempotent_and_purges_extra_revisions(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();

        $form->revisions()->create([
            'revision' => '99',
            'status' => ControlledFormRevisionStatus::Draft,
            'created_by' => $admin->id,
            'fill_mode' => 'overlay',
            'notes' => 'extra revision to purge',
        ]);

        $this->assertSame(2, $form->revisions()->count());

        $this->seed(ControlledFormDefaultsSeeder::class);

        $form->refresh();
        $this->assertSame(1, $form->revisions()->count());
        $this->assertSame('1', $form->revisions()->first()?->revision);
        $this->assertSame(ControlledFormRevisionStatus::Active, $form->revisions()->first()?->status);
    }

    public function test_defaults_seeder_does_not_overwrite_milk_designer_canonical_pdf(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();
        $this->assertTrue($revision->hasCanonicalPdf());

        $pathBefore = $revision->canonical_pdf_path;
        $shaBefore = $revision->sha256;
        $this->assertNotEmpty($pathBefore);

        // Simulate a distinct Designer upload fingerprint.
        $revision->notes = 'designer-upload-marker';
        $revision->sha256 = 'designer-fake-sha-should-survive-seed';
        $revision->save();

        $this->seed(ControlledFormDefaultsSeeder::class);

        $revision->refresh();
        $this->assertSame($pathBefore, $revision->canonical_pdf_path);
        $this->assertSame('designer-fake-sha-should-survive-seed', $revision->sha256);
        $this->assertSame('designer-upload-marker', $revision->notes);
        $this->assertNotSame($shaBefore, $revision->sha256);
        $this->assertTrue($revision->hasCanonicalPdf());
        $this->assertSame(ControlledFormRevisionStatus::Active, $revision->status);
    }
}
