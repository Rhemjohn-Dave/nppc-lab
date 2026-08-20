<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_forms', function (Blueprint $table) {
            $table->id();
            $table->string('form_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('department')->nullable();
            $table->string('category');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->string('combination_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('controlled_form_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controlled_form_id')->constrained()->restrictOnDelete();
            $table->string('revision');
            $table->string('status');
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('original_name')->nullable();
            $table->string('original_path')->nullable();
            $table->string('canonical_pdf_path')->nullable();
            $table->string('original_mime')->nullable();
            $table->unsignedInteger('page_count')->default(1);
            $table->decimal('page_width_mm', 8, 2)->nullable();
            $table->decimal('page_height_mm', 8, 2)->nullable();
            $table->string('fill_mode')->default('overlay');
            $table->string('sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['controlled_form_id', 'revision']);
            $table->index(['controlled_form_id', 'status']);
        });

        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->foreign('current_revision_id')
                ->references('id')
                ->on('controlled_form_revisions')
                ->nullOnDelete();
        });

        Schema::create('controlled_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controlled_form_revision_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('field_type');
            $table->unsignedInteger('page_number')->default(1);
            $table->decimal('x', 10, 3)->default(0);
            $table->decimal('y', 10, 3)->default(0);
            $table->decimal('width', 10, 3)->default(20);
            $table->decimal('height', 10, 3)->default(5);
            $table->decimal('font_size', 6, 2)->nullable();
            $table->string('font_family')->nullable();
            $table->string('font_color', 20)->nullable();
            $table->string('alignment', 10)->nullable();
            $table->string('data_source_key')->nullable();
            $table->string('format')->nullable();
            $table->string('checkbox_true_value')->nullable();
            $table->json('options')->nullable();
            $table->json('table_config')->nullable();
            $table->unsignedInteger('z_order')->default(0);
            $table->timestamps();
        });

        Schema::create('controlled_form_binding_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controlled_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_type_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->timestamps();

            $table->unique(['controlled_form_id', 'slot']);
            $table->unique(['controlled_form_id', 'analysis_type_id']);
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->foreignId('controlled_form_id')->constrained()->restrictOnDelete();
            $table->foreignId('controlled_form_revision_id')->constrained()->restrictOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->string('pdf_path');
            $table->string('status');
            $table->string('sha256', 64)->nullable();
            $table->string('template_sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at');
            $table->unsignedInteger('number_of_copies')->default(1);
            $table->string('printer_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('document_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controlled_form_revision_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('document_number_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_counters');
        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('document_audit_logs');
        Schema::dropIfExists('print_logs');
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('controlled_form_binding_types');
        Schema::dropIfExists('controlled_form_fields');
        Schema::table('controlled_forms', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });
        Schema::dropIfExists('controlled_form_revisions');
        Schema::dropIfExists('controlled_forms');
    }
};
