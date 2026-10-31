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
            $table->string('result_remarks_raw', 1000)->nullable()->after('result_remarks');
            $table->string('result_remarks_finish', 1000)->nullable()->after('result_remarks_raw');
        });

        // Existing TDS remarks become the Raw remarks cell.
        if (Schema::hasColumn('job_order_analyses', 'result_raw')) {
            DB::table('job_order_analyses')
                ->whereNotNull('result_remarks')
                ->where('result_remarks', '!=', '')
                ->where(function ($query): void {
                    $query->whereNotNull('result_raw')
                        ->orWhereNotNull('result_finish');
                })
                ->update([
                    'result_remarks_raw' => DB::raw('result_remarks'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropColumn(['result_remarks_raw', 'result_remarks_finish']);
        });
    }
};
