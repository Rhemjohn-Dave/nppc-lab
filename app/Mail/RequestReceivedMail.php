<?php

namespace App\Mail;

use App\Models\JobOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public JobOrder $jobOrder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "NPPC Lab request received — {$this->jobOrder->reference_no}",
        );
    }

    public function content(): Content
    {
        $this->jobOrder->loadMissing(['samples', 'analyses']);

        $tests = $this->jobOrder->analyses
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return new Content(
            markdown: 'mail.request-received',
            with: [
                'jobOrder' => $this->jobOrder,
                'sampleCount' => $this->jobOrder->samples->count(),
                'tests' => $tests,
            ],
        );
    }
}
