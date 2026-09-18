<?php

use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['MB-02A', 'MB-02B'] as $code) {
            $method = OfficialAnalysisCatalog::methodForCode($code);
            if ($method === null) {
                continue;
            }

            DB::table('analysis_types')
                ->where('code', $code)
                ->where(function ($query) {
                    $query->whereNull('method')->orWhere('method', '');
                })
                ->update(['method' => $method]);
        }
    }

    public function down(): void
    {
        // Keep seeded procedure methods; do not blank analyst-edited values.
    }
};
