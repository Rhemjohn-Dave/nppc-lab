<?php

namespace Tests\Feature;

use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisTypeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_unused_procedure(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $category = AnalysisCategory::query()->where('slug', 'other')->firstOrFail();

        $type = AnalysisType::query()->create([
            'code' => 'DEL-01',
            'name' => 'Deletable procedure',
            'category_id' => $category->id,
            'default_price' => 10,
            'is_active' => true,
            'sort_order' => 9999,
        ]);

        $this->actingAs($admin)
            ->delete("/admin/prices/{$type->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('analysis_types', ['id' => $type->id]);
    }

    public function test_admin_cannot_delete_procedure_on_a_package(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-PROXIMATE')->firstOrFail();
        $typeId = $package->orderedTypeIds()[0] ?? null;
        $this->assertNotNull($typeId);

        $this->actingAs($admin)
            ->from('/admin/prices')
            ->delete("/admin/prices/{$typeId}")
            ->assertRedirect('/admin/prices')
            ->assertSessionHasErrors('procedure');

        $this->assertDatabaseHas('analysis_types', ['id' => $typeId]);
    }

    public function test_new_procedures_default_requires_pass_fail_off_and_admin_can_enable(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $category = AnalysisCategory::query()->where('slug', 'other')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/prices', [
                'code' => 'PF-01',
                'name' => 'Pass fail optional procedure',
                'category_id' => $category->id,
                'default_price' => 50,
                'is_active' => true,
            ])
            ->assertRedirect();

        $type = AnalysisType::query()->where('code', 'PF-01')->firstOrFail();
        $this->assertFalse($type->requires_pass_fail);

        $this->actingAs($admin)
            ->patch("/admin/prices/{$type->id}", [
                'code' => 'PF-01',
                'name' => 'Pass fail optional procedure',
                'category_id' => $category->id,
                'default_price' => 50,
                'is_active' => true,
                'requires_pass_fail' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($type->fresh()->requires_pass_fail);

        $this->actingAs($admin)
            ->get('/admin/prices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prices')
                ->where('groups', function ($groups) {
                    foreach ($groups as $group) {
                        foreach ($group['items'] ?? [] as $item) {
                            if (($item['code'] ?? null) === 'PF-01') {
                                return ($item['requires_pass_fail'] ?? false) === true;
                            }
                        }
                    }

                    return false;
                }));
    }
}
