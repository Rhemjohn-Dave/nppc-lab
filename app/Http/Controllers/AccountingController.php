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
        $payload = $monitor->build(
            $request->string('date')->toString() ?: null,
            $request->string('payment_mode')->toString() ?: null,
        );

        return Inertia::render('accounting/index', $payload);
    }
}
