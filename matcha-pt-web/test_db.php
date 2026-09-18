<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$session = App\Models\SessionModel::latest('session_id')->first();
$cacheKey = "scoring.session.{$session->session_id}";
$sessionScores = Illuminate\Support\Facades\Cache::get($cacheKey, []);

echo json_encode($sessionScores, JSON_PRETTY_PRINT);
