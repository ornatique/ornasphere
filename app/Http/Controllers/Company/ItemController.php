<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ItemController extends Controller
{
    // ================= INDEX =================
    public function index($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.items.index', compact('company'));
    }

    // ================= DATATABLE =================
    public function data($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id', $company->id)
            ->select('items.*');

        return datatables()->of($items)
            ->addIndexColumn()
            ->editColumn('labour_type', function ($item) {
                $map = [
                    'per_netweight' => 'Per Netweight',
                    'per_fineweight' => 'Per Fineweight',
                    'per_grossweight' => 'Per Grossweight',
                    'per_quantity' => 'Per Quantity',
                    'flat' => 'Flat',
                ];

                $key = strtolower((string) $item->labour_type);

                return $map[$key] ?? ucfirst(str_replace('_', ' ', $key));
            })
            ->addColumn('status', function ($item) {
                return $item->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>';
            })
            ->addColumn('action', function ($item) use ($company) {
                $encryptedId = Crypt::encryptString($item->id);

                $editUrl = route('company.items.edit', [$company->slug, $encryptedId]);
                $tryOnUrl = route('company.items.try-on', [$company->slug, $encryptedId]);
                $deleteUrl = route('company.items.destroy', [$company->slug, $encryptedId]);

                return '
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                <a href="' . $tryOnUrl . '" class="btn btn-sm btn-info">Try-On</a>
                <button class="btn btn-sm btn-danger deleteItem" data-url="' . $deleteUrl . '">Delete</button>
            ';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    // ================= CREATE =================
    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.items.create', compact('company'));
    }

    public function arCatalog($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id', $company->id)
            ->latest()
            ->get();

        return view('company.items.ar_catalog', compact('company', 'items'));
    }

    public function arCreate($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $items = Item::where('company_id', $company->id)->orderBy('item_name')->get();

        return view('company.items.ar_create', compact('company', 'items'));
    }

    public function arEdit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return view('company.items.ar_edit', compact('company', 'item'));
    }

    // ================= STORE =================
    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'required|string|max:255',
            'metal' => 'nullable|string|max:100',
            'metal_formula' => 'nullable|string|max:100',
            'outward_carat' => 'nullable|numeric',
            'inward_carat' => 'nullable|numeric',
            'outward_purity' => 'nullable|numeric',
            'inward_purity' => 'nullable|numeric',
            'labour_type' => 'nullable|string|max:100',
            'labour_unit' => 'nullable|string|max:100',
            'jobwork_item_type' => 'nullable|string|max:100',
            'hsn' => 'nullable|string|max:100',
            'export_hsn' => 'nullable|string|max:100',
            'numeric_length' => 'nullable|integer',
            'item_group' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);

        Item::create([
            'company_id' => $company->id,
            'item_name' => $validated['item_name'],
            'item_code' => $validated['item_code'],
            'metal' => $validated['metal'] ?? null,
            'metal_formula' => $validated['metal_formula'] ?? null,
            'outward_carat' => $validated['outward_carat'] ?? null,
            'inward_carat' => $validated['inward_carat'] ?? null,
            'outward_purity' => $validated['outward_purity'] ?? null,
            'inward_purity' => $validated['inward_purity'] ?? null,
            'labour_type' => $validated['labour_type'] ?? null,
            'labour_unit' => $validated['labour_unit'] ?? null,
            'jobwork_item_type' => $validated['jobwork_item_type'] ?? null,
            'hsn' => $validated['hsn'] ?? null,
            'export_hsn' => $validated['export_hsn'] ?? null,
            'auto_load_purity' => $request->has('auto_load_purity') ? 1 : 0,
            'auto_create_label_purchase' => $request->has('auto_create_label_purchase') ? 1 : 0,
            'auto_create_label_config' => $request->has('auto_create_label_config') ? 1 : 0,
            'numeric_length' => $validated['numeric_length'] ?? null,
            'item_group' => $validated['item_group'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()
            ->route('company.items.index', $company->slug)
            ->with('success', 'Item created successfully');
    }

    // ================= EDIT =================
    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return view('company.items.edit', compact('company', 'item'));
    }

    // ================= TRY-ON =================
    public function tryOn($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return view('company.items.tryon', compact('company', 'item'));
    }

    public function arConfig($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'item_code' => $item->item_code,
                'jewellery_type' => $item->jewellery_type,
                'ar_mode' => $item->ar_mode,
                'glb_url' => $item->glb_url,
                'usdz_url' => $item->usdz_url,
                'thumbnail_url' => $item->thumbnail_url,
                'deepar_effect_id' => $item->deepar_effect_id,
                'ar_meta' => $item->ar_meta ? json_decode($item->ar_meta, true) : null,
            ],
        ]);
    }

    public function arUpdate(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $validated = $request->validate([
            'jewellery_type' => 'nullable|string|max:30',
            'ar_mode' => 'nullable|string|max:30',
            'glb_url' => 'nullable|string|max:2048',
            'usdz_url' => 'nullable|string|max:2048',
            'thumbnail_url' => 'nullable|string|max:2048',
            'thumbnail_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'deepar_effect_id' => 'nullable|string|max:255',
            'ar_meta' => 'nullable|string',
        ]);

        $arMeta = $validated['ar_meta'] ?? null;
        if (!empty($arMeta)) {
            json_decode($arMeta, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['ar_meta' => 'AR Meta must be valid JSON'])->withInput();
            }
        }

        $thumbnailUrl = $validated['thumbnail_url'] ?? $item->thumbnail_url;
        if ($request->hasFile('thumbnail_file')) {
            $thumbnailPath = $request->file('thumbnail_file')->store('ar-thumbnails', 'public');
            $thumbnailUrl = '/storage/' . ltrim($thumbnailPath, '/');
        }

        $item->update([
            'jewellery_type' => $validated['jewellery_type'] ?? null,
            'ar_mode' => $validated['ar_mode'] ?? '3d_view',
            'glb_url' => $validated['glb_url'] ?? null,
            'usdz_url' => $validated['usdz_url'] ?? null,
            'thumbnail_url' => $thumbnailUrl,
            'deepar_effect_id' => $validated['deepar_effect_id'] ?? null,
            'ar_meta' => $arMeta,
        ]);

        return redirect()
            ->route('company.items.ar-catalog', $company->slug)
            ->with('success', 'AR configuration updated successfully');
    }

    public function arStore(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $validated = $request->validate([
            'item_id' => 'required|integer',
            'jewellery_type' => 'nullable|string|max:30',
            'ar_mode' => 'nullable|string|max:30',
            'glb_url' => 'nullable|string|max:2048',
            'usdz_url' => 'nullable|string|max:2048',
            'thumbnail_url' => 'nullable|string|max:2048',
            'thumbnail_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'deepar_effect_id' => 'nullable|string|max:255',
            'ar_meta' => 'nullable|string',
        ]);

        $item = Item::where('company_id', $company->id)
            ->where('id', $validated['item_id'])
            ->firstOrFail();

        $arMeta = $validated['ar_meta'] ?? null;
        if (!empty($arMeta)) {
            json_decode($arMeta, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['ar_meta' => 'AR Meta must be valid JSON'])->withInput();
            }
        }

        $thumbnailUrl = $validated['thumbnail_url'] ?? $item->thumbnail_url;
        if ($request->hasFile('thumbnail_file')) {
            $thumbnailPath = $request->file('thumbnail_file')->store('ar-thumbnails', 'public');
            $thumbnailUrl = '/storage/' . ltrim($thumbnailPath, '/');
        }

        $item->update([
            'jewellery_type' => $validated['jewellery_type'] ?? null,
            'ar_mode' => $validated['ar_mode'] ?? '3d_view',
            'glb_url' => $validated['glb_url'] ?? null,
            'usdz_url' => $validated['usdz_url'] ?? null,
            'thumbnail_url' => $thumbnailUrl,
            'deepar_effect_id' => $validated['deepar_effect_id'] ?? null,
            'ar_meta' => $arMeta,
        ]);

        return redirect()
            ->route('company.items.ar-catalog', $company->slug)
            ->with('success', 'AR configuration created successfully');
    }

    // ================= UPDATE =================
    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'required|string|max:255',
            'metal' => 'nullable|string|max:100',
            'metal_formula' => 'nullable|string|max:100',
            'outward_carat' => 'nullable|numeric',
            'inward_carat' => 'nullable|numeric',
            'outward_purity' => 'nullable|numeric',
            'inward_purity' => 'nullable|numeric',
            'labour_type' => 'nullable|string|max:100',
            'labour_unit' => 'nullable|string|max:100',
            'jobwork_item_type' => 'nullable|string|max:100',
            'hsn' => 'nullable|string|max:100',
            'export_hsn' => 'nullable|string|max:100',
            'item_group' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
            'numeric_length' => 'nullable|integer',
        ]);

        $item->update([
            'item_name' => $validated['item_name'],
            'item_code' => $validated['item_code'],
            'metal' => $validated['metal'] ?? null,
            'metal_formula' => $validated['metal_formula'] ?? null,
            'outward_carat' => $validated['outward_carat'] ?? null,
            'inward_carat' => $validated['inward_carat'] ?? null,
            'outward_purity' => $validated['outward_purity'] ?? null,
            'inward_purity' => $validated['inward_purity'] ?? null,
            'labour_type' => $validated['labour_type'] ?? null,
            'labour_unit' => $validated['labour_unit'] ?? null,
            'jobwork_item_type' => $validated['jobwork_item_type'] ?? null,
            'hsn' => $validated['hsn'] ?? null,
            'export_hsn' => $validated['export_hsn'] ?? null,
            'auto_load_purity' => $request->has('auto_load_purity') ? 1 : 0,
            'auto_create_label_purchase' => $request->has('auto_create_label_purchase') ? 1 : 0,
            'auto_create_label_config' => $request->has('auto_create_label_config') ? 1 : 0,
            'numeric_length' => $validated['numeric_length'] ?? null,
            'item_group' => $validated['item_group'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()
            ->route('company.items.index', $company->slug)
            ->with('success', 'Item updated successfully');
    }

    // ================= DELETE =================
    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'status' => true,
            'message' => 'Item deleted successfully'
        ]);
    }
}
