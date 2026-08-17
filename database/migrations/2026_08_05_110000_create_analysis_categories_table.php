<?php

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $sort = 0;
        foreach (AnalysisCategoryEnum::cases() as $case) {
            DB::table('analysis_categories')->insert([
                'slug' => $case->value,
                'name' => $case->label(),
                'sort_order' => $sort++,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('analysis_types', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('analysis_categories')->nullOnDelete();
        });

        $categories = DB::table('analysis_categories')->pluck('id', 'slug');

        foreach (DB::table('analysis_types')->select(['id', 'category'])->get() as $type) {
            $categoryId = $categories[$type->category] ?? $categories['other'] ?? null;
            DB::table('analysis_types')->where('id', $type->id)->update([
                'category_id' => $categoryId,
            ]);
        }

        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->string('category_label')->nullable()->after('category');
        });

        foreach (DB::table('job_order_analyses')->select(['id', 'category'])->get() as $line) {
            $label = null;
            foreach (AnalysisCategoryEnum::cases() as $case) {
                if ($case->value === $line->category) {
                    $label = $case->label();
                    break;
                }
            }

            DB::table('job_order_analyses')->where('id', $line->id)->update([
                'category_label' => $label ?? $line->category,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_order_analyses', function (Blueprint $table) {
            $table->dropColumn('category_label');
        });

        Schema::table('analysis_types', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });

        $categories = DB::table('analysis_categories')->pluck('slug', 'id');

        foreach (DB::table('analysis_types')->select(['id', 'category_id'])->get() as $type) {
            DB::table('analysis_types')->where('id', $type->id)->update([
                'category' => $categories[$type->category_id] ?? 'other',
            ]);
        }

        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('analysis_categories');
    }
};
