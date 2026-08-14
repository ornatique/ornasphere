<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CategoryPerson;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CategoryPersonController extends Controller
{
    public function index(string $slug, Request $request)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            CategoryPerson::ensureCompanyDefaults((int) $company->id);

            $categories = CategoryPerson::query()
                ->where('company_id', $company->id)
                ->latest();

            return DataTables::of($categories)
                ->addIndexColumn()
                ->addColumn('created_at_display', fn ($row) => optional($row->created_at)->format('d-m-Y h:i A'))
                ->addColumn('action', function ($row) use ($company) {
                    if ($row->isSystemDefault()) {
                        return '<span class="badge bg-secondary">System Default</span>';
                    }

                    $encryptedId = Crypt::encryptString($row->id);
                    $editUrl = route('company.category-persons.edit', [$company->slug, $encryptedId]);
                    $deleteUrl = route('company.category-persons.destroy', [$company->slug, $encryptedId]);

                    return '<div class="action-buttons">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                        <form method="POST" action="' . $deleteUrl . '" class="d-inline">
                            ' . csrf_field() . method_field('DELETE') . '
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this category person?\')">Delete</button>
                        </form>
                    </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.category_person.index', compact('company'));
    }

    public function create(string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.category_person.create', compact('company'));
    }

    public function store(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $request->merge([
            'category_name' => trim((string) $request->input('category_name')),
        ]);

        $validated = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_people', 'category_name')
                    ->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
        ]);

        CategoryPerson::create([
            'company_id' => $company->id,
            'category_name' => $validated['category_name'],
        ]);

        return redirect()
            ->route('company.category-persons.index', $company->slug)
            ->with('success', 'Category person created successfully');
    }

    public function edit(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $categoryPerson = $this->findCategoryPerson($company, $encryptedId);

        if ($categoryPerson->isSystemDefault()) {
            return redirect()
                ->route('company.category-persons.index', $company->slug)
                ->withErrors(['category_name' => 'System default category person cannot be edited.']);
        }

        return view('company.category_person.edit', compact('company', 'categoryPerson'));
    }

    public function update(Request $request, string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $categoryPerson = $this->findCategoryPerson($company, $encryptedId);

        if ($categoryPerson->isSystemDefault()) {
            return redirect()
                ->route('company.category-persons.index', $company->slug)
                ->withErrors(['category_name' => 'System default category person cannot be edited.']);
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
                    ->ignore($categoryPerson->id)
                    ->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
        ]);

        $categoryPerson->update($validated);

        return redirect()
            ->route('company.category-persons.index', $company->slug)
            ->with('success', 'Category person updated successfully');
    }

    public function destroy(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $categoryPerson = $this->findCategoryPerson($company, $encryptedId);

        if ($categoryPerson->isSystemDefault()) {
            return back()->withErrors(['category_name' => 'System default category person cannot be deleted.']);
        }

        $categoryPerson->delete();

        return back()->with('success', 'Category person deleted successfully');
    }

    private function findCategoryPerson(Company $company, string $encryptedId): CategoryPerson
    {
        $id = Crypt::decryptString($encryptedId);

        return CategoryPerson::where('company_id', $company->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
