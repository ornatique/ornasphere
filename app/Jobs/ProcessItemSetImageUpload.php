<?php

namespace App\Jobs;

use App\Services\ItemSetImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessItemSetImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly int $itemSetId,
        private readonly int $companyId,
        private readonly string $pendingPath,
        private readonly string $originalMime
    ) {
    }

    public function handle(ItemSetImageService $images): void
    {
        $images->processPendingUpload(
            $this->itemSetId,
            $this->companyId,
            $this->pendingPath,
            $this->originalMime
        );
    }
}
