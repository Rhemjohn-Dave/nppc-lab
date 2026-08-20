<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisPackageAdminController extends Controller
{
    public function index(): Response
    {
        $packages = AnalysisPackage::query()
            ->with(['category', 'analysisTypes', 'signatory', 'resultForm'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AnalysisPackage $package) => $this->serialize($package))
            ->values();

        return Inertia::render('admin/packages', [
            'packages' => $packages,
            'categories' => AnalysisCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (AnalysisCategory $category) => [
                    'id' => $category->id,
                    'label' => $category->name,
                ])
                ->values(),
            'classifications' => OfficialAnalysisCatalog::classifications(),
            'analysisGroups' => $this->analysisGroups(),
            'analysts' => User::role('analyst')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $package = AnalysisPackage::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'default_price' => $data['default_price'],
            'classifications' => $data['classifications'] ?? [],
            'signatory_user_id' => $data['signatory_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => ((int) AnalysisPackage::query()->max('sort_order')) + 1,
        ]);

        $package->syncTypes($data['analysis_type_ids']);

        return redirect()->route('admin.packages')->with('success', 'Package created.');
    }

    public function update(Request $request, AnalysisPackage $package): RedirectResponse
    {
        $data = $this->validated($request, $package);

        $package->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'default_price' => $data['default_price'],
            'classifications' => $data['classifications'] ?? [],
            'signatory_user_id' => $data['signatory_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $package->syncTypes($data['analysis_type_ids']);

        return redirect()->route('admin.packages')->with('success', 'Package updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AnalysisPackage $package = null): array
    {
        if ($request->input('category_id') === '') {
            $request->merge(['category_id' => null]);
        }

        if ($request->input('signatory_user_id') === '') {
            $request->merge(['signatory_user_id' => null]);
        }

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('analysis_packages', 'code')->ignore($package?->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:analysis_categories,id'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'classifications' => ['nullable', 'array'],
            'classifications.*' => ['string', 'max:80'],
            'signatory_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'analysis_type_ids' => ['required', 'array', 'min:1'],
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AnalysisPackage $package): array
    {
        $linked = $package->resultForm;

        return [
            'id' => $package->id,
            'code' => $package->code,
            'name' => $package->name,
            'description' => $package->description,
            'category_id' => $package->category_id,
            'category_label' => $package->category?->name,
            'default_price' => $package->default_price,
            'classifications' => $package->classifications ?? [],
            'form_code' => $linked?->form_code ?? $package->form_code,
            'result_form' => $linked
                ? [
                    'id' => $linked->id,
                    'form_code' => $linked->form_code,
                    'name' => $linked->name,
                ]
                : null,
            'signatory_user_id' => $package->signatory_user_id,
            'signatory_name' => $package->signatory?->name,
            'is_active' => $package->is_active,
            'analysis_type_ids' => $package->orderedTypeIds(),
            'tests' => $package->analysisTypes->map(fn (AnalysisType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
            ])->values(),
        ];
    }

    /**
     * @return list<array{label: string, items: list<array{id: int, code: string, name: string}>}>
     */
    private function analysisGroups(): array
    {
        $categories = AnalysisCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $types = AnalysisType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $categories->map(function (AnalysisCategory $category) use ($types) {
            $items = $types
                ->where('category_id', $category->id)
                ->values()
                ->map(fn (AnalysisType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                ]);

            return [
                'label' => $category->name,
                'items' => $items->all(),
            ];
        })->filter(fn (array $group) => $group['items'] !== [])->values()->all();
    }
}
