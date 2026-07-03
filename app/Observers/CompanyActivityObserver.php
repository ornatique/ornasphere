<?php

namespace App\Observers;

use App\Models\ApprovalHeader;
use App\Models\CompanyAppTheme;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemSet;
use App\Models\JobWorker;
use App\Models\JobworkIssue;
use App\Models\LabelConfig;
use App\Models\LabourFormula;
use App\Models\OtherCharge;
use App\Models\ProductionCost;
use App\Models\ProductionStep;
use App\Models\Sale;
use App\Models\User;
use App\Models\VisitingCard;
use App\Services\CompanyNotificationService;
use Illuminate\Database\Eloquent\Model;

class CompanyActivityObserver
{
    public function created(Model $model): void
    {
        $this->notify($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changes = array_diff(array_keys($model->getChanges()), ['updated_at']);
        if (!$changes) {
            return;
        }

        if ($model instanceof ItemSet) {
            $tracked = [
                'item_id',
                'gross_weight',
                'other',
                'net_weight',
                'sale_labour_formula',
                'sale_labour_rate',
                'sale_labour_amount',
                'sale_other',
                'supplier_person',
                'size',
                'HUID',
                'qr_code',
                'barcode',
                'serial_no',
            ];

            if (!array_intersect($changes, $tracked)) {
                return;
            }
        }

        $this->notify($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->notify($model, 'deleted');
    }

    private function notify(Model $model, string $action): void
    {
        $actor = auth()->user();
        if (!$actor instanceof User) {
            return;
        }

        $meta = $this->meta($model);
        if (!$meta) {
            return;
        }

        [$module, $label, $routeName] = $meta;
        $name = $this->displayName($model);
        $actorName = $actor->name ?: 'User';
        $title = "{$label} {$action}";
        $message = trim("{$actorName} {$action} {$label}" . ($name !== '' ? ": {$name}" : ''));
        $routeParams = $actor->company ? ['slug' => $actor->company->slug] : [];

        CompanyNotificationService::record(
            $actor,
            $module,
            $action,
            $title,
            $message,
            $routeName,
            $routeParams,
            $model
        );
    }

    private function meta(Model $model): ?array
    {
        return match (true) {
            $model instanceof User => ['user', 'User', 'company.users.index'],
            $model instanceof Customer => ['customer', 'Customer', 'company.customers.index'],
            $model instanceof CompanyAppTheme => ['app_theme', 'App Theme', 'company.app-themes.index'],
            $model instanceof Item => ['item', 'Item', 'company.items.index'],
            $model instanceof LabelConfig => ['label_config', 'Label Config', 'company.label_config.index'],
            $model instanceof ItemSet => ['item_set', 'Label Item', 'company.list_itemset'],
            $model instanceof OtherCharge => ['other_charge', 'Other Charge', 'company.other-charge.index'],
            $model instanceof ApprovalHeader => ['approval', 'Approval', 'company.approval.index'],
            $model instanceof Sale => ['sale', 'Sale', 'company.sales.index'],
            $model instanceof JobWorker => ['job_worker', 'Job Worker', 'company.job-workers.index'],
            $model instanceof JobworkIssue => ['jobwork_issue', 'Jobwork Issue', 'company.jobwork-issue.index'],
            $model instanceof ProductionCost => ['production_cost', 'Production Cost', 'company.production-cost.index'],
            $model instanceof LabourFormula => ['labour_formula', 'Labour Formula', 'company.labour-formula.index'],
            $model instanceof ProductionStep => ['production_step', 'Production Step', 'company.production-step.index'],
            $model instanceof VisitingCard => ['visiting_card', 'Visiting Card', 'company.reports.visiting-cards.index'],
            default => null,
        };
    }

    private function displayName(Model $model): string
    {
        foreach (['name', 'item_name', 'other_charge', 'prefix', 'qr_code', 'barcode', 'approval_no', 'voucher_no', 'mobile_no'] as $field) {
            $value = $model->getAttribute($field);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return (string) $model->getKey();
    }
}
