<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
$receiving = App\Models\User::where('email','receiving@nppc.local')->first();
$type = App\Models\AnalysisType::where('code','WW-08')->first();
$svc = app(App\Services\JobOrderService::class);
// simulate via HTTP kernel is hard; use models
