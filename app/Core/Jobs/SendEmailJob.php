<?php

namespace App\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
    ) {
        $this->onQueue(config('structer.queues.emails', 'emails'));
    }

    public function handle(): void
    {
        Log::info('Queued email dispatched', [
            'to' => $this->to,
            'subject' => $this->subject,
        ]);
    }
}
