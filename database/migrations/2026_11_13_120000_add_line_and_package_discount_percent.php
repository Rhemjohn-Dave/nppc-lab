<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('job_order_analyses', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
            }
        });

        Schema::table('job_order_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('job_order_packages', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('waived_type_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('job_order_analyses', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });

        Schema::table('job_order_packages', function (Blueprint $table) {
            if (Schema::hasColumn('job_order_packages', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }
};
