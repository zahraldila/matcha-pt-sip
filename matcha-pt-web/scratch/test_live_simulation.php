<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SessionModel as Session;
use App\Models\User;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

$s = Session::with(['players', 'courts'])->find(84);
if (!$s) {
    echo "Session 84 not found\n";
    exit;
}
echo "SESSION 84: {$s->nama_sesi} (Status: {$s->status_session}, Host User ID: {$s->host_user_id})\n";
$hostUser = User::find($s->host_user_id);
echo "HOST: {$hostUser->name} ({$hostUser->email}, Role: {$hostUser->role})\n";

$otherPlayers = DB::table('tb_session_player')
    ->join('tb_player', 'tb_session_player.player_id', '=', 'tb_player.player_id')
    ->leftJoin('tb_user', 'tb_player.user_id', '=', 'tb_user.user_id')
    ->where('session_id', 84)
    ->where('tb_player.user_id', '!=', $s->host_user_id)
    ->select('tb_player.*', 'tb_user.email', 'tb_user.nama as user_name', 'tb_user.role')
    ->get();

echo "OTHER PLAYERS:\n";
$game = (new \App\Http\Controllers\Scoring\ScoringController())->getGameData(84);
$context = \App\Services\Scoring\ScoringService::buildMatchContext($game, 'round_1', 0);
echo "TOTAL COURTS IN SESSION: " . ($s->courts ? $s->courts->count() : 0) . "\n";
echo "MATCHES IN CONTEXT: " . count($context['matches'] ?? []) . "\n";
echo "MATCH CONTEXT: " . json_encode($context['matches'] ?? [], JSON_PRETTY_PRINT) . "\n";
