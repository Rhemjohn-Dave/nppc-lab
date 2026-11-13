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
        $headId = User::where('email', 'head@nppc.local')->value('id');

        JobOrder::query()->create([
            'reference_no' => '26-9001',
            'customer_name' => 'Sales Customer',
            'company_name' => 'Sales Co',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 1500.5,
            'discount_amount' => 100,
            'jo_approved_at' => now(),
            'jo_approved_by' => $headId,
        ]);

        JobOrder::query()->create([
            'reference_no' => '26-9002',
            'customer_name' => 'Yesterday Customer',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Check,
            'total_cost' => 999,
            'discount_amount' => 0,
            'jo_approved_at' => now()->subDay(),
            'jo_approved_by' => $headId,
        ]);

        $this->actingAs($accounting)
            ->get('/accounting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accounting/index')
                ->where('filters.date_from', now()->toDateString())
                ->where('filters.date_to', now()->toDateString())
                ->where('summary.job_count', 1)
                ->where('summary.net', 1500.5)
                ->where('summary.discount', 100)
                ->where('summary.gross', 1600.5)
                ->has('orders.data', 1)
                ->where('orders.data.0.reference_no', '26-9001')
                ->has('classification_options')
                ->has('trend', 1)
            );
    }

    public function test_date_range_filters_kpis_and_list(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();
        $headId = User::where('email', 'head@nppc.local')->value('id');

        JobOrder::query()->create([
            'reference_no' => '26-9101',
            'customer_name' => 'Day One',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 100,
            'discount_amount' => 0,
            'jo_approved_at' => now()->subDays(2)->setTime(10, 0),
            'jo_approved_by' => $headId,
        ]);

        JobOrder::query()->create([
            'reference_no' => '26-9102',
            'customer_name' => 'Day Two',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 200,
            'discount_amount' => 0,
            'jo_approved_at' => now()->subDay()->setTime(11, 0),
            'jo_approved_by' => $headId,
        ]);

        $from = now()->subDays(2)->toDateString();
        $to = now()->subDays(2)->toDateString();

        $this->actingAs($accounting)
            ->get('/accounting?'.http_build_query([
                'date_from' => $from,
                'date_to' => $to,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.date_from', $from)
                ->where('filters.date_to', $to)
                ->where('summary.job_count', 1)
                ->where('summary.net', 100)
                ->has('orders.data', 1)
                ->where('orders.data.0.reference_no', '26-9101')
                ->has('trend', 1)
            );
    }

    public function test_search_filters_by_reference_or_company(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();
        $headId = User::where('email', 'head@nppc.local')->value('id');
        $today = now();

        JobOrder::query()->create([
            'reference_no' => '26-9201',
            'customer_name' => 'Alpha Person',
            'company_name' => 'Acme Labs',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 50,
            'discount_amount' => 0,
            'jo_approved_at' => $today,
            'jo_approved_by' => $headId,
        ]);

        JobOrder::query()->create([
            'reference_no' => '26-9202',
            'customer_name' => 'Beta Person',
            'company_name' => 'Other Corp',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 75,
            'discount_amount' => 0,
            'jo_approved_at' => $today,
            'jo_approved_by' => $headId,
        ]);

        $this->actingAs($accounting)
            ->get('/accounting?'.http_build_query([
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
                'q' => 'Acme',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'Acme')
                ->where('summary.job_count', 1)
                ->where('orders.data.0.reference_no', '26-9201')
            );

        $this->actingAs($accounting)
            ->get('/accounting?'.http_build_query([
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
                'q' => '9202',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.job_count', 1)
                ->where('orders.data.0.reference_no', '26-9202')
            );
    }

    public function test_classification_filter_matches_exact_value(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();
        $headId = User::where('email', 'head@nppc.local')->value('id');
        $today = now();

        JobOrder::query()->create([
            'reference_no' => '26-9301',
            'customer_name' => 'Aqua Customer',
            'classification' => 'Aqua',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 10,
            'discount_amount' => 0,
            'jo_approved_at' => $today,
            'jo_approved_by' => $headId,
        ]);

        JobOrder::query()->create([
            'reference_no' => '26-9302',
            'customer_name' => 'Potability Customer',
            'classification' => 'Potability',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 20,
            'discount_amount' => 0,
            'jo_approved_at' => $today,
            'jo_approved_by' => $headId,
        ]);

        $this->actingAs($accounting)
            ->get('/accounting?'.http_build_query([
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
                'classification' => 'Aqua',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.classification', 'Aqua')
                ->where('summary.job_count', 1)
                ->where('summary.net', 10)
                ->where('orders.data.0.reference_no', '26-9301')
            );
    }

    public function test_payment_mode_chip_works_with_date_range(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();
        $headId = User::where('email', 'head@nppc.local')->value('id');
        $from = now()->subDay();
        $to = now();

        JobOrder::query()->create([
            'reference_no' => '26-9401',
            'customer_name' => 'Cash Yesterday',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 100,
            'discount_amount' => 0,
            'jo_approved_at' => $from,
            'jo_approved_by' => $headId,
        ]);

        JobOrder::query()->create([
            'reference_no' => '26-9402',
            'customer_name' => 'Check Today',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Check,
            'total_cost' => 200,
            'discount_amount' => 0,
            'jo_approved_at' => $to,
            'jo_approved_by' => $headId,
        ]);

        $this->actingAs($accounting)
            ->get('/accounting?'.http_build_query([
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'payment_mode' => 'cash',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.payment_mode', 'cash')
                ->where('summary.job_count', 1)
                ->where('summary.net', 100)
                ->has('orders.data', 1)
                ->where('orders.data.0.reference_no', '26-9401')
                ->where('by_payment_mode.0.id', 'cash')
                ->where('by_payment_mode.0.count', 1)
                ->where('by_payment_mode.2.id', 'check')
                ->where('by_payment_mode.2.count', 1)
            );
    }

    public function test_legacy_date_param_maps_to_from_and_to(): void
    {
        $this->seed();

        $accounting = User::where('email', 'accounting@nppc.local')->firstOrFail();
        $headId = User::where('email', 'head@nppc.local')->value('id');
        $day = now()->subDays(3);

        JobOrder::query()->create([
            'reference_no' => '26-9501',
            'customer_name' => 'Legacy Day',
            'status' => JobOrderStatus::JoApproved,
            'payment_mode' => PaymentMode::Cash,
            'total_cost' => 42,
            'discount_amount' => 0,
            'jo_approved_at' => $day,
            'jo_approved_by' => $headId,
        ]);

        $date = $day->toDateString();

        $this->actingAs($accounting)
            ->get('/accounting?date='.$date)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.date_from', $date)
                ->where('filters.date_to', $date)
                ->where('summary.job_count', 1)
                ->where('summary.net', 42)
                ->where('orders.data.0.reference_no', '26-9501')
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
