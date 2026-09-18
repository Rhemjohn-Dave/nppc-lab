<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->json('result_signatories')->nullable()->after('review_notes');
        });

        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->unsignedTinyInteger('analyst_signatory_slots')->default(1)->after('analysis_package_id');
            $table->boolean('analyst_require_prc')->default(false)->after('analyst_signatory_slots');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('result_signatories');
        });

        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->dropColumn(['analyst_signatory_slots', 'analyst_require_prc']);
        });
    }
};
