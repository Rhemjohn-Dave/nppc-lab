<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\RfaFormTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class FormTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_flat_pdf_and_generate_fillable(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $pdf = $this->makeBlankFolioPdf();

        $this->actingAs($admin)
            ->post('/admin/form-templates', [
                'template' => $pdf,
                'notes' => 'Revision 10',
            ])
            ->assertRedirect();

        $templates = app(RfaFormTemplateService::class);
        $this->assertTrue($templates->hasSource());
        $this->assertTrue($templates->hasFillable());
        $this->assertSame('Revision 10', $templates->meta()['notes'] ?? null);

        $fillable = file_get_contents($templates->fillableAbsolutePath());
        $this->assertNotFalse($fillable);
        $this->assertStringStartsWith('%PDF', $fillable);
    }

    public function test_receiving_pdf_uses_template_when_uploaded(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/form-templates', [
                'template' => $this->makeBlankFolioPdf(),
            ])
            ->assertRedirect();

        $job = JobOrder::query()->create([
            'reference_no' => '26-7999',
            'customer_name' => 'Template Customer',
            'customer_address' => 'Bacolod',
            'customer_contact' => '09170000000',
            'ownership_type' => 'Private',
            'classification' => 'Potability',
            'status' => JobOrderStatus::Priced,
            'total_cost' => 250,
        ]);

        $response = $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'RFA-26-7999.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_non_admin_cannot_access_form_templates(): void
    {
        $this->seed();

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($analyst)
            ->get('/admin/form-templates')
            ->assertForbidden();
    }

    private function makeBlankFolioPdf(): UploadedFile
    {
        $pdf = new Fpdi('P', 'mm', [215.9, 330.2], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Text(20, 20, 'Blank RFA template for tests');
        $binary = $pdf->Output('', 'S');

        $path = tempnam(sys_get_temp_dir(), 'rfa');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'rfa-test.pdf', 'application/pdf', null, true);
    }
}
