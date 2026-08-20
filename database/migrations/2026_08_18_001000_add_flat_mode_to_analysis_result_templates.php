<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_result_templates', function (Blueprint $table) {
            $table->string('fill_mode', 16)->default('named')->after('combination_key');
            $table->json('field_map')->nullable()->after('field_names');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_result_templates', function (Blueprint $table) {
            $table->dropColumn(['fill_mode', 'field_map']);
        });
    }
};
