<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('analysis_types', 'requires_pass_fail')) {
            return;
        }

        Schema::table('analysis_types', function (Blueprint $table) {
            $table->boolean('requires_pass_fail')->default(false)->after('result_mode');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropColumn('requires_pass_fail');
        });
    }
};
