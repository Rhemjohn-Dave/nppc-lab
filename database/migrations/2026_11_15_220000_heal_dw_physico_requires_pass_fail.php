<?php

use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enable Requires pass/fail on DW physico procedures (FO3/FO37) and promote
 * historical Remarks-shortcut Passed/Failed values into result_pass_fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('analysis_types', 'requires_pass_fail')) {
            return;
        }

        $codes = OfficialAnalysisCatalog::drinkingWaterPhysicoPassFailCodes();

        DB::table('analysis_types')
            ->whereIn('code', $codes)
            ->update(['requires_pass_fail' => true]);

        if (! Schema::hasTable('job_order_analyses')) {
            return;
        }

        $typeIds = DB::table('analysis_types')
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();

        if ($typeIds === []) {
            return;
        }

        $query = DB::table('job_order_analyses')
            ->whereIn('analysis_type_id', $typeIds)
            ->where(function ($q): void {
                $q->whereNull('result_pass_fail')
                    ->orWhere('result_pass_fail', '');
            });

        $remarkColumns = ['result_remarks'];
        if (Schema::hasColumn('job_order_analyses', 'result_remarks_raw')) {
            $remarkColumns[] = 'result_remarks_raw';
        }
        if (Schema::hasColumn('job_order_analyses', 'result_remarks_finish')) {
            $remarkColumns[] = 'result_remarks_finish';
        }
        if (Schema::hasColumn('job_order_analyses', 'result_remarks_chlorinated')) {
            $remarkColumns[] = 'result_remarks_chlorinated';
        }

        foreach (['Passed', 'Failed'] as $outcome) {
            $match = clone $query;
            $match->where(function ($q) use ($remarkColumns, $outcome): void {
                foreach ($remarkColumns as $i => $column) {
                    if ($i === 0) {
                        $q->where($column, $outcome);
                    } else {
                        $q->orWhere($column, $outcome);
                    }
                }
            })->update(['result_pass_fail' => $outcome]);
        }
    }

    public function down(): void
    {
        // Non-destructive: do not clear analyst Pass/Fail data or toggle flags off.
    }
};
