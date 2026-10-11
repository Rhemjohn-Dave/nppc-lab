<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalystDayFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_tabs_filter_by_job_order_received_day(): void
    {
        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00', 'Asia/Manila'));
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->openLine('26-9001', Carbon::parse('2026-10-10 09:00', 'Asia/Manila'));
        $this->openLine('26-9002', Carbon::parse('2026-10-11 08:00', 'Asia/Manila'));

        $this->actingAs($admin)
            ->get('/analyst?day=2026-10-11')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.day', '2026-10-11')
                ->where('counts.all', 1)
                ->where('tasks', fn ($tasks) => collect($tasks)->pluck('job_order.reference_no')->unique()->values()->all() === ['26-9002']));

        $this->actingAs($admin)
            ->get('/analyst?day=2026-10-10')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.all', 1)
                ->where('tasks', fn ($tasks) => collect($tasks)->pluck('job_order.reference_no')->unique()->values()->all() === ['26-9001']));
    }

    public function test_completed_tab_filters_by_completed_day(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $received = Carbon::parse('2026-10-05 09:00', 'Asia/Manila');
        $this->completedLine('26-9003', $received, Carbon::parse('2026-10-09 14:00', 'Asia/Manila'));
        $this->completedLine('26-9004', $received, Carbon::parse('2026-10-10 14:00', 'Asia/Manila'));

        $this->actingAs($admin)
            ->get('/analyst?status=completed&day=2026-10-10')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.completed', 1)
                ->where('tasks', fn ($tasks) => collect($tasks)->pluck('job_order.reference_no')->unique()->values()->all() === ['26-9004']));
    }

    public function test_late_evening_manila_time_belongs_to_manila_day(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        // 23:30 Manila = 15:30 UTC the same calendar day; must not spill into Oct 11.
        $this->openLine('26-9005', Carbon::parse('2026-10-10 23:30', 'Asia/Manila'));

        $this->actingAs($admin)
            ->get('/analyst?day=2026-10-10')
            ->assertInertia(fn (Assert $page) => $page->where('counts.all', 1));

        $this->actingAs($admin)
            ->get('/analyst?day=2026-10-11')
            ->assertInertia(fn (Assert $page) => $page->where('counts.all', 0));
    }

    public function test_invalid_day_is_ignored(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->openLine('26-9006', Carbon::parse('2026-10-01 09:00', 'Asia/Manila'));
        $this->openLine('26-9007', Carbon::parse('2026-10-02 09:00', 'Asia/Manila'));

        $this->actingAs($admin)
            ->get('/analyst?day=2026-13-45')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.day', '')
                ->where('counts.all', 2));
    }

    private function openLine(string $reference, Carbon $receivedAt): JobOrderAnalysis
    {
        return $this->line($reference, $receivedAt, JobOrderAnalysisStatus::Assigned, null);
    }

    private function completedLine(string $reference, Carbon $receivedAt, Carbon $completedAt): JobOrderAnalysis
    {
        return $this->line($reference, $receivedAt, JobOrderAnalysisStatus::Completed, $completedAt);
    }

    private function line(string $reference, Carbon $receivedAt, JobOrderAnalysisStatus $status, ?Carbon $completedAt): JobOrderAnalysis
    {
        $job = JobOrder::query()->create([
            'reference_no' => $reference,
            'customer_name' => "Customer {$reference}",
            'status' => JobOrderStatus::InAnalysis,
            'received_at' => $receivedAt->copy()->timezone(config('app.timezone')),
        ]);

        return JobOrderAnalysis::query()->create([
            'job_order_id' => $job->id,
            'name' => 'pH',
            'status' => $status,
            'completed_at' => $completedAt?->copy()->timezone(config('app.timezone')),
        ]);
    }
}
