<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->foreignId('sample_id')
                ->nullable()
                ->after('job_order_id')
                ->constrained('samples')
                ->nullOnDelete();
        });

        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->foreignId('sample_id')
                ->nullable()
                ->after('job_order_id')
                ->constrained('samples')
                ->nullOnDelete();
        });

        // Backfill: attach legacy JO-level rows to the first sample on each job.
        $firstSamples = DB::table('samples')
            ->select('id', 'job_order_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique('job_order_id')
            ->keyBy('job_order_id');

        foreach ($firstSamples as $jobOrderId => $sample) {
            DB::table('job_order_analyses')
                ->where('job_order_id', $jobOrderId)
                ->whereNull('sample_id')
                ->update(['sample_id' => $sample->id]);

            DB::table('job_order_packages')
                ->where('job_order_id', $jobOrderId)
                ->whereNull('sample_id')
                ->update(['sample_id' => $sample->id]);
        }

        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->dropUnique(['job_order_id', 'analysis_package_id']);
            $table->unique(
                ['job_order_id', 'sample_id', 'analysis_package_id'],
                'jop_job_sample_package_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->dropUnique('jop_job_sample_package_unique');
            $table->unique(['job_order_id', 'analysis_package_id']);
        });

        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sample_id');
        });

        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sample_id');
        });
    }
};
