<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->string('job_order_variant')->nullable()->after('category')->index();
        });
    }

    public function down(): void
    {
        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->dropColumn('job_order_variant');
        });
    }
};
