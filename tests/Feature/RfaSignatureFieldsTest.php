<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RfaSignatureFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_fields_fill_from_submit_pricing_and_jo_approval(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->travelTo(Carbon::parse('2026-09-06 10:15:00', 'Asia/Manila'));

        $this->post('/intake/job-orders', [
            'customer_name' => 'Conforme Customer',
            'customer_email' => 'conforme@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Signature sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job);

        $this->assertSame('Conforme Customer', $bag['conforme_name']);
        $this->assertSame('09/06/2026', $bag['conforme_date']);
        $this->assertNull($bag['received_date']);
        $this->assertNull($bag['reviewed_date']);
        $this->assertNull($bag['job_orders.jo_approved_at']);

        $line = $job->analyses()->firstOrFail();

        $this->travelTo(Carbon::parse('2026-09-06 14:00:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 100, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $job->refresh();
        $this->assertNotNull($job->received_at);
        $this->assertSame($receiving->id, $job->received_by);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertSame('09/06/2026', $bag['received_date']);
        $this->assertSame('09/06/2026', $bag['conforme_date']);
        $this->assertNull($bag['reviewed_date']);

        $pricedReceivedAt = $job->received_at->toIso8601String();

        $this->travelTo(Carbon::parse('2026-09-07 09:00:00', 'Asia/Manila'));
        $this->approveJobOrder($job);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh());
        $this->assertSame('09/07/2026', $bag['reviewed_date']);
        $this->assertSame('09/07/2026', $bag['job_orders.jo_approved_at']);
        $this->assertSame('09/06/2026', $bag['received_date']);
        $this->assertSame('Conforme Customer', $bag['conforme_name']);
        $this->assertSame('09/06/2026', $bag['conforme_date']);

        $this->travelTo(Carbon::parse('2026-09-08 14:30:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $job->refresh();
        $this->assertSame($pricedReceivedAt, $job->received_at?->toIso8601String());

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertSame('09/06/2026', $bag['received_date']);
        $this->assertSame('09/07/2026', $bag['reviewed_date']);
        $this->assertSame('09/06/2026', $bag['conforme_date']);
    }
}
