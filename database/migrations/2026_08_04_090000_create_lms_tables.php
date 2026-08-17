<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->decimal('default_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('analysis_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'analysis_type_id']);
        });

        Schema::create('reference_counters', function (Blueprint $table) {
            $table->id();
            $table->string('year', 2)->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_contact')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('company_name')->nullable();
            $table->string('classification')->nullable();
            $table->text('field_data')->nullable();
            $table->string('other_tests')->nullable();
            $table->string('status');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
            $table->string('sample_code')->nullable();
            $table->string('description');
            $table->string('matrix')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('job_order_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('status');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result_value')->nullable();
            $table->string('result_unit')->nullable();
            $table->text('result_remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('job_order_analyses');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('job_orders');
        Schema::dropIfExists('reference_counters');
        Schema::dropIfExists('analysis_assignments');
        Schema::dropIfExists('analysis_types');
    }
};
