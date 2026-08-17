<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('ownership_type')->nullable()->after('company_name');
            $table->date('sampling_date')->nullable()->after('classification');
            $table->string('sampling_time')->nullable()->after('sampling_date');
            $table->string('sample_collected_by')->nullable()->after('sampling_time');
            $table->string('sample_storage_temp')->nullable()->after('field_data');
            $table->string('wastewater_source')->nullable()->after('sample_storage_temp');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn([
                'ownership_type',
                'sampling_date',
                'sampling_time',
                'sample_collected_by',
                'sample_storage_temp',
                'wastewater_source',
            ]);
        });
    }
};
