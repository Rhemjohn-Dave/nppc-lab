<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_packages', function (Blueprint $table) {
            $table->foreignId('signatory_user_id')
                ->nullable()
                ->after('form_code')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signatory_user_id');
        });
    }
};
