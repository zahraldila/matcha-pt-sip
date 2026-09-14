<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Scoring\ScoringService;
use App\Models\SessionModel as Session;
use Illuminate\Support\Facades\Cache;

$s = Session::with(['players', 'courts'])->find(84);
$game = [
    'id' => 84,
    'sport' => 'Padel',
    'match_format' => $s->match_format,
    'scoring_system' => $s->scoring_system ?? 'Total of 3',
    'participants' => $s->players->map(fn($p) => ['id' => $p->player_id, 'name' => $p->nama])->toArray(),
    'drawing' => Cache::get('drawing.schedule_84', []),
];

echo "Session courts in DB: " . $s->courts->count() . "\n";
foreach ($s->courts as $c) {
    echo "- Court ID {$c->court_id}: {$c->nama_court}\n";
}
$dbDrawing = \App\Models\Drawing::where('session_id', 84)->first();
echo "Drawing in DB: " . ($dbDrawing ? "ID {$dbDrawing->drawing_id}" : "none") . "\n";
if ($dbDrawing) {
    $matches = \App\Models\GameMatch::where('drawing_id', $dbDrawing->drawing_id)->get();
    echo "Matches in DB count: " . $matches->count() . "\n";
    foreach ($matches as $m) {
        echo "  Match ID {$m->match_id}, nomor: {$m->nomor_match}, status: {$m->status_match}\n";
    }
}

$cacheScores = Cache::get('scoring.game_84', []);
echo "CACHE SCORES KEYS: " . json_encode(array_keys($cacheScores)) . "\n";
echo "CACHE SCORES CONTENT: " . json_encode($cacheScores, JSON_PRETTY_PRINT) . "\n";
