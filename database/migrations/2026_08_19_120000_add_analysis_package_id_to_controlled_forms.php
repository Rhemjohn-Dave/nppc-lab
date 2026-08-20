<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->foreignId('analysis_package_id')
                ->nullable()
                ->after('combination_key')
                ->constrained('analysis_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_package_id');
        });
    }
};
