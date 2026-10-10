<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Each step is guarded: MySQL DDL is not transactional, so a failed earlier run can leave partial changes.
    public function up(): void
    {
        foreach (['job_order_analyses', 'job_order_packages'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'sample_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('sample_id')->nullable()->after('job_order_id');
                });
            }

            if (! $this->hasForeignKeyOn($tableName, 'sample_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('sample_id')->references('id')->on('samples')->nullOnDelete();
                });
            }
        }

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

        // Add the new unique first so MySQL still has an index covering the job_order_id foreign key.
        if (! Schema::hasIndex('job_order_packages', 'jop_job_sample_package_unique')) {
            Schema::table('job_order_packages', function (Blueprint $table) {
                $table->unique(
                    ['job_order_id', 'sample_id', 'analysis_package_id'],
                    'jop_job_sample_package_unique',
                );
            });
        }

        if (Schema::hasIndex('job_order_packages', ['job_order_id', 'analysis_package_id'], 'unique')) {
            Schema::table('job_order_packages', function (Blueprint $table) {
                $table->dropUnique(['job_order_id', 'analysis_package_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->unique(['job_order_id', 'analysis_package_id']);
        });

        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->dropUnique('jop_job_sample_package_unique');
        });

        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sample_id');
        });

        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sample_id');
        });
    }

    private function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                return true;
            }
        }

        return false;
    }
};
