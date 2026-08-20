<?php

namespace App\Exceptions;

use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ObsoleteFormRevisionException extends HttpException
{
    public function __construct(
        public readonly ControlledForm $form,
        public readonly ControlledFormRevision $selected,
        public readonly ?ControlledFormRevision $active,
    ) {
        $activeRev = $active?->revision ?? 'none';

        parent::__construct(
            409,
            "CONTROLLED DOCUMENT WARNING\n\nThis form revision is no longer active.\n\nForm:\n{$form->form_code}\n\nSelected Revision:\n{$selected->revision}\n\nCurrent Active Revision:\n{$activeRev}\n\nPlease use the current approved revision.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'warning' => 'CONTROLLED DOCUMENT WARNING',
            'message' => 'This form revision is no longer active.',
            'form_code' => $this->form->form_code,
            'form_name' => $this->form->name,
            'selected_revision' => $this->selected->revision,
            'active_revision' => $this->active?->revision,
        ];
    }
}
