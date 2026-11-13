<?php

namespace App\Http\Controllers;

use App\Support\Accounting\SalesMonitor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function index(Request $request, SalesMonitor $monitor): Response
    {
        $payload = $monitor->build([
            'date' => $request->string('date')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'q' => $request->string('q')->toString() ?: null,
            'classification' => $request->string('classification')->toString() ?: null,
            'payment_mode' => $request->string('payment_mode')->toString() ?: null,
        ]);

        return Inertia::render('accounting/index', $payload);
    }
}
