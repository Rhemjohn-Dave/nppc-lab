<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_result_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('revision')->nullable();
            $table->string('notes')->nullable();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('combination_key');
            $table->json('field_names')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('combination_key');
        });

        Schema::create('analysis_result_template_types', function (Blueprint $table) {
            $table->id();
            // Explicit short FK names — MySQL limits identifiers to 64 chars.
            $table->unsignedBigInteger('analysis_result_template_id');
            $table->unsignedBigInteger('analysis_type_id');
            $table->unsignedTinyInteger('slot');
            $table->timestamps();

            $table->foreign('analysis_result_template_id', 'artt_template_id_foreign')
                ->references('id')
                ->on('analysis_result_templates')
                ->cascadeOnDelete();
            $table->foreign('analysis_type_id', 'artt_type_id_foreign')
                ->references('id')
                ->on('analysis_types')
                ->restrictOnDelete();

            $table->unique(['analysis_result_template_id', 'slot'], 'artt_template_slot_unique');
            $table->unique(['analysis_result_template_id', 'analysis_type_id'], 'artt_template_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_result_template_types');
        Schema::dropIfExists('analysis_result_templates');
    }
};
