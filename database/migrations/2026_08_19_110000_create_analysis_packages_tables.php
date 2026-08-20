<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->boolean('show_on_kiosk')->default(true)->after('is_active');
        });

        Schema::create('analysis_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('analysis_categories')->nullOnDelete();
            $table->decimal('default_price', 12, 2)->default(0);
            $table->json('classifications')->nullable();
            $table->string('form_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('analysis_package_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('slot')->default(1);
            $table->timestamps();

            $table->unique(['analysis_package_id', 'analysis_type_id'], 'package_type_unique');
        });

        Schema::create('job_order_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_package_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_order_id', 'analysis_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_packages');
        Schema::dropIfExists('analysis_package_types');
        Schema::dropIfExists('analysis_packages');

        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropColumn('show_on_kiosk');
        });
    }
};
