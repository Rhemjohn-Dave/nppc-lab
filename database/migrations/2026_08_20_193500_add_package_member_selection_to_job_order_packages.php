<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->json('selected_type_ids')->nullable()->after('analysis_package_id');
            $table->json('waived_type_ids')->nullable()->after('selected_type_ids');
        });
    }

    public function down(): void
    {
        Schema::table('job_order_packages', function (Blueprint $table) {
            $table->dropColumn(['selected_type_ids', 'waived_type_ids']);
        });
    }
};
