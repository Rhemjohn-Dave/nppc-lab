<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceivingDayFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_day_filter_matches_intake_created_at_in_lab_timezone(): void
    {
        $this->seed();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00', 'Asia/Manila'));
        $onDay = $this->jobOrder('26-9101', JobOrderStatus::DraftSubmitted);

        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00', 'Asia/Manila'));
        $this->jobOrder('26-9102', JobOrderStatus::DraftSubmitted);

        $this->actingAs($receiving)
            ->get('/receiving?day=2026-10-11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('receiving/index')
                ->where('filters.day', '2026-10-11')
                ->where('counts.all', 1)
                ->where('orders.data.0.id', $onDay->id)
                ->where('orders.data.0.reference_no', '26-9101'));

        $this->actingAs($receiving)
            ->get('/receiving?day=2026-10-10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.day', '2026-10-10')
                ->where('counts.all', 0)
                ->has('orders.data', 0));
    }

    public function test_day_filter_includes_sent_job_orders_for_that_day(): void
    {
        $this->seed();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00', 'Asia/Manila'));
        $sent = $this->jobOrder('26-9103', JobOrderStatus::InAnalysis);

        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->get('/receiving')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.day', '')
                ->where('counts.all', 0)
                ->has('orders.data', 0));

        $this->actingAs($receiving)
            ->get('/receiving?day=2026-10-11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.day', '2026-10-11')
                ->where('counts.all', 1)
                ->where('orders.data.0.id', $sent->id)
                ->where('orders.data.0.status', JobOrderStatus::InAnalysis->value));
    }

    public function test_invalid_day_is_ignored(): void
    {
        $this->seed();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00', 'Asia/Manila'));
        $this->jobOrder('26-9104', JobOrderStatus::JoApproved);

        $this->actingAs($receiving)
            ->get('/receiving?day=2026-13-45')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.day', '')
                ->where('counts.all', 1));
    }

    private function jobOrder(string $reference, JobOrderStatus $status): JobOrder
    {
        return JobOrder::query()->create([
            'reference_no' => $reference,
            'customer_name' => "Customer {$reference}",
            'status' => $status,
        ]);
    }
}
