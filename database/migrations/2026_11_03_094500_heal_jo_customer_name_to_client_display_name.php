<?php

use App\Models\ControlledForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * JO top Customer Name should prefer company (else requestor).
 * Conforme stays on job_orders.customer_name (person who delivered samples).
 */
return new class extends Migration
{
    public function up(): void
    {
        $formIds = ControlledForm::query()
            ->whereIn('form_code', [
                ControlledForm::RFA_FORM_CODE,
                ControlledForm::RFA_AQUA_FORM_CODE,
            ])
            ->pluck('id');

        if ($formIds->isEmpty()) {
            return;
        }

        $revisionIds = DB::table('controlled_form_revisions')
            ->whereIn('controlled_form_id', $formIds)
            ->pluck('id');

        if ($revisionIds->isEmpty()) {
            return;
        }

        DB::table('controlled_form_fields')
            ->whereIn('controlled_form_revision_id', $revisionIds)
            ->where('name', 'customer_name')
            ->where('data_source_key', 'job_orders.customer_name')
            ->update(['data_source_key' => 'job_orders.client_display_name']);
    }

    public function down(): void
    {
        $formIds = ControlledForm::query()
            ->whereIn('form_code', [
                ControlledForm::RFA_FORM_CODE,
                ControlledForm::RFA_AQUA_FORM_CODE,
            ])
            ->pluck('id');

        if ($formIds->isEmpty()) {
            return;
        }

        $revisionIds = DB::table('controlled_form_revisions')
            ->whereIn('controlled_form_id', $formIds)
            ->pluck('id');

        if ($revisionIds->isEmpty()) {
            return;
        }

        DB::table('controlled_form_fields')
            ->whereIn('controlled_form_revision_id', $revisionIds)
            ->where('name', 'customer_name')
            ->where('data_source_key', 'job_orders.client_display_name')
            ->update(['data_source_key' => 'job_orders.customer_name']);
    }
};
