<?php

namespace Tests\Feature;

use App\Models\AnalysisCategory;
use App\Models\AnalysisType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyProximateCategoryHealTest extends TestCase
{
    use RefreshDatabase;

    public function test_heal_migration_deletes_legacy_proximate_and_moves_types(): void
    {
        $this->seed();

        $legacy = AnalysisCategory::query()->updateOrCreate(
            ['slug' => 'proximate'],
            [
                'name' => 'Proximate Analysis',
                'sort_order' => 99,
                'is_active' => true,
            ],
        );
        $legacyId = $legacy->id;

        $canonical = AnalysisCategory::query()->where('slug', 'proximate_analysis')->firstOrFail();

        $orphan = AnalysisType::query()->create([
            'code' => 'PX-LEGACY',
            'name' => 'Legacy proximate type',
            'category_id' => $legacyId,
            'default_price' => 100,
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $migration = require database_path('migrations/2026_09_07_151000_delete_legacy_duplicate_categories.php');
        $migration->up();

        $orphan->refresh();

        $this->assertNull(AnalysisCategory::query()->find($legacyId));
        $this->assertDatabaseMissing('analysis_categories', ['slug' => 'proximate']);
        $this->assertSame($canonical->id, $orphan->category_id);
    }

    public function test_procedures_page_has_single_proximate_category(): void
    {
        $this->seed();

        AnalysisCategory::query()->updateOrCreate(
            ['slug' => 'proximate'],
            [
                'name' => 'Proximate Analysis',
                'sort_order' => 99,
                'is_active' => true,
            ],
        );

        $migration = require database_path('migrations/2026_09_07_151000_delete_legacy_duplicate_categories.php');
        $migration->up();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $canonical = AnalysisCategory::query()->where('slug', 'proximate_analysis')->firstOrFail();

        $this->assertSame(
            1,
            AnalysisCategory::query()->where('name', 'Proximate Analysis')->count(),
        );

        $this->actingAs($admin)
            ->get('/admin/prices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prices')
                ->has('categories')
                ->where(
                    'categories',
                    fn ($cats) => collect($cats)->contains(fn ($c) => $c['id'] === $canonical->id)
                        && collect($cats)->every(fn ($c) => $c['slug'] !== 'proximate'),
                ));
    }
}
