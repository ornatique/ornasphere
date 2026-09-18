<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StockGalleryController extends Controller
{
    public function index($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name']);

        return view('company.stock_gallery.index', compact('company', 'items'));
    }

    public function data(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $perPage = min(max((int) $request->input('per_page', 24), 1), 100);

        $rows = $this->galleryQuery($company, $request)
            ->latest('item_sets.id')
            ->paginate($perPage);

        return response()->json([
            'data' => $rows->getCollection()->map(fn (ItemSet $itemSet) => $this->payload($itemSet, $company))->values(),
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
            'summary' => $this->gallerySummary($company, $request),
        ]);
    }

    public function exportPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $matchedItemCount = (clone $this->selectedOrFilteredQuery($company, $request))->count();
        $itemSets = $this->selectedOrFilteredQuery($company, $request)
            ->whereNotNull('item_sets.image_path')
            ->latest('item_sets.id')
            ->limit(300)
            ->get()
            ->map(function (ItemSet $itemSet) use ($company) {
                $itemSet->stock_gallery_payload = $this->payload($itemSet, $company);
                $itemSet->stock_gallery_image = $this->imageDataUri($itemSet);

                return $itemSet;
            })
            ->filter(fn (ItemSet $itemSet) => !empty($itemSet->stock_gallery_image))
            ->values();
        $emptyMessage = $matchedItemCount > 0
            ? 'Items are available in stock, but image is not uploaded for the selected items.'
            : 'No stock gallery items found.';

        $pdf = Pdf::loadView('company.stock_gallery.pdf', compact('company', 'itemSets', 'emptyMessage'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('stock-gallery-' . now()->format('Ymd-His') . '.pdf');
    }

    public function downloadList(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = $this->selectedOrFilteredQuery($company, $request)
            ->whereNotNull('item_sets.image_path')
            ->latest('item_sets.id')
            ->limit(300)
            ->get()
            ->filter(fn (ItemSet $itemSet) => $this->imageExists($itemSet))
            ->map(fn (ItemSet $itemSet) => [
                'id' => (int) $itemSet->id,
                'label_code' => (string) ($itemSet->qr_code ?: $itemSet->barcode ?: $itemSet->id),
                'item_name' => optional($itemSet->item)->item_name ?? '-',
                'download_url' => route('company.stock-gallery.download-image', [
                    $company->slug,
                    Crypt::encryptString((string) $itemSet->id),
                ]),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function downloadImage($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = (int) Crypt::decryptString($encryptedId);

        $itemSet = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        abort_unless($itemSet->image_path, 404, 'Image not uploaded.');

        $disk = Storage::disk($itemSet->image_disk ?: $this->itemImageDisk());
        abort_unless($disk->exists($itemSet->image_path), 404, 'Image file not found.');

        return $disk->download($itemSet->image_path, $this->imageFilename($itemSet));
    }

    private function selectedOrFilteredQuery(Company $company, Request $request): Builder
    {
        $query = $this->galleryQuery($company, $request);
        $ids = $this->selectedIds($request);

        if ($request->input('scope') === 'selected' && empty($ids)) {
            abort(422, 'Please select at least one item.');
        }

        if (!empty($ids)) {
            $query->whereIn('item_sets.id', $ids);
        }

        return $query;
    }

    private function galleryQuery(Company $company, Request $request): Builder
    {
        $query = ItemSet::query()
            ->with('item')
            ->where('item_sets.company_id', $company->id)
            ->where('item_sets.is_final', 1)
            ->whereNotNull('item_sets.qr_code');

        if ($request->filled('item_id')) {
            $query->where('item_sets.item_id', $request->input('item_id'));
        }

        if ($request->input('stock_status') === 'in') {
            $query->where('item_sets.is_sold', 0);
        } elseif ($request->input('stock_status') === 'out') {
            $query->where('item_sets.is_sold', 1);
        }

        if ($request->input('image_status') === 'with') {
            $query->whereNotNull('item_sets.image_path');
        } elseif ($request->input('image_status') === 'without') {
            $query->whereNull('item_sets.image_path');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('item_sets.qr_code', 'like', "%{$search}%")
                    ->orWhere('item_sets.barcode', 'like', "%{$search}%")
                    ->orWhere('item_sets.HUID', 'like', "%{$search}%")
                    ->orWhereHas('item', function (Builder $itemQuery) use ($search) {
                        $itemQuery->where('item_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function gallerySummary(Company $company, Request $request): array
    {
        $summary = $this->galleryQuery($company, $request)
            ->selectRaw('
                COUNT(*) as total_items,
                SUM(CASE WHEN item_sets.is_sold = 0 THEN 1 ELSE 0 END) as in_stock_items,
                SUM(CASE WHEN item_sets.is_sold = 1 THEN 1 ELSE 0 END) as out_stock_items,
                SUM(CASE WHEN item_sets.image_path IS NOT NULL THEN 1 ELSE 0 END) as with_image_items,
                SUM(CASE WHEN item_sets.image_path IS NULL THEN 1 ELSE 0 END) as without_image_items,
                COALESCE(SUM(item_sets.gross_weight), 0) as total_gross_weight,
                COALESCE(SUM(item_sets.other), 0) as total_other_weight,
                COALESCE(SUM(item_sets.net_weight), 0) as total_net_weight
            ')
            ->first();

        return [
            'total_items' => (int) ($summary->total_items ?? 0),
            'in_stock_items' => (int) ($summary->in_stock_items ?? 0),
            'out_stock_items' => (int) ($summary->out_stock_items ?? 0),
            'with_image_items' => (int) ($summary->with_image_items ?? 0),
            'without_image_items' => (int) ($summary->without_image_items ?? 0),
            'total_gross_weight' => number_format((float) ($summary->total_gross_weight ?? 0), 3),
            'total_other_weight' => number_format((float) ($summary->total_other_weight ?? 0), 3),
            'total_net_weight' => number_format((float) ($summary->total_net_weight ?? 0), 3),
        ];
    }

    private function payload(ItemSet $itemSet, Company $company): array
    {
        return [
            'id' => (int) $itemSet->id,
            'encrypted_id' => Crypt::encryptString((string) $itemSet->id),
            'item_name' => optional($itemSet->item)->item_name ?? '-',
            'label_code' => $itemSet->qr_code ?: $itemSet->barcode ?: '-',
            'qr_code' => $itemSet->qr_code,
            'huid' => $itemSet->HUID ?: '-',
            'gross_weight' => number_format((float) $itemSet->gross_weight, 3, '.', ''),
            'other_weight' => number_format((float) $itemSet->other, 3, '.', ''),
            'net_weight' => number_format((float) $itemSet->net_weight, 3, '.', ''),
            'has_other_weight' => (float) $itemSet->other > 0,
            'stock_status' => ((int) $itemSet->is_sold === 1) ? 'Out Stock' : 'In Stock',
            'is_sold' => (int) $itemSet->is_sold,
            'image_url' => $this->itemImageUrl($itemSet),
            'has_image' => !empty($itemSet->image_path),
            'download_url' => route('company.stock-gallery.download-image', [
                $company->slug,
                Crypt::encryptString((string) $itemSet->id),
            ]),
        ];
    }

    private function selectedIds(Request $request): array
    {
        $ids = $request->input('selected_ids', []);

        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }

        if (!is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function itemImageDisk(): string
    {
        return (string) config('filesystems.item_image_disk', 'public');
    }

    private function itemImageUrl(?ItemSet $itemSet): ?string
    {
        if (!$itemSet || !$itemSet->image_path) {
            return null;
        }

        return Storage::disk($itemSet->image_disk ?: $this->itemImageDisk())->url($itemSet->image_path);
    }

    private function imageDataUri(ItemSet $itemSet): ?string
    {
        if (!$itemSet->image_path) {
            return null;
        }

        try {
            $disk = Storage::disk($itemSet->image_disk ?: $this->itemImageDisk());
            if (!$disk->exists($itemSet->image_path)) {
                return null;
            }

            $mime = $itemSet->image_mime ?: 'image/jpeg';

            return 'data:' . $mime . ';base64,' . base64_encode($disk->get($itemSet->image_path));
        } catch (\Throwable) {
            return null;
        }
    }

    private function imageExists(ItemSet $itemSet): bool
    {
        if (!$itemSet->image_path) {
            return false;
        }

        try {
            return Storage::disk($itemSet->image_disk ?: $this->itemImageDisk())->exists($itemSet->image_path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function imageFilename(ItemSet $itemSet): string
    {
        $extension = pathinfo($itemSet->image_path ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $label = $itemSet->qr_code ?: $itemSet->barcode ?: ('item-' . $itemSet->id);

        return Str::slug($label) . '.' . $extension;
    }
}
