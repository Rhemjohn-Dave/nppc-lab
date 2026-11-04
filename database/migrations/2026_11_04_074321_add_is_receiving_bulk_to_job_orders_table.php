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
            $table->boolean('is_receiving_bulk')->default(false)->after('is_bulk_draft');
        });

        DB::table('job_orders')
            ->where(function ($query) {
                $query->where('is_bulk_draft', true)
                    ->orWhere('see_attached_file', true)
                    ->orWhereNotNull('attachment_path');
            })
            ->update(['is_receiving_bulk' => true]);
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('is_receiving_bulk');
        });
    }
};
