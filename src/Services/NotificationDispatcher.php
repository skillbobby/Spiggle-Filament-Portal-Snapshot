<?php

namespace Spiggle\FilamentPortalSnapshot\Services;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spiggle\FilamentPortalSnapshot\Mail\SnapshotCompletedMail;
use Throwable;

class NotificationDispatcher
{
    public function completed(
        string $title,
        string $body,
        bool $success = true,
        ?Authenticatable $actor = null,
        array $meta = [],
    ): void {
        $this->filamentBell($title, $body, $success, $actor);
        $this->mail($title, $body, $success, $actor, $meta);
    }

    public function filamentBell(string $title, string $body, bool $success, ?Authenticatable $actor): void
    {
        if (! config('filament-portal-snapshot.notifications.database', true)) {
            return;
        }

        try {
            $notification = FilamentNotification::make()
                ->title($title)
                ->body($body);

            $success ? $notification->success() : $notification->danger();

            if ($actor) {
                $notification->sendToDatabase($actor);
            }

            $notification->send();
        } catch (Throwable $exception) {
            Log::debug('Portal snapshot Filament notification skipped.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function mail(
        string $title,
        string $body,
        bool $success,
        ?Authenticatable $actor,
        array $meta = [],
    ): void {
        if (! config('filament-portal-snapshot.notifications.mail.enabled', true)) {
            return;
        }

        $recipients = $this->recipients($actor);

        if ($recipients === []) {
            return;
        }

        try {
            $pending = Mail::to($recipients);

            $mailable = new SnapshotCompletedMail(
                title: $title,
                body: $body,
                success: $success,
                meta: $meta,
            );

            $queue = config('filament-portal-snapshot.notifications.mail.queue');

            if (filled($queue)) {
                $pending->queue($mailable->onQueue($queue));
            } else {
                $pending->queue($mailable);
            }
        } catch (Throwable $exception) {
            Log::debug('Portal snapshot mail notification skipped.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /** @return list<string> */
    protected function recipients(?Authenticatable $actor): array
    {
        $configured = config('filament-portal-snapshot.notifications.mail.recipients', []);
        $configured = is_array($configured) ? $configured : [];

        $email = $actor && isset($actor->email) ? (string) $actor->email : null;

        return array_values(array_unique(array_filter([
            $email,
            ...$configured,
        ])));
    }
}
