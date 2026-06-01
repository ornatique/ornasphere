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

class ApprovalController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->get();

        if ($request->ajax()) {
            $query = ApprovalHeader::with([
                'customer',
                'creator',
                'items' => function ($q) {
                    $q->where('status', '!=', 'returned')
                        ->with(['itemSet.item', 'legacyItemSet.item']);
                }
            ])
                ->withCount([
                    'items as active_items_count' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ])
                ->withSum([
                    'items as active_gross_weight' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'gross_weight')
                ->withSum([
                    'items as active_net_weight' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'net_weight')
                ->withSum([
                    'items as active_fine_weight' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'total_fine_weight')
                ->withSum([
                    'items as active_metal_amount' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'metal_amount')
                ->withSum([
                    'items as active_labour_amount' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'labour_amount')
                ->withSum([
                    'items as active_other_amount' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'other_amount')
                ->withSum([
                    'items as active_item_amount' => function ($q) {
                        $q->where('status', '!=', 'returned');
                    }
                ], 'total_amount')
                ->where('company_id', $company->id)
                ->orderByDesc('approval_date')
                ->orderByDesc('id');

            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

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

            if (!empty($fromDate) && !empty($toDate)) {
                $query->whereBetween('approval_date', [$fromDate, $toDate]);
            }

            return datatables()->of($query)
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
                                $iq->where(function ($itemsQ) use ($searchValue) {
                                    $itemsQ->whereHas('item', function ($itemQ) use ($searchValue) {
                                        $itemQ->where('item_name', 'like', "%{$searchValue}%");
                                    })->orWhereHas('itemSet.item', function ($itemQ) use ($searchValue) {
                                        $itemQ->where('item_name', 'like', "%{$searchValue}%");
                                    })->orWhereHas('legacyItemSet.item', function ($itemQ) use ($searchValue) {
                                        $itemQ->where('item_name', 'like', "%{$searchValue}%");
                                    });
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
                ->addColumn('customer_name', fn($row) => $row->customer->name ?? '-')
                ->addColumn('approval_date', fn($row) => \Carbon\Carbon::parse($row->approval_date)->format('d-m-Y'))
                ->addColumn('item_names', function ($row) {
                    $names = collect($row->items ?? [])
                        ->map(function ($it) {
                            $set = $it->itemSet ?? $it->legacyItemSet;
                            return optional(optional($set)->item)->item_name;
                        })
                        ->filter()
                        ->unique()
                        ->values();

                    return $names->isEmpty() ? '-' : $names->implode(', ');
                })
                ->addColumn('total_qty', fn($row) => (int) ($row->active_items_count ?? 0))
                ->addColumn('total_gross_weight', fn($row) => number_format((float) ($row->active_gross_weight ?? 0), 3))
                ->addColumn('total_net_weight', fn($row) => number_format((float) ($row->active_net_weight ?? 0), 3))
                ->addColumn('total_fine_weight', fn($row) => number_format((float) ($row->active_fine_weight ?? 0), 3))
                ->addColumn('total_metal_amount', fn($row) => number_format((float) ($row->active_metal_amount ?? 0), 2))
                ->addColumn('total_labour_amount', fn($row) => number_format((float) ($row->active_labour_amount ?? 0), 2))
                ->addColumn('total_other_amount', fn($row) => number_format((float) ($row->active_other_amount ?? 0), 2))
                ->addColumn('total_amount', fn($row) => number_format((float) ($row->active_item_amount ?? 0), 2))
                ->addColumn('creator_name', fn($row) => optional($row->creator)->name ?? '-')
                ->addColumn('modified_at', function ($row) {
                    return $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('d-m-Y h:i A') : '-';
                })
                ->addColumn('modified_count', fn($row) => (int) ($row->modified_count ?? 0))
                ->addColumn('status', fn($row) => $row->status_badge)
                ->addColumn('action', function ($row) use ($slug) {
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
                ->make(true);
        }

        return view('company.approval.index', compact('company', 'customers'));
    }

    public function exportListPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $query = ApprovalHeader::with([
            'customer',
            'creator',
            'items' => function ($q) {
                $q->where('status', '!=', 'returned')
                    ->with(['itemSet.item', 'legacyItemSet.item']);
            }
        ])
            ->withCount([
                'items as active_items_count' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ])
            ->withSum([
                'items as active_gross_weight' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'gross_weight')
            ->withSum([
                'items as active_net_weight' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'net_weight')
            ->withSum([
                'items as active_fine_weight' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'total_fine_weight')
            ->withSum([
                'items as active_metal_amount' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'metal_amount')
            ->withSum([
                'items as active_labour_amount' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'labour_amount')
            ->withSum([
                'items as active_other_amount' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'other_amount')
            ->withSum([
                'items as active_item_amount' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'total_amount')
            ->where('company_id', $company->id)
            ->orderByDesc('approval_date')
            ->orderByDesc('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }

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
        if (!empty($fromDate) && !empty($toDate)) {
            $query->whereBetween('approval_date', [$fromDate, $toDate]);
        }

        $rows = $query->get()->map(function ($row) {
            $names = collect($row->items ?? [])
                ->map(function ($it) {
                    $set = $it->itemSet ?? $it->legacyItemSet;
                    return optional(optional($set)->item)->item_name;
                })
                ->filter()
                ->unique()
                ->values();

            return [
                'approval_no' => (string) ($row->approval_no ?? '-'),
                'approval_date' => $row->approval_date ? Carbon::parse($row->approval_date)->format('d-m-Y') : '-',
                'customer_name' => optional($row->customer)->name ?? '-',
                'item_names' => $names->isEmpty() ? '-' : $names->implode(', '),
                'total_qty' => (int) ($row->active_items_count ?? 0),
                'gross_wt' => (float) ($row->active_gross_weight ?? 0),
                'net_wt' => (float) ($row->active_net_weight ?? 0),
                'fine_wt' => (float) ($row->active_fine_weight ?? 0),
                'metal_amt' => (float) ($row->active_metal_amount ?? 0),
                'labour_amt' => (float) ($row->active_labour_amount ?? 0),
                'other_amt' => (float) ($row->active_other_amount ?? 0),
                'total_amt' => (float) ($row->active_item_amount ?? 0),
                'created_by' => optional($row->creator)->name ?? '-',
            ];
        })->values();

        $pdf = Pdf::loadView('company.approval.list_pdf', [
            'company' => $company,
            'rows' => $rows,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('approval-list-' . now()->format('YmdHis') . '.pdf');
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->get();

        return view('company.approval.create', compact('company', 'customers'));
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
            ->where('is_active', 1)
            ->get();

        $editableItems = $approval->items
            ->where('status', 'pending')
            ->values()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                return [
                    'itemset_id' => (int) ($row->itemset_id ?? optional($itemSet)->id),
                    'item_id' => (int) ($row->item_id ?? optional($itemSet)->item_id),
                    'item_name' => optional(optional($itemSet)->item)->item_name,
                    'huid' => $row->huid ?? optional($itemSet)->HUID,
                    'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                    'gross_weight' => (float) ($row->gross_weight ?? 0),
                    'other_weight' => (float) ($row->other_weight ?? 0),
                    'net_weight' => (float) ($row->net_weight ?? 0),
                    'purity' => (float) ($row->purity ?? 0),
                    'waste_percent' => (float) ($row->waste_percent ?? 0),
                    'net_purity' => (float) ($row->net_purity ?? 0),
                    'total_fine_weight' => (float) ($row->total_fine_weight ?? 0),
                    'metal_rate' => (float) ($row->metal_rate ?? 0),
                    'metal_amount' => (float) ($row->metal_amount ?? 0),
                    'labour_rate' => (float) ($row->labour_rate ?? 0),
                    'labour_amount' => (float) ($row->labour_amount ?? 0),
                    'other_amount' => (float) ($row->other_amount ?? 0),
                    'total_amount' => (float) ($row->total_amount ?? 0),
                    'remarks' => (string) ($row->remarks ?? ''),
                    'other_charges' => [],
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
            ->where('is_sold', 0)
            ->get();

        return response()->json($data);
    }

    public function searchItemSets($slug, Request $request)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $keyword = trim((string) $request->keyword);
        $limit = max(10, min((int) $request->input('limit', 1000), 2000));

        $data = ItemSet::with('item')
            ->where('company_id', $company->id)
             ->where('is_final', 1)
            ->where('is_sold', 0)
            ->where(function ($q) use ($keyword) {
                $q->where('HUID', 'LIKE', "%{$keyword}%")
                    ->orWhere('qr_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('barcode', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('item', function ($q2) use ($keyword) {
                        $q2->where('item_name', 'like', "%{$keyword}%");
                    });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
        $itemSetItemIds = $data->pluck('item_id')->filter()->unique()->values()->all();
        $itemOnly = Item::query()
            ->where('company_id', $company->id)
            ->whereNotIn('id', $itemSetItemIds)
            ->where(function ($q) use ($keyword) {
                $q->where('item_name', 'like', "%{$keyword}%")
                    ->orWhere('item_code', 'like', "%{$keyword}%");
            })
            ->orderBy('item_name')
            ->limit($limit)
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);

        if (Schema::hasColumn('items', 'is_active')) {
            $itemOnly = Item::query()
                ->where('company_id', $company->id)
                ->where('is_active', 1)
                ->whereNotIn('id', $itemSetItemIds)
                ->where(function ($q) use ($keyword) {
                    $q->where('item_name', 'like', "%{$keyword}%")
                        ->orWhere('item_code', 'like', "%{$keyword}%");
                })
                ->orderBy('item_name')
                ->limit($limit)
                ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);
        }

        $itemOnlyRows = $itemOnly->map(function ($item) {
            return (object) [
                'id' => 0,
                'item_id' => (int) $item->id,
                'item' => (object) ['item_name' => (string) ($item->item_name ?? '')],
                'HUID' => '',
                'qr_code' => (string) ($item->item_code ?? ''),
                'barcode' => '',
                'gross_weight' => 0,
                'other' => 0,
                'net_weight' => 0,
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
            $approval = ApprovalHeader::create([
                'company_id' => $company->id,
                'customer_id' => $request->customer_id,
                'approval_no' => 'APP' . time(),
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

                $itemSet = ItemSet::with('item')
                    ->where('company_id', $company->id)
                    ->findOrFail($itemSetId);

                if ((int) $itemSet->is_sold === 1) {
                    throw new \Exception("Item already sold/used: {$itemSet->qr_code}");
                }

                $gross = (float) ($row['gross_weight'] ?? $itemSet->gross_weight ?? 0);
                $otherWeight = (float) ($row['other_weight'] ?? $itemSet->other ?? 0);
                $netWeight = (float) ($row['net_weight'] ?? ($gross - $otherWeight));
                $purity = (float) ($row['purity'] ?? optional($itemSet->item)->outward_purity ?? 0);
                $wastePercent = (float) ($row['waste_percent'] ?? 0);
                $netPurity = (float) ($row['net_purity'] ?? max(0, $purity - $wastePercent));
                $totalFineWeight = (float) ($row['total_fine_weight'] ?? ($netWeight * $netPurity / 100));
                $metalRate = (float) ($row['metal_rate'] ?? 0);
                $metalAmount = (float) ($row['metal_amount'] ?? ($netWeight * $metalRate));
                $labourRate = (float) ($row['labour_rate'] ?? $itemSet->sale_labour_rate ?? optional($itemSet->item)->labour_rate ?? 0);
                $labourAmount = (float) ($row['labour_amount'] ?? ($netWeight * $labourRate));
                $otherAmount = $this->resolveOtherAmount($row['other_amount'] ?? null, $itemSet->sale_other ?? 0);
                $totalAmount = $this->resolveTotalAmount($row['total_amount'] ?? null, $metalAmount, $labourAmount, $otherAmount);

                ApprovalItem::create([
                    'approval_id' => $approval->id,
                    'itemset_id' => $itemSet->id,
                    'item_id' => $itemSet->item_id,
                    'huid' => $row['huid'] ?? $itemSet->HUID,
                    'qr_code' => $row['qr_code'] ?? $itemSet->qr_code,
                    'gross_weight' => $gross,
                    'other_weight' => $otherWeight,
                    'net_weight' => $netWeight,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'total_fine_weight' => $totalFineWeight,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'remarks' => $row['remarks'] ?? null,
                ]);

                $itemSet->update(['is_sold' => 1]);
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
                    ItemSet::where('company_id', $company->id)
                        ->where('id', (int) $row->itemset_id)
                        ->update(['is_sold' => 0]);
                }
                $row->delete();
            }

            foreach ($incomingRows as $row) {
                $itemSetId = (int) ($row['itemset_id'] ?? $row['id'] ?? 0);
                if ($itemSetId <= 0) {
                    continue;
                }

                $itemSet = ItemSet::with('item')
                    ->where('company_id', $company->id)
                    ->findOrFail($itemSetId);

                // If this label was not already pending in this approval, it must be free.
                if (!$pendingByItemset->has($itemSetId) && (int) $itemSet->is_sold === 1) {
                    throw new \Exception("Item already used: {$itemSet->qr_code}");
                }

                $payload = $this->buildApprovalItemPayload($row, $itemSet);

                if ($pendingByItemset->has($itemSetId)) {
                    $pendingByItemset->get($itemSetId)->update($payload);
                } else {
                    ApprovalItem::create(array_merge($payload, [
                        'approval_id' => $approval->id,
                        'itemset_id' => $itemSet->id,
                        'item_id' => $itemSet->item_id,
                        'status' => 'pending',
                    ]));
                    $itemSet->update(['is_sold' => 1]);
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

    public function pdf($slug, $encryptedId)
    {
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

        $pdf = Pdf::loadView('company.approval.approval_pdf', compact('company', 'approval', 'printedBy', 'printedAt'))
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
                $item = ApprovalItem::whereHas('approval', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                })->findOrFail($id);
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

        $items = ApprovalItem::with('itemSet.item', 'legacyItemSet.item')
            ->whereHas('approval', function ($q) use ($company, $customerId) {
                $q->where('company_id', $company->id)
                    ->where('customer_id', $customerId);
            })
            ->where('status', 'pending')
            ->get();

        return response()->json($items->map(function ($row) {
            $itemSet = $row->itemSet ?? $row->legacyItemSet;
            $item = optional($itemSet)->item;
            $gross = (float) ($row->gross_weight ?? 0);
            $otherWeight = (float) ($row->other_weight ?? 0);
            $net = (float) ($row->net_weight ?? ($gross - $otherWeight));
            $purity = (float) ($row->purity ?? optional($item)->outward_purity ?? 0);
            $wastePercent = (float) ($row->waste_percent ?? 0);
            $netPurity = (float) ($row->net_purity ?? ($purity - $wastePercent));
            $fineWeight = (float) ($row->total_fine_weight ?? (($net * $netPurity) / 100));
            $metalRate = (float) ($row->metal_rate ?? 0);
            $metalAmount = (float) ($row->metal_amount ?? ($net * $metalRate));
            $labourRate = (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
            $labourAmount = (float) ($row->labour_amount ?? ($net * $labourRate));
            $otherAmount = $this->resolveOtherAmount($row->other_amount ?? null, optional($itemSet)->sale_other ?? 0);
            $totalAmount = $this->resolveTotalAmount($row->total_amount ?? null, $metalAmount, $labourAmount, $otherAmount);
            return [
                'id' => $row->id,
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
        $netPurity = (float) ($row['net_purity'] ?? max(0, $purity - $wastePercent));
        $totalFineWeight = (float) ($row['total_fine_weight'] ?? ($netWeight * $netPurity / 100));
        $metalRate = (float) ($row['metal_rate'] ?? 0);
        $metalAmount = (float) ($row['metal_amount'] ?? ($netWeight * $metalRate));
        $labourRate = (float) ($row['labour_rate'] ?? $itemSet->sale_labour_rate ?? optional($itemSet->item)->labour_rate ?? 0);
        $labourAmount = (float) ($row['labour_amount'] ?? ($netWeight * $labourRate));
        $otherAmount = $this->resolveOtherAmount($row['other_amount'] ?? null, $itemSet->sale_other ?? 0);
        $totalAmount = $this->resolveTotalAmount($row['total_amount'] ?? null, $metalAmount, $labourAmount, $otherAmount);

        return [
            'huid' => $row['huid'] ?? $itemSet->HUID,
            'qr_code' => $row['qr_code'] ?? $itemSet->qr_code,
            'gross_weight' => $gross,
            'other_weight' => $otherWeight,
            'net_weight' => $netWeight,
            'purity' => $purity,
            'waste_percent' => $wastePercent,
            'net_purity' => $netPurity,
            'total_fine_weight' => $totalFineWeight,
            'metal_rate' => $metalRate,
            'metal_amount' => $metalAmount,
            'labour_rate' => $labourRate,
            'labour_amount' => $labourAmount,
            'other_amount' => $otherAmount,
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
                $q->where('status', 'pending')
                    ->with('itemSet');
            },
        ])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        return view('company.returns.approval_return_items', compact('company', 'approval'));
    }

    public function approvalReturnStore(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        DB::beginTransaction();

        try {
            $hasItemsetIdColumn = Schema::hasColumn('sale_return_items', 'itemset_id');
            $hasProductIdColumn = Schema::hasColumn('sale_return_items', 'product_id');

            if (!$request->has('items') || count($request->items) == 0) {
                return response()->json(['error' => 'No items selected']);
            }

            $return = SaleReturn::create([
                'company_id' => $company->id,
                'sale_id' => null,
                'source_type' => 'approval',
                'source_id' => $request->approval_id,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'return_total' => 0,
                'remarks' => $request->input('voucher_remarks'),
            ]);

            $totalAmount = 0;

            foreach ($request->items as $id) {
                $approvalItem = ApprovalItem::with('itemSet')
                    ->where('approval_id', (int) $request->approval_id)
                    ->whereHas('approval', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->findOrFail((int) $id);

                if ($approvalItem->status === 'returned') {
                    continue;
                }

                $itemSet = $approvalItem->itemSet;
                if (!$itemSet) {
                    throw new \Exception("ItemSet not found for Approval Item ID: {$id}");
                }

                $rate = $itemSet->metal_rate ?? 1;
                $amount = $approvalItem->net_weight * $rate;

                $returnItemPayload = [
                    'sale_return_id' => $return->id,
                    'sale_item_id' => null,
                    'return_amount' => $amount,
                ];

                if ($hasItemsetIdColumn) {
                    $returnItemPayload['itemset_id'] = $itemSet->id;
                }

                if ($hasProductIdColumn) {
                    $returnItemPayload['product_id'] = $approvalItem->item_id;
                }

                SaleReturnItem::create($returnItemPayload);

                $totalAmount += $amount;

                $approvalItem->update(['status' => 'returned']);

                $itemSet->update(['is_sold' => 0]);
            }

            $return->update(['return_total' => $totalAmount]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval Return Voucher Created Successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
    }
}
