<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_does_not_reset_staff_passwords_or_catalog_prices(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $admin->update(['password' => Hash::make('changed-password')]);

        $type = AnalysisType::query()->firstOrFail();
        $type->update(['default_price' => '999.00']);
        $originalCode = $type->code;

        $this->seed();

        $this->assertTrue(Hash::check('changed-password', $admin->fresh()->password));
        $this->assertFalse(Hash::check('password', $admin->fresh()->password));
        $this->assertSame('999.00', AnalysisType::query()->where('code', $originalCode)->value('default_price'));
    }

    public function test_reseed_does_not_overwrite_existing_analyst_assignments(): void
    {
        $this->seed();

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $kept = AnalysisType::query()->orderBy('id')->value('id');
        $this->assertNotNull($kept);
        $analyst->analysisTypes()->sync([$kept]);

        $this->seed();

        $this->assertEqualsCanonicalizing(
            [(int) $kept],
            $analyst->analysisTypes()->pluck('analysis_types.id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_production_check_fails_in_the_test_environment(): void
    {
        $this->artisan('nppc:production-check')
            ->assertFailed();
    }
}
