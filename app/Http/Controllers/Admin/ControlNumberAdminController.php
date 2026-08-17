<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReferenceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ControlNumberAdminController extends Controller
{
    public function edit(ReferenceNumberService $referenceNumbers): Response
    {
        return Inertia::render('admin/control-number', [
            'counter' => $referenceNumbers->status(),
        ]);
    }

    public function update(Request $request, ReferenceNumberService $referenceNumbers): RedirectResponse
    {
        $data = $request->validate([
            'next_number' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $nextReference = $referenceNumbers->setNextNumber((int) $data['next_number']);

        return back()->with(
            'success',
            "Next control number set to {$nextReference}.",
        );
    }
}
