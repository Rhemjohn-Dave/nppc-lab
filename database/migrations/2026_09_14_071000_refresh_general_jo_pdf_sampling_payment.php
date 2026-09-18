<?php

use App\Models\ControlledForm;
use App\Models\User;
use App\Services\ControlledFormService;
use App\Services\RevisionWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->first();

        if (! $form) {
            return;
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->first()
            ?? User::query()->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        $absolute = resource_path('forms/official/JOB ORDER Issue 11 09012026.pdf');
        if (! is_file($absolute)) {
            return;
        }

        $forms = app(ControlledFormService::class);
        $upload = new UploadedFile(
            $absolute,
            'JOB ORDER Issue 11 09012026.pdf',
            'application/pdf',
            null,
            true,
        );

        $revision = $forms->createRevision(
            $form,
            [
                'notes' => 'Issue 11 PDF with sampling site + payment mode/terms overlays.',
                'blank_fields' => true,
            ],
            $admin,
            $upload,
        );

        $forms->importBlueprint($revision->fresh());
        app(RevisionWorkflow::class)->activate($revision->fresh(), $admin);
    }

    public function down(): void
    {
        // Keep new revision — irreversible heal.
    }
};
