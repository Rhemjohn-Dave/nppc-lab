<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\ReferenceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlNumberAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_next_control_number(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $year = now()->format('y');

        $this->actingAs($admin)
            ->put('/admin/control-number', ['next_number' => 500])
            ->assertRedirect();

        $this->assertDatabaseHas('reference_counters', [
            'year' => $year,
            'last_number' => 499,
        ]);

        $reference = app(ReferenceNumberService::class)->next();
        $this->assertSame(sprintf('%s-0500', $year), $reference);
    }

    public function test_admin_cannot_set_next_below_highest_issued(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $year = now()->format('y');

        JobOrder::query()->create([
            'reference_no' => sprintf('%s-0010', $year),
            'customer_name' => 'Existing Customer',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);

        $this->actingAs($admin)
            ->from('/admin/control-number')
            ->put('/admin/control-number', ['next_number' => 10])
            ->assertRedirect('/admin/control-number')
            ->assertSessionHasErrors('next_number');
    }

    public function test_non_admin_cannot_access_control_number(): void
    {
        $this->seed();

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($analyst)
            ->get('/admin/control-number')
            ->assertForbidden();
    }
}
