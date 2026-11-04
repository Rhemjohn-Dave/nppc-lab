<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->timestamp('submitted_for_review_at')->nullable()->after('sort_order');
            $table->timestamp('released_at')->nullable()->after('submitted_for_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropColumn(['submitted_for_review_at', 'released_at']);
        });
    }
};
