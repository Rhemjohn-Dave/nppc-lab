<?php

use App\Models\AnalysisType;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->string('result_pass_fail', 20)->nullable()->after('result_value');
            $table->text('result_method')->nullable()->after('result_remarks');
        });

        $types = AnalysisType::query()
            ->get(['id', 'code', 'method'])
            ->keyBy('id');

        DB::table('job_order_analyses')->orderBy('id')->chunkById(100, function ($rows) use ($types) {
            foreach ($rows as $row) {
                $updates = [];
                $value = trim((string) ($row->result_value ?? ''));
                $measurement = trim((string) ($row->result_measurement ?? ''));

                if (in_array($value, ['Passed', 'Failed'], true)) {
                    $updates['result_pass_fail'] = $value;
                    if ($measurement !== '') {
                        $updates['result_value'] = $measurement;
                        $updates['result_measurement'] = null;
                    } else {
                        $updates['result_value'] = null;
                    }
                }

                $method = trim((string) ($row->result_method ?? ''));
                if ($method === '') {
                    $type = $types->get((int) $row->analysis_type_id);
                    $fromType = $type ? trim((string) ($type->method ?? '')) : '';
                    if ($fromType === '' && $type) {
                        $fromType = trim((string) (OfficialAnalysisCatalog::methodForCode($type->code) ?? ''));
                    }
                    if ($fromType !== '') {
                        $updates['result_method'] = $fromType;
                    }
                }

                if ($updates !== []) {
                    DB::table('job_order_analyses')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropColumn(['result_pass_fail', 'result_method']);
        });
    }
};
