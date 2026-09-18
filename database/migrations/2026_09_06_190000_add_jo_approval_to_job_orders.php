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
            $table->foreignId('jo_approved_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->timestamp('jo_approved_at')->nullable()->after('jo_approved_by');
        });

        // Legacy "priced" jobs were waiting for receive; they now wait for Head JO approval.
        DB::table('job_orders')
            ->where('status', 'priced')
            ->update(['status' => 'pending_jo_approval']);
    }

    public function down(): void
    {
        DB::table('job_orders')
            ->where('status', 'pending_jo_approval')
            ->whereNull('jo_approved_at')
            ->update(['status' => 'priced']);

        DB::table('job_orders')
            ->where('status', 'jo_approved')
            ->update(['status' => 'priced']);

        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jo_approved_by');
            $table->dropColumn('jo_approved_at');
        });
    }
};
