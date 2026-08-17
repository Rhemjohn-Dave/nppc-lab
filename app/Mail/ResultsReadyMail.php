<?php

namespace App\Mail;

use App\Models\JobOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResultsReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public JobOrder $jobOrder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "NPPC Lab results ready for pickup — {$this->jobOrder->reference_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.results-ready',
            with: [
                'jobOrder' => $this->jobOrder,
            ],
        );
    }
}
