<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->string('result_mode', 20)->default('value')->after('show_on_kiosk');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropColumn('result_mode');
        });
    }
};
