<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->string('accreditation_footnote', 8)->nullable()->after('result_method');
        });
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropColumn('accreditation_footnote');
        });
    }
};
