<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('sampling_site')->nullable()->after('sampling_point');
            $table->string('payment_mode', 40)->nullable()->after('sampling_site');
            $table->string('payment_terms', 40)->nullable()->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn(['sampling_site', 'payment_mode', 'payment_terms']);
        });
    }
};
