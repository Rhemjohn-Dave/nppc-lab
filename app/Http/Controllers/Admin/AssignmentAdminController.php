<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisCategory;
use App\Models\AnalysisType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentAdminController extends Controller
{
    public function index(): Response
    {
        $analysts = User::role('analyst')->orderBy('name')->get(['id', 'name', 'email']);
        $categories = AnalysisCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $types = AnalysisType::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $matrix = $analysts->map(fn (User $analyst) => [
            'id' => $analyst->id,
            'name' => $analyst->name,
            'email' => $analyst->email,
            'analysis_type_ids' => $analyst->analysisTypes()->pluck('analysis_types.id')->values(),
        ]);

        $groups = $categories->map(function (AnalysisCategory $category) use ($types) {
            $items = $types
                ->where('category_id', $category->id)
                ->values()
                ->map(fn (AnalysisType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'category' => $category->slug,
                    'category_label' => $category->name,
                ]);

            return [
                'category' => $category->slug,
                'label' => $category->name,
                'items' => $items,
                'type_ids' => $items->pluck('id')->values(),
            ];
        })->filter(fn (array $group) => $group['items']->isNotEmpty())->values();

        return Inertia::render('admin/assignments', [
            'analysts' => $matrix,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('analyst'), 404);

        $data = $request->validate([
            'analysis_type_ids' => ['array'],
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
        ]);

        $user->analysisTypes()->sync($data['analysis_type_ids'] ?? []);

        return back()->with('success', 'Assignments updated.');
    }
}
