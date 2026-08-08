<?php

namespace Workbench\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Workbench\App\DataTransferObjects\SerializablePostData;

class SummarisePostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The DTO handed to the last job instance processed, kept here purely so
     * tests can inspect what `handle()` saw after a serialize/unserialize
     * round trip.
     */
    public static ?SerializablePostData $handled = null;

    public function __construct(
        public SerializablePostData $summary,
    ) {
        //
    }

    public function handle(): void
    {
        static::$handled = $this->summary;
    }
}
