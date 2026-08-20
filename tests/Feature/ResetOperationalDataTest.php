<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ResetOperationalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_keeps_users_forms_and_customers_and_adds_second_analyst(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Keep Me Customer',
            'customer_email' => 'keep-me@example.com',
            'customer_contact' => '09170001111',
            'customer_address' => 'Bacolod City',
            'company_name' => 'Keep Me Co',
            'samples' => [
                ['description' => 'Water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $this->assertSame(1, JobOrder::query()->count());
        $this->assertDatabaseHas('customers', ['customer_email' => 'keep-me@example.com']);

        Artisan::call('nppc:reset-operational-data', ['--force' => true]);

        $this->assertSame(0, JobOrder::query()->count());
        $this->assertDatabaseHas('users', ['email' => 'admin@nppc.local']);
        $this->assertDatabaseHas('users', ['email' => 'analyst2@nppc.local']);
        $this->assertDatabaseHas('customers', [
            'customer_email' => 'keep-me@example.com',
            'customer_name' => 'Keep Me Customer',
        ]);

        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $this->assertTrue($second->hasRole('analyst'));

        $this->from('/intake')
            ->post('/intake/lookup', ['query' => 'keep-me@example.com'])
            ->assertRedirect();
    }
}
