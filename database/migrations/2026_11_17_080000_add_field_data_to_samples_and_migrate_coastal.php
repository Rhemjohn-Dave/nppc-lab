<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coastal FIELD DATA moves from JO-wide job_orders.field_data onto samples.field_data
 * so multi-sample JOs can store different coastal answers per sample.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->text('field_data')->nullable()->after('remarks');
        });

        $jobs = DB::table('job_orders')
            ->whereNotNull('field_data')
            ->where('field_data', '!=', '')
            ->get(['id', 'field_data']);

        foreach ($jobs as $job) {
            $raw = trim((string) $job->field_data);
            if ($raw === '') {
                continue;
            }

            $coastalSampleIds = DB::table('samples')
                ->where('job_order_id', $job->id)
                ->where('description', 'Coastal Water')
                ->where(function ($query) {
                    $query->whereNull('field_data')->orWhere('field_data', '');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');

            if ($coastalSampleIds->isEmpty()) {
                continue;
            }

            // Copy JO blob onto every empty Coastal sample (same historical JO-wide value).
            DB::table('samples')
                ->whereIn('id', $coastalSampleIds->all())
                ->update(['field_data' => $raw]);
        }
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropColumn('field_data');
        });
    }
};
