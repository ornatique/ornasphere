<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LabelConfig;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSet;
use Illuminate\Support\Facades\Crypt;
use Yajra\DataTables\Facades\DataTables;

class LabelConfigController extends Controller
{

    // ================= INDEX =================
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $data = LabelConfig::with('item')
                ->where('company_id', $company->id)
                ->latest();

            return DataTables::of($data)
                 ->addIndexColumn()
                ->addColumn('item_name', function ($row) {
                    return $row->item->item_name ?? '';
                })

                ->addColumn('action', function ($row) use ($company) {

                    $encryptedId = Crypt::encryptString($row->id);
                    $isLocked = $this->hasGeneratedLabelItems((int) $company->id, (int) $row->item_id);

                    $editUrl = route(
                        'company.label_config.edit',
                        [$company->slug, $encryptedId]
                    );

                    $deleteUrl = route(
                        'company.label_config.delete',
                        [$company->slug, $encryptedId]
                    );

                    $deleteButton = $isLocked
                        ? '<span class="badge bg-secondary" title="Label items already created. This config cannot be deleted.">In Use</span>'
                        : '<button type="submit"
                            class="btn btn-sm btn-danger"
                            onclick="return confirm(\'Are you sure?\')">
                            Delete
                        </button>';

                    return '
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary">
                        Edit
                    </a>

                    <form action="' . $deleteUrl . '" method="POST"
                        style="display:inline-block; margin-left:5px;">

                        ' . csrf_field() . '
                        ' . method_field("DELETE") . '

                        ' . $deleteButton . '

                    </form>
                ';
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view(
            'company.label_config.index',
            compact('company')
        );
    }


    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = $this->availableItemsQuery($company)
            ->get(['id', 'item_name']);

        return view('company.label_config.create', compact('company', 'items'));
    }
    // ================= STORE =================
    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'item_id' => 'required|exists:items,id',
            'prefix' => 'nullable|string|max:50',
        ]);

        $prefix = $this->normalizePrefix($request->input('prefix'));

        if (! $this->availableItemsQuery($company)->where('id', $request->item_id)->exists()) {
            return back()
                ->withInput()
                ->withErrors(['item_id' => 'Label Config already exists for selected item.']);
        }

        if ($this->prefixExists((int) $company->id, $prefix)) {
            return back()
                ->withInput()
                ->withErrors(['prefix' => 'This prefix is already used. Please enter a different prefix.']);
        }

        LabelConfig::create([
            'company_id' => $company->id,
            'item_id' => $request->item_id,
            'prefix' => $prefix,
            'numeric_length' => $request->numeric_length,
            'last_no' => $request->last_no ?? 0,
            'reuse' => $request->reuse ? 1 : 0,
            'random' => $request->random ? 1 : 0,
            'min_no' => $request->min_no,
            'max_no' => $request->max_no,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'Label Config Saved Successfully'
            ]);
        }

        return redirect()
            ->route('company.label_config.index', $company->slug)
            ->with('success', 'Label Config Saved Successfully');
    }


    // ================= EDIT =================
    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $labelConfig = LabelConfig::where('id', $id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $items = $this->availableItemsQuery($company, $labelConfig->item_id)
            ->get(['id', 'item_name']);
        $isLocked = $this->hasGeneratedLabelItems((int) $company->id, (int) $labelConfig->item_id);
        $minimumLastNo = $isLocked ? $this->minimumAllowedLastNo($labelConfig) : 0;

        return view(
            'company.label_config.edit',
            compact('company', 'labelConfig', 'items', 'isLocked', 'minimumLastNo')
        );
    }



    // ================= UPDATE =================
    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $labelConfig = LabelConfig::where('id', $id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $isLocked = $this->hasGeneratedLabelItems((int) $company->id, (int) $labelConfig->item_id);

        $request->validate([
            'item_id' => $isLocked ? 'nullable' : 'required|exists:items,id',
            'prefix' => 'nullable|string|max:50',
            'numeric_length' => 'nullable|integer',
            'last_no' => 'nullable|integer|min:0',
            'min_no' => 'nullable|integer',
            'max_no' => 'nullable|integer',
        ]);

        $prefix = $this->normalizePrefix($request->input('prefix'));
        $lastNo = $request->has('last_no')
            ? (int) $request->last_no
            : (int) ($labelConfig->last_no ?? 0);

        if (!$isLocked && ! $this->availableItemsQuery($company, $labelConfig->item_id)->where('id', $request->item_id)->exists()) {
            return back()
                ->withInput()
                ->withErrors(['item_id' => 'Label Config already exists for selected item.']);
        }

        if (!$isLocked && $this->prefixExists((int) $company->id, $prefix, (int) $labelConfig->id)) {
            return back()
                ->withInput()
                ->withErrors(['prefix' => 'This prefix is already used. Please enter a different prefix.']);
        }

        $minimumLastNo = $this->minimumAllowedLastNo($labelConfig);
        if ($isLocked && $lastNo < $minimumLastNo) {
            return back()
                ->withInput()
                ->withErrors(['last_no' => "Last No cannot be less than {$minimumLastNo}. Generated label numbers cannot be reused."]);
        }

        $labelConfig->update([

            'item_id' => $isLocked ? $labelConfig->item_id : $request->item_id,
            'prefix' => $isLocked ? $labelConfig->prefix : $prefix,
            'numeric_length' => $request->numeric_length,
            'last_no' => $lastNo,
            'reuse' => $request->reuse ? 1 : 0,
            'random' => $request->random ? 1 : 0,
            'min_no' => $request->min_no,
            'max_no' => $request->max_no,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,

        ]);

        return redirect()
            ->route('company.label_config.index', $company->slug)
            ->with('success', 'Label Config Updated Successfully');
    }



    // ================= DELETE =================
    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $labelConfig = LabelConfig::where('id', $id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        if ($this->hasGeneratedLabelItems((int) $company->id, (int) $labelConfig->item_id)) {
            return redirect()
                ->route('company.label_config.index', $company->slug)
                ->with('error', 'Label items already created for this item. This label config cannot be deleted.');
        }

        $labelConfig->delete();

        return redirect()
            ->route('company.label_config.index', $company->slug)
            ->with('success', 'Label Config Deleted Successfully');
    }

    private function availableItemsQuery(Company $company, $currentItemId = null)
    {
        return Item::where('company_id', $company->id)
            ->where(function ($query) use ($currentItemId) {
                $query->whereDoesntHave('labelConfig')
                    ->when($currentItemId, fn($q) => $q->orWhere('id', $currentItemId));
            })
            ->orderBy('item_name');
    }

    private function normalizePrefix($prefix): ?string
    {
        $prefix = trim((string) $prefix);

        return $prefix === '' ? null : $prefix;
    }

    private function prefixExists(int $companyId, ?string $prefix, ?int $ignoreId = null): bool
    {
        if ($prefix === null) {
            return false;
        }

        return LabelConfig::where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(prefix)) = ?', [strtolower($prefix)])
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function hasGeneratedLabelItems(int $companyId, int $itemId): bool
    {
        return ItemSet::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->exists();
    }

    private function minimumAllowedLastNo(LabelConfig $labelConfig): int
    {
        $highestExistingSerialNo = (int) ItemSet::where('company_id', $labelConfig->company_id)
            ->where('item_id', $labelConfig->item_id)
            ->where('is_final', 1)
            ->max('serial_no');

        return max((int) ($labelConfig->last_no ?? 0), $highestExistingSerialNo);
    }
}
