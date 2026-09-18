<?php

use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->string('method', 255)->nullable()->after('name');
        });

        foreach (['PX-01', 'PX-02', 'PX-03', 'PX-04', 'PX-05', 'PX-06', 'PX-07', 'PX-08'] as $code) {
            $method = OfficialAnalysisCatalog::methodForCode($code);
            if ($method === null) {
                continue;
            }

            DB::table('analysis_types')
                ->where('code', $code)
                ->update(['method' => $method]);
        }
    }

    public function down(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
