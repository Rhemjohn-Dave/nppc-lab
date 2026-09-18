<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)
                ->default(0)
                ->after('discount_amount');
        });

        $jobs = DB::table('job_orders')
            ->select('id', 'discount_amount')
            ->where('discount_amount', '>', 0)
            ->get();

        foreach ($jobs as $job) {
            $subtotal = (float) DB::table('job_order_analyses')
                ->where('job_order_id', $job->id)
                ->sum('total_cost');

            if ($subtotal <= 0) {
                continue;
            }

            $percent = min(100, round(((float) $job->discount_amount / $subtotal) * 100, 2));

            DB::table('job_orders')
                ->where('id', $job->id)
                ->update(['discount_percent' => $percent]);
        }
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
    }
};
