<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class StockGalleryLog
{
    public static function write(string $event, array $context = []): void
    {
        Log::build(['driver' => 'daily', 'path' => storage_path('logs/stock-gallery.log'), 'days' => 14])
            ->info($event, $context);
    }
}
