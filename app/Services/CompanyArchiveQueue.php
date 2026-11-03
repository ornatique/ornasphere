<?php

namespace App\Services;

use App\Jobs\ProcessItemSetImageUpload;
use App\Jobs\ProcessStockGalleryImage;
use App\Mail\CompanyLoginMail;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Inspect only known queue metadata; never unserialize or execute a queued command. */
class CompanyArchiveQueue
{
    public function blocksCompany(int $companyId): bool
    {
        if (!Schema::hasTable('jobs')) return false;
        foreach (DB::table('jobs')->select('payload')->cursor() as $job) {
            $payload = json_decode($job->payload, true);
            $class = $payload['data']['commandName'] ?? null;
            $display = $payload['displayName'] ?? null;
            $command = $payload['data']['command'] ?? null;
            if (!is_string($class) || !is_string($command) || !str_starts_with($command, $this->objectPrefix($class))) return true;
            // Account-only mail uses retained Company/User records and does not change business data.
            if ($class === SendQueuedMailable::class && $display === CompanyLoginMail::class
                && str_contains($command, $this->objectPrefix(CompanyLoginMail::class))) continue;
            if ($display !== $class) return true;
            if ($class === ProcessItemSetImageUpload::class && preg_match('/companyId";i:([0-9]+);/', $command, $match)) {
                if ((int)$match[1] === $companyId) return true;
                continue;
            }
            if ($class === ProcessStockGalleryImage::class && preg_match('/mediaId";i:([0-9]+);/', $command, $match)) {
                if (!Schema::hasTable('stock_gallery_media')) return true;
                $owner = DB::table('stock_gallery_media')->where('id', (int)$match[1])->value('company_id');
                // Missing media rows make this specific reviewed job a no-op.
                if ($owner !== null && (int)$owner === $companyId) return true;
                continue;
            }
            return true; // Unknown job types require review rather than guessed ownership.
        }
        return false;
    }

    private function objectPrefix(string $class): string
    {
        return 'O:'.strlen($class).':"'.$class.'":';
    }
}
