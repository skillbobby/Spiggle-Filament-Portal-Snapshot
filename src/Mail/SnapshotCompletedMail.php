<?php

namespace Spiggle\FilamentPortalSnapshot\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SnapshotCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** @param  array<string, mixed>  $meta */
    public function __construct(
        public string $title,
        public string $body,
        public bool $success,
        public array $meta = [],
    ) {}

    public function envelope(): Envelope
    {
        $app = config('app.name', 'Laravel');

        return new Envelope(subject: "[{$app}] {$this->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'filament-portal-snapshot::emails.snapshot-completed');
    }
}
