<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$session = App\Models\SessionModel::latest('session_id')->first();
$drawings = App\Models\Drawing::where('session_id', $session->session_id)->pluck('drawing_id');
$matches = App\Models\GameMatch::whereIn('drawing_id', $drawings)->with('scores')->get();
echo json_encode(['session' => $session->toArray(), 'matches' => $matches->toArray()], JSON_PRETTY_PRINT);
