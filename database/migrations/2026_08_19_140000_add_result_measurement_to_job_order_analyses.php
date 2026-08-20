<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->string('result_measurement')->nullable()->after('result_value');
        });
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropColumn('result_measurement');
        });
    }
};
