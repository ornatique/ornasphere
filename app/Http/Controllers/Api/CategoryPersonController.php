<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CategoryPerson;
use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class CategoryPersonController extends Controller
{
    /**
     * Category List
     */
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        CategoryPerson::ensureCompanyDefaults($companyId);

        $categories = CategoryPerson::where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Store Category
     */
    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $request->merge([
            'category_name' => trim((string) $request->input('category_name')),
        ]);

        $validated = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_people', 'category_name')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ]);

        $category = CategoryPerson::create([
            'company_id'    => $companyId,
            'category_name' => $validated['category_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
        ]);
    }

    /**
     * Show Category
     */
    public function show(Request $request, $id)
    {
        $category = CategoryPerson::where('company_id', $request->user()->company_id)
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * Update Category
     */
    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $category = CategoryPerson::where('company_id', $companyId)
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        if ($category->isSystemDefault()) {
            return response()->json([
                'success' => false,
                'message' => 'System default category cannot be edited.',
            ], 422);
        }

        $request->merge([
            'category_name' => trim((string) $request->input('category_name')),
        ]);

        $validated = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_people', 'category_name')
                    ->ignore($category->id)
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $category,
        ]);
    }

    /**
     * Delete Category
     */
   public function destroy(Request $request, $id)
    {
        $category = CategoryPerson::where('company_id', $request->user()->company_id)
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        if ($category->isSystemDefault()) {
            return response()->json([
                'success' => false,
                'message' => 'System default category cannot be deleted.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}
