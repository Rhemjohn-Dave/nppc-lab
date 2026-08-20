<?php

namespace App\Services;

use App\Enums\ControlledFormRevisionStatus;
use App\Exceptions\ObsoleteFormRevisionException;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\DocumentApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevisionWorkflow
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['for_review', 'for_approval', 'approved', 'active', 'archived'],
        'for_review' => ['draft', 'for_approval', 'archived'],
        'for_approval' => ['for_review', 'approved', 'archived'],
        'approved' => ['active', 'archived'],
        'active' => ['archived'],
        'superseded' => ['archived'],
        'archived' => [],
    ];

    public function transition(
        ControlledFormRevision $revision,
        ControlledFormRevisionStatus $to,
        User $user,
        ?string $comment = null,
    ): ControlledFormRevision {
        $from = $revision->status;
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true) && ! ($from === $to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move revision {$revision->revision} from {$from->label()} to {$to->label()}.",
            ]);
        }

        return DB::transaction(function () use ($revision, $from, $to, $user, $comment) {
            if ($to === ControlledFormRevisionStatus::Active) {
                $this->activate($revision, $user);
            } else {
                $revision->status = $to;
                if ($to === ControlledFormRevisionStatus::Approved) {
                    $revision->approved_by = $user->id;
                    $revision->approved_at = now();
                }
                $revision->save();
            }

            DocumentApproval::query()->create([
                'controlled_form_revision_id' => $revision->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $user->id,
                'comment' => $comment,
            ]);

            app(DocumentAuditLogger::class)->record(
                'form.'.$to->value,
                $revision,
                $user,
                ['status' => $from->value],
                ['status' => $to->value],
            );

            return $revision->fresh() ?? $revision;
        });
    }

    public function activate(ControlledFormRevision $revision, User $user): ControlledFormRevision
    {
        if (! $revision->hasCanonicalPdf()) {
            throw ValidationException::withMessages([
                'status' => 'Upload a canonical PDF before activating this revision.',
            ]);
        }

        $form = $revision->form;

        $form->revisions()
            ->where('id', '!=', $revision->id)
            ->where('status', ControlledFormRevisionStatus::Active)
            ->get()
            ->each(function (ControlledFormRevision $previous) use ($user): void {
                $previous->status = ControlledFormRevisionStatus::Superseded;
                $previous->save();

                DocumentApproval::query()->create([
                    'controlled_form_revision_id' => $previous->id,
                    'from_status' => ControlledFormRevisionStatus::Active->value,
                    'to_status' => ControlledFormRevisionStatus::Superseded->value,
                    'user_id' => $user->id,
                    'comment' => 'Superseded by a newer active revision.',
                ]);

                app(DocumentAuditLogger::class)->record(
                    'form.superseded',
                    $previous,
                    $user,
                    ['status' => ControlledFormRevisionStatus::Active->value],
                    ['status' => ControlledFormRevisionStatus::Superseded->value],
                );
            });

        $revision->status = ControlledFormRevisionStatus::Active;
        $revision->approved_by ??= $user->id;
        $revision->approved_at ??= now();
        $revision->save();

        $form->current_revision_id = $revision->id;
        $form->save();

        return $revision;
    }

    public function assertCanGenerate(?ControlledFormRevision $revision, ControlledForm $form): ControlledFormRevision
    {
        $active = $form->activeRevision();

        if (! $revision) {
            if (! $active) {
                throw ValidationException::withMessages([
                    'revision' => 'This controlled form has no active revision.',
                ]);
            }

            return $active;
        }

        if ($revision->status !== ControlledFormRevisionStatus::Active) {
            throw new ObsoleteFormRevisionException($form, $revision, $active);
        }

        return $revision;
    }
}
