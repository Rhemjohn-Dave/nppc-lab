<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisResultPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_can_download_pdf_for_own_completed_analysis(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Result PDF Customer',
            'customer_email' => 'result-pdf@example.com',
            'customer_contact' => '09170000001',
            'samples' => [
                ['description' => 'Tap water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '7.2',
                'result_unit' => 'pH',
                'result_remarks' => 'Within range',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        $response = $this->actingAs($analyst)
            ->get("/analyst/tasks/{$line->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            "Result-{$job->reference_no}-",
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_other_analyst_cannot_download_result_pdf(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Other Analyst Customer',
            'customer_email' => 'other-analyst@example.com',
            'customer_contact' => '09170000002',
            'samples' => [
                ['description' => 'Sample B', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '1.0',
            ])
            ->assertRedirect();

        $other = User::factory()->create([
            'name' => 'Other Analyst',
            'email' => 'analyst2@nppc.local',
        ]);
        $other->assignRole('analyst');

        $this->actingAs($other)
            ->get("/analyst/tasks/{$line->id}/pdf")
            ->assertForbidden();
    }

    public function test_admin_can_download_result_pdf(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Admin PDF Customer',
            'customer_email' => 'admin-pdf@example.com',
            'customer_contact' => '09170000003',
            'samples' => [
                ['description' => 'Sample C', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '12.5',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get("/analyst/tasks/{$line->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
