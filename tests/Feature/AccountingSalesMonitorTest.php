<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Enums\PaymentMode;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingSalesMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_user_can_view_daily_sales_for_approved_jobs(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();

        JobOrder::query()->create([
            'reference_no' => '26-9001',
            'customer_name' => 'Sales Customer',
            'company_name' => 'Sales Co',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 1500.5,
            'discount_amount' => 100,
            'jo_approved_at' => now(),
            'jo_approved_by' => User::where('email', 'head@nppc.local')->value('id'),
        ]);

        JobOrder::query()->create([
            'reference_no' => '26-9002',
            'customer_name' => 'Yesterday Customer',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Check,
            'total_cost' => 999,
            'discount_amount' => 0,
            'jo_approved_at' => now()->subDay(),
            'jo_approved_by' => User::where('email', 'head@nppc.local')->value('id'),
        ]);

        $this->actingAs($accounting)
            ->get('/accounting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accounting/index')
                ->where('summary.job_count', 1)
                ->where('summary.net', 1500.5)
                ->where('summary.discount', 100)
                ->where('summary.gross', 1600.5)
                ->has('orders.data', 1)
                ->where('orders.data.0.reference_no', '26-9001')
            );
    }

    public function test_admin_can_access_accounting_and_others_cannot(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/accounting')
            ->assertOk();

        $this->actingAs($receiving)
            ->get('/accounting')
            ->assertForbidden();

        $this->actingAs($analyst)
            ->get('/accounting')
            ->assertForbidden();
    }

    public function test_accounting_dashboard_uses_accounting_role_payload(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();

        $this->actingAs($accounting)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('role', 'accounting')
            );
    }
}
