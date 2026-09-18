<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Models\ControlledForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResultPanelsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_result_panels_lists_types_only_analysis_result_forms(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $typesOnly = ControlledForm::query()
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->orderBy('form_code')
            ->pluck('form_code')
            ->all();

        $this->assertNotEmpty($typesOnly);
        $this->assertContains('LSP-7.8-FO26', $typesOnly);
        $this->assertContains('LSP-7.8-FO27', $typesOnly);

        $packageBoundIds = ControlledForm::query()
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNotNull('analysis_package_id')
            ->pluck('id')
            ->all();

        $this->actingAs($admin)
            ->get('/admin/result-panels')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/result-panels')
                ->has('panels', count($typesOnly))
                ->where('panels.0.form_code', $typesOnly[0])
                ->where('panels', function ($panels) use ($packageBoundIds) {
                    foreach ($panels as $panel) {
                        if (in_array($panel['id'], $packageBoundIds, true)) {
                            return false;
                        }
                        if (($panel['analysis_package_id'] ?? null) !== null) {
                            return false;
                        }
                    }

                    return true;
                }));
    }

    public function test_non_admin_cannot_access_result_panels(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->actingAs($receiving)
            ->get('/admin/result-panels')
            ->assertForbidden();
    }
}
