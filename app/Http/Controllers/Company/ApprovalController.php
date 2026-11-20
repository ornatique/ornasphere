<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use App\Models\ItemSet;
use App\Models\Item;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use App\Services\TransactionItemGuard;

class ApprovalController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $companies = \App\Services\CompanyBranches::managementSelection($request, $company);

        $customers = Customer::whereIn('company_id', $companies->keys())
            ->saleParties()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        if ($request->ajax() || $request->filled('export')) {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $today = now()->toDateString();

            if (empty($fromDate) && empty($toDate)) {
                $fromDate = $today;
                $toDate = $today;
            } elseif (!empty($fromDate) && empty($toDate)) {
                $toDate = $fromDate;
            } elseif (empty($fromDate) && !empty($toDate)) {
                $fromDate = $toDate;
            }

            $viewType = $request->input('view_type') === 'item' ? 'item' : 'voucher';

            if ($viewType === 'voucher') {
                // A legacy sale can leave a partially sold approval row marked "sold".
                // Include it only when linked sale lines prove some approved quantity remains.
                $pendingItemFilter = function ($q) {
                    return $this->filterApprovalItemsWithAvailableQuantity($q);
                };

                $query = ApprovalHeader::with([
                    'customer',
                    'creator',
                    'items' => function ($q) use ($pendingItemFilter) {
                        $pendingItemFilter($q)->with(['item', 'itemSet.item', 'legacyItemSet.item']);
                    },
                ])
                    ->withCount([
                        'items as active_items_count' => $pendingItemFilter,
                    ])
                    ->withSum([
                        'items as active_quantity' => $pendingItemFilter,
                    ], 'qty')
                    ->withSum([
                        'items as active_gross_weight' => $pendingItemFilter,
                    ], 'gross_weight')
                ->withSum(['items as active_other_weight' => $pendingItemFilter], 'other_weight')
                    ->withSum([
                        'items as active_net_weight' => $pendingItemFilter,
                    ], 'net_weight')
                    ->withSum([
                        'items as active_fine_weight' => $pendingItemFilter,
                    ], 'total_fine_weight')
                    ->withSum([
                        'items as active_metal_amount' => $pendingItemFilter,
                    ], 'metal_amount')
                    ->withSum([
                        'items as active_labour_amount' => $pendingItemFilter,
                    ], 'labour_amount')
                    ->withSum([
                        'items as active_other_amount' => $pendingItemFilter,
                    ], 'other_amount')
                    ->withSum([
                        'items as active_item_amount' => $pendingItemFilter,
                    ], 'total_amount')
                    ->whereIn('company_id', $companies->keys())
                    ->whereHas('items', $pendingItemFilter)
                    ->whereHas('customer', function ($customer) {
                        $customer->saleParties();
                    })
                    ->orderByDesc('approval_date')
                    ->orderByDesc('id');

                if ($request->filled('customer_id')) {
                    $query->where('customer_id', $request->customer_id);
                }

                if (!empty($fromDate) && !empty($toDate)) {
                    $query->whereBetween('approval_date', [$fromDate, $toDate]);
                }

                $summaryRows = (clone $query)->get();
                $summary = [
                    'total_qty' => (int) $summaryRows->sum(fn($row) => $this->remainingHeaderTotals($row)['qty']),
                    'total_gross_wt' => (float) $summaryRows->sum(fn($row) => $this->remainingHeaderTotals($row)['gross']),
                    'total_other_wt' => (float) $summaryRows->sum(fn($row) => $this->remainingHeaderTotals($row)['other']),
                    'total_net_wt' => (float) $summaryRows->sum(fn($row) => $this->remainingHeaderTotals($row)['net']),
                ];

                $table = datatables()->of($query)
                    ->filter(function ($q) use ($request) {
                        $searchValue = trim((string) data_get($request->input('search'), 'value', ''));
                        if ($searchValue === '') {
                            return;
                        }

                        $q->where(function ($innerQ) use ($searchValue) {
                            $innerQ->where('approval_no', 'like', "%{$searchValue}%")
                                ->orWhereHas('customer', function ($cq) use ($searchValue) {
                                    $cq->where('name', 'like', "%{$searchValue}%");
                                })
                                ->orWhereHas('items', function ($iq) use ($searchValue) {
                                    $iq->where('qr_code', 'like', "%{$searchValue}%")
                                        ->orWhere('huid', 'like', "%{$searchValue}%")
                                        ->orWhereHas('item', function ($itemQ) use ($searchValue) {
                                            $itemQ->where('item_name', 'like', "%{$searchValue}%");
                                        })
                                        ->orWhereHas('itemSet.item', function ($itemQ) use ($searchValue) {
                                            $itemQ->where('item_name', 'like', "%{$searchValue}%");
                                        })
                                        ->orWhereHas('legacyItemSet.item', function ($itemQ) use ($searchValue) {
                                            $itemQ->where('item_name', 'like', "%{$searchValue}%");
                                        });
                                })
                                ->orWhereRaw("DATE_FORMAT(approval_date, '%d-%m-%Y') like ?", ["%{$searchValue}%"])
                                ->orWhereRaw("DATE_FORMAT(approval_date, '%Y-%m-%d') like ?", ["%{$searchValue}%"]);

                            if (preg_match('/^\d{2}\-\d{2}\-\d{4}$/', $searchValue)) {
                                try {
                                    $searchDate = Carbon::createFromFormat('d-m-Y', $searchValue)->format('Y-m-d');
                                    $innerQ->orWhereDate('approval_date', $searchDate);
                                } catch (\Throwable $e) {
                                }
                            } elseif (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $searchValue)) {
                                $innerQ->orWhereDate('approval_date', $searchValue);
                            }
                        });
                    })
                    ->addIndexColumn()
                ->addColumn('company_name', fn ($row) => $companies[($row->company_id ?? $row->approval->company_id)]->name)
                ->addColumn('company_id', fn ($row) => ($row->company_id ?? $row->approval->company_id))
                    ->addColumn('voucher_qr_image_url', fn($row) => \App\Services\VoucherQr::approvalImageUrl((int) $row->id))
                    ->addColumn('approval_no', fn($row) => $row->approval_no ?? '-')
                    ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
                    ->addColumn('approval_date', fn($row) => $row->approval_date ? Carbon::parse($row->approval_date)->format('d-m-Y') : '-')
                    ->addColumn('item_names', function ($row) {
                        $names = collect($row->items ?? [])
                            ->map(function ($it) {
                                $set = $it->itemSet ?? $it->legacyItemSet;
                                return optional(optional($set)->item)->item_name
                                    ?? optional($it->item)->item_name;
                            })
                            ->filter()
                            ->unique()
                            ->values();

                        return $names->isEmpty() ? '-' : $names->implode(', ');
                    })
                    ->addColumn('qr_code', fn() => '-')
                    ->addColumn('total_qty', fn($row) => $this->remainingHeaderTotals($row)['qty'])
                    ->addColumn('total_gross_weight', fn($row) => number_format($this->remainingHeaderTotals($row)['gross'], 3))
                    ->addColumn('total_other_weight', fn($row) => number_format($this->remainingHeaderTotals($row)['other'], 3))
                    ->addColumn('total_net_weight', fn($row) => number_format($this->remainingHeaderTotals($row)['net'], 3))
                    ->addColumn('total_fine_weight', fn($row) => number_format($this->remainingHeaderTotals($row)['fine'], 3))
                    ->addColumn('total_metal_amount', fn($row) => number_format($this->remainingHeaderTotals($row)['metal'], 2))
                    ->addColumn('total_labour_amount', fn($row) => number_format($this->remainingHeaderTotals($row)['labour'], 2))
                    ->addColumn('total_other_amount', fn($row) => number_format($this->remainingHeaderTotals($row)['other_amount'], 2))
                    ->addColumn('total_amount', fn($row) => number_format($this->remainingHeaderTotals($row)['amount'], 2))
                    ->addColumn('creator_name', fn($row) => optional($row->creator)->name ?? '-')
                    ->addColumn('modified_at', fn($row) => $row->updated_at ? Carbon::parse($row->updated_at)->format('d-m-Y h:i A') : '-')
                    ->addColumn('modified_count', fn($row) => (int) ($row->modified_count ?? 0))
                    ->addColumn('status', fn($row) => $row->status_badge)
                    ->addColumn('action', function ($row) use ($companies) {
                    $slug = $companies[$row->company_id ?? $row->approval->company_id]->slug;
                        $encryptedId = Crypt::encryptString((string) $row->id);
                        $url = route('company.approval.view', [$slug, $encryptedId]);
                        $pdfUrl = route('company.approval.pdf', [$slug, $encryptedId]);
                        $editBtn = '';
                        if (in_array((string) $row->status, ['open', 'partial'], true)) {
                            $editUrl = route('company.approval.edit', [$slug, $encryptedId]);
                            $editBtn = '<a href="' . $editUrl . '" class="btn btn-sm btn-warning me-1">Edit</a>';
                        }

                        return $editBtn . '<a href="' . $url . '" class="btn btn-sm btn-info me-1">View</a>
                                <a href="' . $pdfUrl . '" class="btn btn-sm btn-primary" target="_blank">PDF</a>';
                    })
                    ->rawColumns(['status', 'action'])
                    ->with('summary', $summary)
                    ;
            return \App\Services\SalesBranchExport::respond($table, $request, $companies, 'approval');
            }

            $query = ApprovalItem::with([
                'approval.customer',
                'approval.creator',
                'item',
                'itemSet.item',
                'legacyItemSet.item',
            ])
                ->join('approval_headers', 'approval_headers.id', '=', 'approval_items.approval_id')
                ->whereIn('approval_headers.company_id', $companies->keys())
                ->where(function ($availableQuery) {
                    $this->filterApprovalItemsWithAvailableQuantity($availableQuery);
                })
                ->whereHas('approval.customer', function ($customer) {
                    $customer->saleParties();
                })
                ->select('approval_items.*')
                ->orderByDesc('approval_headers.approval_date')
                ->orderByDesc('approval_items.id');

            if ($request->filled('customer_id')) {
                $query->where('approval_headers.customer_id', $request->customer_id);
            }

            if (!empty($fromDate) && !empty($toDate)) {
                $query->whereBetween('approval_headers.approval_date', [$fromDate, $toDate]);
            }

            $summaryRows = (clone $query)->get();
            $summary = [
                'total_qty' => (int) $summaryRows->sum(fn($row) => \App\Services\ApprovalItemAvailability::availableQuantity($row)),
                'total_gross_wt' => (float) $summaryRows->sum(fn($row) => \App\Services\ApprovalItemAvailability::remainingValue($row->gross_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row))),
                'total_other_wt' => (float) $summaryRows->sum(fn($row) => \App\Services\ApprovalItemAvailability::remainingValue($row->other_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row))),
                'total_net_wt' => (float) $summaryRows->sum(fn($row) => \App\Services\ApprovalItemAvailability::remainingValue($row->net_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row))),
            ];

            $table = datatables()->of($query)
                ->filter(function ($q) use ($request) {
                    $searchValue = trim((string) data_get($request->input('search'), 'value', ''));
                    if ($searchValue === '') {
                        return;
                    }

                    $q->where(function ($innerQ) use ($searchValue) {
                        $innerQ->where('approval_headers.approval_no', 'like', "%{$searchValue}%")
                            ->orWhere('approval_items.qr_code', 'like', "%{$searchValue}%")
                            ->orWhere('approval_items.huid', 'like', "%{$searchValue}%")
                            ->orWhereHas('approval.customer', function ($cq) use ($searchValue) {
                                $cq->where('name', 'like', "%{$searchValue}%");
                            })
                            ->orWhereHas('item', function ($itemQ) use ($searchValue) {
                                $itemQ->where('item_name', 'like', "%{$searchValue}%");
                            })
                            ->orWhereHas('itemSet.item', function ($itemQ) use ($searchValue) {
                                $itemQ->where('item_name', 'like', "%{$searchValue}%");
                            })
                            ->orWhereHas('legacyItemSet.item', function ($itemQ) use ($searchValue) {
                                $itemQ->where('item_name', 'like', "%{$searchValue}%");
                            })
                            ->orWhereRaw("DATE_FORMAT(approval_headers.approval_date, '%d-%m-%Y') like ?", ["%{$searchValue}%"])
                            ->orWhereRaw("DATE_FORMAT(approval_headers.approval_date, '%Y-%m-%d') like ?", ["%{$searchValue}%"]);

                        if (preg_match('/^\d{2}\-\d{2}\-\d{4}$/', $searchValue)) {
                            try {
                                $searchDate = Carbon::createFromFormat('d-m-Y', $searchValue)->format('Y-m-d');
                                $innerQ->orWhereDate('approval_headers.approval_date', $searchDate);
                            } catch (\Throwable $e) {
                            }
                        } elseif (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $searchValue)) {
                            $innerQ->orWhereDate('approval_headers.approval_date', $searchValue);
                        }
                    });
                })
                ->addIndexColumn()
                ->addColumn('company_name', fn ($row) => $companies[($row->company_id ?? $row->approval->company_id)]->name)
                ->addColumn('company_id', fn ($row) => ($row->company_id ?? $row->approval->company_id))
                ->addColumn('voucher_qr_image_url', fn($row) => $row->approval ? \App\Services\VoucherQr::approvalImageUrl((int) $row->approval->id) : null)
                ->addColumn('approval_no', fn($row) => optional($row->approval)->approval_no ?? '-')
                ->addColumn('customer_name', fn($row) => optional(optional($row->approval)->customer)->name ?? '-')
                ->addColumn('approval_date', function ($row) {
                    $approval = $row->approval;
                    return $approval && $approval->approval_date
                        ? Carbon::parse($approval->approval_date)->format('d-m-Y')
                        : '-';
                })
                ->addColumn('item_names', function ($row) {
                    $set = $row->itemSet ?? $row->legacyItemSet;
                    return optional(optional($set)->item)->item_name
                        ?? optional($row->item)->item_name
                        ?? '-';
                })
                ->addColumn('qr_code', fn($row) => $row->qr_code ?: '-')
                ->addColumn('total_qty', fn($row) => \App\Services\ApprovalItemAvailability::availableQuantity($row))
                ->addColumn('total_gross_weight', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->gross_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 3))
                ->addColumn('total_other_weight', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->other_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 3))
                ->addColumn('total_net_weight', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->net_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 3))
                ->addColumn('total_fine_weight', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->total_fine_weight ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 3))
                ->addColumn('total_metal_amount', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->metal_amount ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 2))
                ->addColumn('total_labour_amount', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->labour_amount ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 2))
                ->addColumn('total_other_amount', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->other_amount ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 2))
                ->addColumn('total_amount', fn($row) => number_format(\App\Services\ApprovalItemAvailability::remainingValue($row->total_amount ?? 0, max(1, (int) ($row->qty ?? 1)), \App\Services\ApprovalItemAvailability::availableQuantity($row)), 2))
                ->addColumn('creator_name', fn($row) => optional(optional($row->approval)->creator)->name ?? '-')
                ->addColumn('modified_at', function ($row) {
                    $approval = $row->approval;
                    return $approval && $approval->updated_at
                        ? Carbon::parse($approval->updated_at)->format('d-m-Y h:i A')
                        : '-';
                })
                ->addColumn('modified_count', fn($row) => (int) (optional($row->approval)->modified_count ?? 0))
                ->addColumn('status', fn() => '<span class="badge bg-warning">Pending</span>')
                ->addColumn('action', function ($row) use ($companies) {
                    $slug = $companies[$row->company_id ?? $row->approval->company_id]->slug;
                    $approval = $row->approval;
                    if (!$approval) {
                        return '-';
                    }

                    $encryptedId = Crypt::encryptString((string) $approval->id);
                    $url = route('company.approval.view', [$slug, $encryptedId]);
                    $pdfUrl = route('company.approval.pdf', [$slug, $encryptedId]);
                    $editBtn = '';
                    if (in_array((string) $approval->status, ['open', 'partial'], true)) {
                        $editUrl = route('company.approval.edit', [$slug, $encryptedId]);
                        $editBtn = '<a href="' . $editUrl . '" class="btn btn-sm btn-warning me-1">Edit</a>';
                    }
                    return $editBtn . '<a href="' . $url . '" class="btn btn-sm btn-info me-1">View</a>
                            <a href="' . $pdfUrl . '" class="btn btn-sm btn-primary" target="_blank">PDF</a>';
                })
                ->rawColumns(['status', 'action'])
                ->with('summary', $summary)
                ;
            return \App\Services\SalesBranchExport::respond($table, $request, $companies, 'approval');
        }

        return view('company.approval.index', compact('company', 'customers'));
    }

    public function exportListPdf(Request $request, $slug)
    {
        $request->merge(['export'=>'pdf']);
        return $this->index($request, $slug);
    }

    private function resolveEncryptedCustomerFilterId(Request $request, int $companyId): ?int
    {
        if (!$request->filled('customer_id')) {
            return null;
        }

        $customerIdValue = (string) $request->input('customer_id');
        try {
            $customerId = ctype_digit($customerIdValue)
                ? (int) $customerIdValue
                : (int) Crypt::decryptString($customerIdValue);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
            abort(404);
        }

        abort_unless(
            Customer::where('company_id', $companyId)
                ->saleParties()
                ->whereKey($customerId)
                ->exists(),
            404
        );

        return $customerId;
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $customers = Customer::where('company_id', $company->id)
            ->saleParties()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $itemsQuery = Item::where('company_id', $company->id)
            ->orderBy('item_name');

        if (Schema::hasColumn('items', 'is_active')) {
            $itemsQuery->where('is_active', 1);
        }

        $items = $itemsQuery->get(['id', 'item_name', 'item_code']);

        return view('company.approval.create', compact('company', 'customers', 'items'));
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        $approval = ApprovalHeader::with(['items.itemSet.item', 'items.legacyItemSet.item'])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        if (!in_array((string) $approval->status, ['open', 'partial'], true)) {
            return redirect()
                ->route('company.approval.index', $company->slug)
                ->with('error', 'Only open/partial approvals can be edited.');
        }

        $customers = Customer::where('company_id', $company->id)
            ->saleParties()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $itemsQuery = Item::where('company_id', $company->id)
            ->orderBy('item_name');

        if (Schema::hasColumn('items', 'is_active')) {
            $itemsQuery->where('is_active', 1);
        }

        $items = $itemsQuery->get(['id', 'item_name', 'item_code']);

        $editableItems = $approval->items
            ->where('status', 'pending')
            ->values()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $qty = max(1, (int) ($row->qty ?? 1));
                $gross = (float) ($row->gross_weight ?? 0) / $qty;
                $otherWeight = (float) ($row->other_weight ?? 0) / $qty;
                $netWeight = (float) ($row->net_weight ?? max(0, $gross - $otherWeight)) / $qty;
                $purity = (float) ($row->purity ?? 0);
                $wastePercent = (float) ($row->waste_percent ?? 0);
                $netPurity = max(0, $purity + $wastePercent);
                $fineWeight = $netWeight * $netPurity / 100;
                $salePrice = \App\Services\ApprovalItemPricing::unitSalePrice($row);

                return [
                    'itemset_id' => (int) ($row->itemset_id ?? optional($itemSet)->id),
                    'item_id' => (int) ($row->item_id ?? optional($itemSet)->item_id),
                    'item_name' => optional(optional($itemSet)->item)->item_name,
                    'huid' => $row->huid ?? optional($itemSet)->HUID,
                    'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                    'qty' => $qty,
                    'available_quantity' => min((int) (optional($itemSet)->quantity ?? 1), (int) (optional($itemSet)->available_quantity ?? 0) + $qty),
                    'gross_weight' => $gross,
                    'other_weight' => $otherWeight,
                    'net_weight' => $netWeight,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'total_fine_weight' => $fineWeight,
                    'metal_rate' => (float) ($row->metal_rate ?? 0),
                    'metal_amount' => (float) ($row->metal_amount ?? 0) / $qty,
                    'labour_rate' => (float) ($row->labour_rate ?? 0),
                    'labour_amount' => (float) ($row->labour_amount ?? 0) / $qty,
                    'other_amount' => (float) ($row->other_amount ?? 0) / $qty,
                    'sale_price' => $salePrice,
                    'total_amount' => $salePrice > 0 ? $salePrice : (float) ($row->total_amount ?? 0) / $qty,
                    'remarks' => (string) ($row->remarks ?? ''),
                    'other_charges' => $this->decodeOtherChargeDetails($row->other_charge_details ?? null),
                ];
            })
            ->sortBy(function ($row) {
                $label = strtoupper(trim((string) ($row['qr_code'] ?? '')));
                if ($label === '') {
                    $label = strtoupper(trim((string) ($row['huid'] ?? '')));
                }

                if ($label === '') {
                    return 'ZZZ99999999';
                }

                if (preg_match('/^([A-Z]+)\s*0*(\d+)$/', $label, $m)) {
                    $prefix = $m[1] ?? '';
                    $num = (int) ($m[2] ?? 0);
                    return sprintf('%s%010d', $prefix, $num);
                }

                return $label;
            })
            ->values();

        return view('company.approval.create', [
            'company' => $company,
            'customers' => $customers,
            'items' => $items,
            'approval' => $approval,
            'editableItems' => $editableItems,
            'isEdit' => true,
        ]);
    }

    public function getItemSets($slug, $itemId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $data = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('item_id', $itemId)
            ->where('is_final', 1)
            ->where('available_quantity', '>', 0)
            ->get();

        return response()->json($data);
    }

    public function searchItemSets($slug, Request $request)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $keyword = trim((string) $request->keyword);
        $itemId = (int) $request->input('item_id', 0);
        $limit = max(10, min((int) $request->input('limit', 1000), 2000));

        $data = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('is_final', 1)
            ->where('available_quantity', '>', 0)
            ->when($itemId > 0, function ($q) use ($itemId) {
                $q->where('item_id', $itemId);
            })
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($inner) use ($keyword) {
                    $inner->where('HUID', 'LIKE', "%{$keyword}%")
                        ->orWhere('qr_code', 'LIKE', "%{$keyword}%")
                        ->orWhere('barcode', 'LIKE', "%{$keyword}%")
                        ->orWhereHas('item', function ($q2) use ($keyword) {
                            $q2->where('item_name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
        $itemSetItemIds = $data->pluck('item_id')->filter()->unique()->values()->all();
        $itemOnly = Item::query()
            ->where('company_id', $company->id)
            ->when($itemId > 0, function ($q) use ($itemId) {
                $q->where('id', $itemId);
            })
            ->whereNotIn('id', $itemSetItemIds)
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($inner) use ($keyword) {
                    $inner->where('item_name', 'like', "%{$keyword}%")
                        ->orWhere('item_code', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('item_name')
            ->limit($limit)
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);

        if (Schema::hasColumn('items', 'is_active')) {
            $itemOnly = Item::query()
                ->where('company_id', $company->id)
                ->where('is_active', 1)
                ->when($itemId > 0, function ($q) use ($itemId) {
                    $q->where('id', $itemId);
                })
                ->whereNotIn('id', $itemSetItemIds)
                ->when($keyword !== '', function ($q) use ($keyword) {
                    $q->where(function ($inner) use ($keyword) {
                        $inner->where('item_name', 'like', "%{$keyword}%")
                            ->orWhere('item_code', 'like', "%{$keyword}%");
                    });
                })
                ->orderBy('item_name')
                ->limit($limit)
                ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);
        }

        $itemOnlyRows = $itemOnly->map(function ($item) {
            return (object) [
                'id' => 0,
                'item_id' => (int) $item->id,
                'item' => (object) [
                    'id' => (int) $item->id,
                    'item_name' => (string) ($item->item_name ?? ''),
                    'item_code' => (string) ($item->item_code ?? ''),
                    'outward_purity' => (float) ($item->outward_purity ?? 0),
                    'labour_rate' => (float) ($item->labour_rate ?? 0),
                ],
                'HUID' => '',
                'qr_code' => (string) ($item->item_code ?? ''),
                'barcode' => '',
                'gross_weight' => 0,
                'other' => 0,
                'net_weight' => 0,
                'purity' => (float) ($item->outward_purity ?? 0),
                'sale_labour_rate' => (float) ($item->labour_rate ?? 0),
                'sale_other' => 0,
                'is_item_only' => true,
            ];
        });

        $rows = $data->map(function ($row) {
            $row->is_item_only = false;
            return $row;
        })->concat($itemOnlyRows)->values();

        return response()->json($rows);
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        DB::beginTransaction();

        try {
            $request->validate([
                'customer_id' => 'required|integer|exists:customers,id',
                'items' => 'required|array|min:1',
            ]);

            $customerExists = Customer::where('company_id', $company->id)
                ->saleParties()
                ->where('id', (int) $request->customer_id)
                ->exists();
            if (!$customerExists) {
                throw new \Exception('Invalid customer for this company.');
            }

            $approval = ApprovalHeader::create([
                'company_id' => $company->id,
                'customer_id' => $request->customer_id,
                'approval_no' => \App\Services\VoucherSeries::next($company->id, 'APP'),
                'approval_date' => now(),
                'status' => 'open',
                'remarks' => $request->input('voucher_remarks'),
                'employee_id' => optional(auth()->user())->id,
                'modified_count' => 0,
            ]);

            $items = collect($request->items ?? []);
            if ($items->isEmpty()) {
                throw new \Exception('No items provided');
            }

            foreach ($items as $row) {
                $itemSetId = (int) ($row['itemset_id'] ?? $row['id'] ?? 0);
                if (!$itemSetId) {
                    continue;
                }

                $itemSet = TransactionItemGuard::lockItemSetForUse($company->id, $itemSetId)->load('item');

                $gross = (float) ($row['gross_weight'] ?? $itemSet->gross_weight ?? 0);
                $otherWeight = (float) ($row['other_weight'] ?? $itemSet->other ?? 0);
                $netWeight = (float) ($row['net_weight'] ?? ($gross - $otherWeight));
                $purity = (float) ($row['purity'] ?? optional($itemSet->item)->outward_purity ?? 0);
                $wastePercent = (float) ($row['waste_percent'] ?? 0);
                $qty = max(1, (int) ($row['qty'] ?? 1));
                if ($qty > max(0, (int) $itemSet->available_quantity)) {
                    throw new \RuntimeException('Requested approval quantity exceeds available label stock.');
                }
                $netPurity = max(0, $purity + $wastePercent);
                $totalFineWeight = $netWeight * $netPurity / 100;
                $metalRate = (float) ($row['metal_rate'] ?? 0);
                $metalAmount = (float) ($row['metal_amount'] ?? ($netWeight * $metalRate));
                $labourRate = (float) ($row['labour_rate'] ?? $itemSet->sale_labour_rate ?? optional($itemSet->item)->labour_rate ?? 0);
                $labourAmount = (float) ($row['labour_amount'] ?? ($netWeight * $labourRate));
                $otherAmount = $this->resolveOtherAmount($row['other_amount'] ?? null, $itemSet->sale_other ?? 0);
                $totalAmount = (float) ($row['total_amount'] ?? (($metalAmount + $labourAmount + $otherAmount) * $qty));
                if ((float) ($itemSet->billing_sale_price ?? 0) > 0) {
                    $metalAmount = $labourAmount = $otherAmount = $totalFineWeight = 0;
                    $totalAmount = (float) $itemSet->billing_sale_price * $qty;
                }

                ApprovalItem::create([
                    'approval_id' => $approval->id,
                    'itemset_id' => $itemSet->id,
                    'item_id' => $itemSet->item_id,
                    'qty' => $qty,
                    'sale_price' => $itemSet->billing_sale_price,
                    'huid' => $row['huid'] ?? $itemSet->HUID,
                    'qr_code' => $row['qr_code'] ?? $itemSet->qr_code,
                    'gross_weight' => $gross * $qty,
                    'other_weight' => $otherWeight * $qty,
                    'net_weight' => $netWeight * $qty,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'total_fine_weight' => $totalFineWeight * $qty,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount * $qty,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount * $qty,
                    'other_amount' => $otherAmount * $qty,
                    'other_charge_details' => $this->normalizeOtherChargeDetails($row['other_charge_details'] ?? $row['other_charges'] ?? null),
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'remarks' => $row['remarks'] ?? null,
                ]);

                \App\Services\BatchInventory::consume($itemSet, $qty);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval Created Successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        DB::beginTransaction();

        try {
            $approval = ApprovalHeader::with('items')
                ->where('company_id', $company->id)
                ->findOrFail($id);

            if (!in_array((string) $approval->status, ['open', 'partial'], true)) {
                throw new \Exception('Only open/partial approvals can be edited.');
            }

            $request->validate([
                'customer_id' => 'required|integer|exists:customers,id',
                'items' => 'required|array|min:1',
            ]);

            $customerExists = Customer::where('company_id', $company->id)
                ->saleParties()
                ->where('id', (int) $request->customer_id)
                ->exists();
            if (!$customerExists) {
                throw new \Exception('Invalid customer for this company.');
            }

            $approval->update([
                'customer_id' => (int) $request->customer_id,
                'remarks' => $request->input('voucher_remarks'),
                'modified_count' => ((int) ($approval->modified_count ?? 0)) + 1,
            ]);

            $incomingRows = collect($request->items ?? []);
            $incomingItemsetIds = $incomingRows
                ->map(fn($row) => (int) ($row['itemset_id'] ?? $row['id'] ?? 0))
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values();

            $pendingItems = ApprovalItem::where('approval_id', $approval->id)
                ->where('status', 'pending')
                ->get();

            $pendingByItemset = $pendingItems->keyBy('itemset_id');

            // Remove pending rows deleted in edit screen and free those labels.
            $toRemove = $pendingItems->filter(function ($row) use ($incomingItemsetIds) {
                return !$incomingItemsetIds->contains((int) $row->itemset_id);
            });

            foreach ($toRemove as $row) {
                if (!empty($row->itemset_id)) {
                    $removedSet = ItemSet::where('company_id', $company->id)
                        ->where('id', (int) $row->itemset_id)
                        ->lockForUpdate()
                        ->first();
                    if ($removedSet) { \App\Services\BatchInventory::restore($removedSet, max(1, (int) ($row->qty ?? 1))); }
                }
                $row->delete();
            }

            foreach ($incomingRows as $row) {
                $itemSetId = (int) ($row['itemset_id'] ?? $row['id'] ?? 0);
                if ($itemSetId <= 0) {
                    continue;
                }

                $itemSet = TransactionItemGuard::lockItemSetForUse(
                    $company->id,
                    $itemSetId,
                    $pendingByItemset->has($itemSetId)
                )->load('item');

                // If this label was not already pending in this approval, it must be free.
                if (!$pendingByItemset->has($itemSetId) && (int) $itemSet->available_quantity <= 0) {
                    throw new \Exception("Item already used: {$itemSet->qr_code}");
                }

                $payload = $this->buildApprovalItemPayload($row, $itemSet);
                $newQty = (int) $payload['qty'];

                if ($pendingByItemset->has($itemSetId)) {
                    $oldRow = $pendingByItemset->get($itemSetId);
                    $oldQty = max(1, (int) ($oldRow->qty ?? 1));
                    $availableBefore = max(0, (int) $itemSet->available_quantity);
                    if ($newQty > $oldQty && ($newQty - $oldQty) > $availableBefore) {
                        throw new \RuntimeException('Requested approval quantity exceeds available label stock.');
                    }
                    $newAvailable = min((int) $itemSet->quantity, $availableBefore + $oldQty - $newQty);
                    $itemSet->update(['available_quantity' => $newAvailable, 'is_sold' => $newAvailable === 0 ? 1 : 0]);
                    $oldRow->update($payload);
                } else {
                    ApprovalItem::create(array_merge($payload, [
                        'approval_id' => $approval->id,
                        'itemset_id' => $itemSet->id,
                        'item_id' => $itemSet->item_id,
                        'status' => 'pending',
                    ]));
                    \App\Services\BatchInventory::consume($itemSet, $newQty);
                }
            }

            $this->refreshApprovalStatusByHeader((int) $approval->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function view($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        $approval = ApprovalHeader::with('items.itemSet.item', 'items.legacyItemSet.item')
            ->where('company_id', $company->id)
            ->findOrFail($id);

        return view('company.approval.view', compact('company', 'approval'));
    }

    public function pdf(Request $request, $slug, $encryptedId)
    {
        $request->validate(['status' => 'nullable|in:all,pending,partial,sold,returned']);
        $pdfStatus = $request->input('status') ?: 'partial';
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        $approval = ApprovalHeader::with([
            'customer',
            'items' => function ($q) {
                $q->orderBy('id', 'asc');
            },
            'items.itemSet.item',
            'items.legacyItemSet.item'
        ])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $printedBy = optional(auth()->user())->name
            ?? optional($approval->creator)->name
            ?? 'System';
        $printedAt = now();

        $pdf = Pdf::loadView('company.approval.approval_pdf', compact('company', 'approval', 'printedBy', 'printedAt', 'pdfStatus'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Approval-' . $approval->approval_no . '.pdf');
    }

    public function itemsData(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        $data = ApprovalItem::with('itemSet.item', 'legacyItemSet.item')
            ->where('approval_id', $id)
            ->whereHas('approval', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            });

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('item_name', function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                return optional(optional($itemSet)->item)->item_name ?? '-';
            })
            ->addColumn('gross_weight', fn($row) => $row->gross_weight)
            ->addColumn('net_weight', fn($row) => $row->net_weight)
            ->addColumn('status', function ($row) {
                if ($row->status == 'pending') {
                    return '<span class="badge bg-warning">Pending</span>';
                } elseif ($row->status == 'sold') {
                    return '<span class="badge bg-success">Sold</span>';
                }

                return '<span class="badge bg-danger">Returned</span>';
            })
            ->addColumn('action', function ($row) {
                if ($row->status == 'pending') {
                    return '<input type="checkbox" class="selectItem" value="' . $row->id . '">';
                }
                return '-';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function sale(Request $request, $slug)
    {
        DB::beginTransaction();

        try {
            $company = Company::whereSlug($slug)->firstOrFail();
            $ids = collect($request->items ?? [])
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                throw new \Exception('No approval items selected.');
            }

            foreach ($ids as $id) {
                $item = TransactionItemGuard::lockApprovalItemForSale($company->id, (int) $id);
                $item->update(['status' => 'sold']);
            }

            $this->updateApprovalStatus($ids->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Items sold successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function returnItems(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $customerId = $request->customer_id;
        $items = ApprovalItem::with('itemSet.item', 'legacyItemSet.item', 'approval:id,approval_no')
            ->whereHas('approval', function ($q) use ($company, $customerId) {
                $q->where('company_id', $company->id)
                    ->where('customer_id', $customerId);
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
            ->when(Schema::hasColumn('sale_return_items', 'approval_item_id'), function ($query) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('sale_return_items')
                        ->whereColumn('sale_return_items.approval_item_id', 'approval_items.id');
                });
            })
            ->get();

        return response()->json($items->map(function ($row) {
            $itemSet = $row->itemSet ?? $row->legacyItemSet;
            $item = optional($itemSet)->item;
            $gross = (float) ($row->gross_weight ?? 0);
            $otherWeight = (float) ($row->other_weight ?? 0);
            $net = (float) ($row->net_weight ?? ($gross - $otherWeight));
            $purity = (float) ($row->purity ?? optional($item)->outward_purity ?? 0);
            $wastePercent = (float) ($row->waste_percent ?? 0);
            $netPurity = max(0, $purity + $wastePercent);
            $fineWeight = $net * $netPurity / 100;
            $metalRate = (float) ($row->metal_rate ?? 0);
            $metalAmount = (float) ($row->metal_amount ?? ($net * $metalRate));
            $labourRate = (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
            $labourAmount = (float) ($row->labour_amount ?? ($net * $labourRate));
            $otherAmount = $this->resolveOtherAmount($row->other_amount ?? null, optional($itemSet)->sale_other ?? 0);
            $totalAmount = $this->resolveTotalAmount($row->total_amount ?? null, $metalAmount, $labourAmount, $otherAmount);
            return [
                'id' => $row->id,
                'qty' => max(1, (int) ($row->qty ?? 1)),
                'approval_id' => $row->approval_id,
                'approval_no' => $row->approval?->approval_no,
                'item_id' => $row->item_id ?? optional($itemSet)->item_id,
                'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                'huid' => $row->huid ?? optional($itemSet)->HUID,
                'name' => optional(optional($itemSet)->item)->item_name,
                'gross_weight' => number_format($gross, 3, '.', ''),
                'other_weight' => number_format($otherWeight, 3, '.', ''),
                'net_weight' => number_format($net, 3, '.', ''),
                'purity' => number_format($purity, 3, '.', ''),
                'waste_percent' => number_format($wastePercent, 3, '.', ''),
                'net_purity' => number_format($netPurity, 3, '.', ''),
                'fine_weight' => number_format($fineWeight, 3, '.', ''),
                'metal_rate' => number_format($metalRate, 2, '.', ''),
                'metal_amount' => number_format($metalAmount, 2, '.', ''),
                'labour_rate' => number_format($labourRate, 2, '.', ''),
                'labour_amount' => number_format($labourAmount, 2, '.', ''),
                'other_amount' => number_format($otherAmount, 2, '.', ''),
                // ApprovalItem.sale_price is a per-piece rate. Older rows may
                // contain a rate that was accidentally split during a partial
                // return, so resolve it from the linked label when available.
                'sale_price' => number_format(\App\Services\ApprovalItemPricing::unitSalePrice($row), 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'remarks' => (string) ($row->remarks ?? ''),
            ];
        }));
    }

    private function updateApprovalStatus($itemIds)
    {
        $first = ApprovalItem::whereIn('id', $itemIds)->first();
        if (!$first) {
            return;
        }
        $approvalId = $first->approval_id;

        $total = ApprovalItem::where('approval_id', $approvalId)->count();
        $done = ApprovalItem::where('approval_id', $approvalId)
            ->whereIn('status', ['sold', 'returned'])
            ->count();

        $status = $total == $done ? 'closed' : 'partial';

        ApprovalHeader::where('id', $approvalId)->update([
            'status' => $status,
        ]);
    }

    private function refreshApprovalStatusByHeader(int $approvalId): void
    {
        $total = ApprovalItem::where('approval_id', $approvalId)->count();
        $done = ApprovalItem::where('approval_id', $approvalId)
            ->whereIn('status', ['sold', 'returned'])
            ->count();

        $status = 'open';
        if ($total > 0 && $done === $total) {
            $status = 'closed';
        } elseif ($done > 0 && $done < $total) {
            $status = 'partial';
        }

        ApprovalHeader::where('id', $approvalId)->update([
            'status' => $status,
        ]);
    }

    private function buildApprovalItemPayload(array $row, ItemSet $itemSet): array
    {
        $gross = (float) ($row['gross_weight'] ?? $itemSet->gross_weight ?? 0);
        $otherWeight = (float) ($row['other_weight'] ?? $itemSet->other ?? 0);
        $netWeight = (float) ($row['net_weight'] ?? ($gross - $otherWeight));
        $purity = (float) ($row['purity'] ?? optional($itemSet->item)->outward_purity ?? 0);
        $wastePercent = (float) ($row['waste_percent'] ?? 0);
        $qty = max(1, (int) ($row['qty'] ?? 1));
        $netPurity = max(0, $purity + $wastePercent);
        $totalFineWeight = $netWeight * $netPurity / 100;
        $metalRate = (float) ($row['metal_rate'] ?? 0);
        $metalAmount = (float) ($row['metal_amount'] ?? ($netWeight * $metalRate));
        $labourRate = (float) ($row['labour_rate'] ?? $itemSet->sale_labour_rate ?? optional($itemSet->item)->labour_rate ?? 0);
        $labourAmount = (float) ($row['labour_amount'] ?? ($netWeight * $labourRate));
        $otherAmount = $this->resolveOtherAmount($row['other_amount'] ?? null, $itemSet->sale_other ?? 0);
        $totalAmount = (float) ($row['total_amount'] ?? (($metalAmount + $labourAmount + $otherAmount) * $qty));
        if ((float) ($itemSet->billing_sale_price ?? 0) > 0) {
            $metalAmount = $labourAmount = $otherAmount = $totalFineWeight = 0;
            $totalAmount = (float) $itemSet->billing_sale_price * $qty;
        }

        return [
            'huid' => $row['huid'] ?? $itemSet->HUID,
            'qr_code' => $row['qr_code'] ?? $itemSet->qr_code,
            'qty' => $qty,
            'sale_price' => $itemSet->billing_sale_price,
            'gross_weight' => $gross * $qty,
            'other_weight' => $otherWeight * $qty,
            'net_weight' => $netWeight * $qty,
            'purity' => $purity,
            'waste_percent' => $wastePercent,
            'net_purity' => $netPurity,
            'total_fine_weight' => $totalFineWeight * $qty,
            'metal_rate' => $metalRate,
            'metal_amount' => $metalAmount * $qty,
            'labour_rate' => $labourRate,
            'labour_amount' => $labourAmount * $qty,
            'other_amount' => $otherAmount * $qty,
            'other_charge_details' => $this->normalizeOtherChargeDetails($row['other_charge_details'] ?? $row['other_charges'] ?? null),
            'total_amount' => $totalAmount,
            'remarks' => $row['remarks'] ?? null,
        ];
    }

    private function resolveOtherAmount($inputOtherAmount, $itemSetSaleOther): float
    {
        $baseOther = (float) ($itemSetSaleOther ?? 0);
        if ($inputOtherAmount === null || $inputOtherAmount === '') {
            return $baseOther;
        }

        $inputOther = (float) $inputOtherAmount;
        if ($inputOther <= 0 && $baseOther > 0) {
            return $baseOther;
        }

        return $inputOther;
    }

    private function decodeOtherChargeDetails($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function normalizeOtherChargeDetails($value): ?string
    {
        if (is_array($value)) {
            $value = array_values(array_filter($value, fn($row) => is_array($row) || trim((string) $row) !== ''));
            return empty($value) ? null : json_encode($value);
        }

        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function resolveTotalAmount($inputTotalAmount, float $metalAmount, float $labourAmount, float $otherAmount): float
    {
        $calculated = (float) ($metalAmount + $labourAmount + $otherAmount);
        if ($inputTotalAmount === null || $inputTotalAmount === '') {
            return $calculated;
        }

        $inputTotal = (float) $inputTotalAmount;
        if ($inputTotal <= 0 && $calculated > 0) {
            return $calculated;
        }

        return $inputTotal;
    }

    public function approvalReturnList($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $approvals = ApprovalHeader::with('customer')
            ->where('company_id', $company->id)
            ->whereHas('customer', function ($customer) {
                $customer->saleParties();
            })
            ->latest()
            ->get();

        return view('company.returns.approval_return_list', compact('company', 'approvals'));
    }

    public function approvalReturnItems($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        $approval = ApprovalHeader::with([
            'customer',
            'items' => function ($q) {
                $q->with(['itemSet', 'legacyItemSet', 'item']);
            },
        ])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        // Only show pieces that are still with the customer. The return form
        // supports returning part of a multi-quantity approval row.
        $approval->setRelation('items', $approval->items
            ->map(function ($item) {
                $item->available_qty = \App\Services\ApprovalItemAvailability::availableQuantity($item);
                return $item;
            })
            ->filter(fn ($item) => $item->available_qty > 0)
            ->values());

        return view('company.returns.approval_return_items', compact('company', 'approval'));
    }

    public function approvalReturnStore(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        DB::beginTransaction();

        try {
            $hasItemsetIdColumn = Schema::hasColumn('sale_return_items', 'itemset_id');
            $hasProductIdColumn = Schema::hasColumn('sale_return_items', 'product_id');

            $request->validate([
                'approval_id' => 'required|integer',
                'items' => 'required|array|min:1',
                'items.*' => 'required|integer',
                'quantities' => 'nullable|array',
                'quantities.*' => 'nullable|integer|min:1',
            ]);

            $return = SaleReturn::create([
                'company_id' => $company->id,
                'created_by' => auth()->id(),
                'sale_id' => null,
                'source_type' => 'approval',
                'source_id' => $request->approval_id,
                'return_voucher_no' => \App\Services\VoucherSeries::next($company->id, 'AR'),
                'return_date' => now(),
                'return_total' => 0,
                'remarks' => $request->input('voucher_remarks'),
            ]);

            $totalAmount = 0;

            foreach ($request->items as $id) {
                $approvalItem = TransactionItemGuard::lockApprovalItemForReturn($company->id, (int) $id)
                    ->load(['itemSet', 'legacyItemSet']);

                if ((int) $approvalItem->approval_id !== (int) $request->approval_id) {
                    throw new \Exception('Approval item does not belong to selected approval voucher.');
                }

                $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                if (!$itemSet) {
                    throw new \Exception("ItemSet not found for Approval Item ID: {$id}");
                }

                $availableQty = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);
                $returnQty = max(1, (int) data_get($request->input('quantities', []), (string) $id, $availableQty));
                if ($returnQty > $availableQty) {
                    throw new \RuntimeException("Only {$availableQty} piece(s) are still available to return for this approval item.");
                }

                $rate = $itemSet->metal_rate ?? 1;
                $originalQty = max(1, (int) ($approvalItem->qty ?? 1));
                $unitNetWeight = (float) ($approvalItem->net_weight ?? 0) / $originalQty;
                $amount = $unitNetWeight * $returnQty * $rate;

                $returnItemPayload = [
                    'sale_return_id' => $return->id,
                    'sale_item_id' => null,
                    'return_amount' => $amount,
                ];

                if (Schema::hasColumn('sale_return_items', 'qty')) {
                    $returnItemPayload['qty'] = $returnQty;
                } elseif ($returnQty !== $availableQty) {
                    throw new \RuntimeException('Partial approval returns require the sale_return_items.qty database migration.');
                }

                if (Schema::hasColumn('sale_return_items', 'approval_item_id')) {
                    $returnItemPayload['approval_item_id'] = $approvalItem->id;
                }

                if ($hasItemsetIdColumn) {
                    $returnItemPayload['itemset_id'] = $itemSet->id;
                }

                if ($hasProductIdColumn) {
                    $returnItemPayload['product_id'] = $approvalItem->item_id;
                }

                SaleReturnItem::create($returnItemPayload);

                $totalAmount += $amount;

                $salePrice = \App\Services\ApprovalItemPricing::unitSalePrice($approvalItem);
                if ($salePrice > 0) $approvalItem->sale_price = $salePrice;
                $approvalItem->update([
                    'status' => $returnQty >= $availableQty ? 'returned' : 'pending',
                ]);

                \App\Services\BatchInventory::restore($itemSet, $returnQty);
            }

            $return->update(['return_total' => $totalAmount]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval Return Voucher Created Successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            $isProcessedConflict = str_contains($e->getMessage(), 'already sold/returned')
                || str_contains($e->getMessage(), 'not available for return');

            return response()->json([
                'error' => $e->getMessage(),
                'success' => false,
                'code' => $isProcessedConflict ? 'APPROVAL_ITEM_ALREADY_PROCESSED' : 'APPROVAL_RETURN_SAVE_FAILED',
            ], $isProcessedConflict ? 409 : 422);
        }
    }

    private function remainingHeaderTotals(ApprovalHeader $approval): array
    {
        $totals = [
            'qty' => 0, 'gross' => 0.0, 'other' => 0.0, 'net' => 0.0,
            'fine' => 0.0, 'metal' => 0.0, 'labour' => 0.0,
            'other_amount' => 0.0, 'amount' => 0.0,
        ];

        foreach ($approval->items ?? [] as $item) {
            $available = \App\Services\ApprovalItemAvailability::availableQuantity($item);
            $original = max(1, (int) ($item->qty ?? 1));
            if ($available <= 0) {
                continue;
            }

            $totals['qty'] += $available;
            $totals['gross'] += \App\Services\ApprovalItemAvailability::remainingValue($item->gross_weight ?? 0, $original, $available);
            $totals['other'] += \App\Services\ApprovalItemAvailability::remainingValue($item->other_weight ?? 0, $original, $available);
            $totals['net'] += \App\Services\ApprovalItemAvailability::remainingValue($item->net_weight ?? 0, $original, $available);
            $totals['fine'] += \App\Services\ApprovalItemAvailability::remainingValue($item->total_fine_weight ?? 0, $original, $available);
            $totals['metal'] += \App\Services\ApprovalItemAvailability::remainingValue($item->metal_amount ?? 0, $original, $available);
            $totals['labour'] += \App\Services\ApprovalItemAvailability::remainingValue($item->labour_amount ?? 0, $original, $available);
            $totals['other_amount'] += \App\Services\ApprovalItemAvailability::remainingValue($item->other_amount ?? 0, $original, $available);
            $totals['amount'] += \App\Services\ApprovalItemAvailability::remainingValue($item->total_amount ?? 0, $original, $available);
        }

        return $totals;
    }

    /** Restrict report/list queries to approval rows with unsold quantity. */
    private function filterApprovalItemsWithAvailableQuantity($query)
    {
        if (!Schema::hasColumn('sale_items', 'approval_item_id') || !Schema::hasColumn('sale_items', 'qty')) {
            return $query->where(function ($statusQuery) {
                $statusQuery->whereNull('approval_items.status')
                    ->orWhereRaw('LOWER(TRIM(approval_items.status)) = ?', ['pending']);
            });
        }

        $soldQty = '(SELECT COALESCE(SUM(approval_sale_items.qty), 0) FROM sale_items AS approval_sale_items WHERE approval_sale_items.approval_item_id = approval_items.id)';

        return $query
            ->whereRaw('COALESCE(approval_items.qty, 1) > ' . $soldQty)
            ->where(function ($statusQuery) use ($soldQty) {
                $statusQuery->whereNull('approval_items.status')
                    ->orWhereRaw('LOWER(TRIM(approval_items.status)) = ?', ['pending'])
                    ->orWhere(function ($soldStatus) use ($soldQty) {
                        $soldStatus->whereRaw('LOWER(TRIM(approval_items.status)) = ?', ['sold'])
                            ->whereRaw($soldQty . ' > 0');
                    });
            });
    }
}

