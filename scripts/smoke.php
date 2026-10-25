<?php

use App\Models\ShoppingSession;
use App\Services\DecisionService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$intent = $argv[1] ?? 'Find wireless headphones under $100 with active noise cancellation and at least 20 hours of battery life.';

/** @var ShoppingSession $session */
$session = app(DecisionService::class)->run($intent);
$recommendation = $session->recommendation;

echo "INTENT: {$intent}\n";
echo 'REQUIREMENTS: '.json_encode($session->requirements, JSON_PRETTY_PRINT)."\n";
echo "VERDICT: {$recommendation->verdict}\n";
echo "SCORE: {$recommendation->score}\n";
echo "NARRATIVE [{$recommendation->narrative_source}]: {$recommendation->narrative}\n";
echo 'ENGINE: '.json_encode($recommendation->engine, JSON_PRETTY_PRINT)."\n";
echo "CANDIDATES:\n";
foreach ($recommendation->candidates as $candidate) {
    echo sprintf(
        "  %-22s %-9s %3d  %s\n",
        $candidate['product']['name'],
        $candidate['verdict'],
        $candidate['score'],
        $candidate['reason'],
    );
}
echo 'SESSION: '.$session->uuid."\n";
