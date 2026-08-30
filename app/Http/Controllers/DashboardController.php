<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardPayloadBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPayloadBuilder $builder): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', $builder->build($user));
    }
}
