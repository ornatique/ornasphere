<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\JobworkIssue;
use App\Services\JobworkRawStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BackfillJobworkRawStock extends Command
{
    protected $signature = 'jobwork:backfill-raw-stock {company_id} {--dry-run}';
    protected $description = 'Allocate existing raw jobwork issues against Receive/Purchase stock, oldest first';

    public function handle(JobworkRawStock $stock): int
    {
        $company = Company::findOrFail((int) $this->argument('company_id'));
        $failed = 0;
        DB::beginTransaction();
        try {
            $stock->lock((int) $company->id);
            $issues = JobworkIssue::where('company_id', $company->id)
                ->whereHas('items.item', fn($q) => $q->whereRaw("LOWER(jobwork_item_type) = 'raw'"))
                ->orderBy('jobwork_date')->orderBy('id')->get();
            foreach ($issues as $issue) {
                if (DB::table('jobwork_raw_stock_allocations')->where('jobwork_issue_id', $issue->id)->exists()) continue;
                try {
                    DB::transaction(function () use ($stock, $issue, $company) {
                        $stock->syncIssue($issue);
                        $stock->validateReturns((int) $company->id);
                    });
                    $this->info("Allocated issue {$issue->id} ({$issue->voucher_no}).");
                } catch (ValidationException $e) {
                    $failed++;
                    $this->error("Issue {$issue->id}: {$e->getMessage()}");
                }
            }
            if ($this->option('dry-run') || $failed) {
                DB::rollBack();
                $this->warn('No changes saved. Correct any reported shortages and rerun.');
            } else {
                DB::commit();
                $this->info('Raw stock allocations saved.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
