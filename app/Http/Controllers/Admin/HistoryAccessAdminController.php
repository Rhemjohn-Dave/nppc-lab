<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HistoryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HistoryAccessAdminController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/history-access', [
            'roles' => HistoryAccess::roleOptions(),
            'selected' => HistoryAccess::visibleRoles(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(HistoryAccess::AVAILABLE_ROLES)],
        ]);

        HistoryAccess::updateVisibleRoles($data['roles']);

        return back()->with('success', 'History visibility updated.');
    }
}
