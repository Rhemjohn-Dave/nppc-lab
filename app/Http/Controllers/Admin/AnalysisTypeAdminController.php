<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisCategory;
use App\Models\AnalysisType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisTypeAdminController extends Controller
{
    public function index(): Response
    {
        $categories = AnalysisCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount('analysisTypes')
            ->get();

        $types = AnalysisType::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $grouped = $categories->map(function (AnalysisCategory $category) use ($types) {
            $items = $types
                ->where('category_id', $category->id)
                ->values()
                ->map(fn (AnalysisType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'category_id' => $type->category_id,
                    'category' => $type->category?->slug,
                    'category_label' => $type->category?->name,
                    'default_price' => $type->default_price,
                    'is_active' => $type->is_active,
                    'sort_order' => $type->sort_order,
                ]);

            return [
                'id' => $category->id,
                'category' => $category->slug,
                'label' => $category->name,
                'is_active' => $category->is_active,
                'items' => $items,
                'count' => $items->count(),
            ];
        })->values();

        return Inertia::render('admin/prices', [
            'groups' => $grouped,
            'categories' => $categories->map(fn (AnalysisCategory $category) => [
                'id' => $category->id,
                'value' => (string) $category->id,
                'slug' => $category->slug,
                'label' => $category->name,
                'is_active' => $category->is_active,
                'procedures_count' => $category->analysis_types_count,
            ])->values(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);
        $slug = $this->uniqueSlug($name);

        AnalysisCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => ((int) AnalysisCategory::query()->max('sort_order')) + 1,
            'is_active' => true,
        ]);

        return back()->with('success', "Category “{$name}” added.");
    }

    public function updateCategory(Request $request, AnalysisCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update([
            'name' => trim($data['name']),
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(AnalysisCategory $category): RedirectResponse
    {
        if ($category->analysisTypes()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Move or delete procedures in this category before removing it.',
            ]);
        }

        $category->delete();

        return back()->with('success', 'Category removed.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:analysis_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:analysis_categories,id'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $maxSort = (int) AnalysisType::query()->max('sort_order');

        AnalysisType::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'category_id' => $data['category_id'],
            'default_price' => $data['default_price'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('success', 'Procedure added to the catalog.');
    }

    public function update(Request $request, AnalysisType $analysisType): RedirectResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('analysis_types', 'code')->ignore($analysisType->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:analysis_categories,id'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $analysisType->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'category_id' => $data['category_id'],
            'default_price' => $data['default_price'],
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Procedure updated.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (AnalysisCategory::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
