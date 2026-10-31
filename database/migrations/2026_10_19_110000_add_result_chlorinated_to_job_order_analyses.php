<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->string('result_chlorinated', 255)->nullable()->after('result_finish');
            $table->string('result_remarks_chlorinated', 1000)->nullable()->after('result_remarks_finish');
        });
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropColumn(['result_chlorinated', 'result_remarks_chlorinated']);
        });
    }
};
