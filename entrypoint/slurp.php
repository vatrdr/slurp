<?php

declare(strict_types=1);

use Bunny\Client as BunnyClient;
use Dotenv\Dotenv;
use GuzzleHttp\Client as GuzzleClient;
use React\EventLoop\Loop;
use Vatradar\Slurp\Env;
use Vatradar\Slurp\Processor;
use Vatradar\Vatsimclient\Client as VatsimClient;

require __DIR__ . '/../vendor/autoload.php';

$env = Dotenv::createImmutable(__DIR__ . '/../');
$env->safeLoad();
$loop = Loop::get();

function memoryUsage(): void
{
    echo sprintf(
        "[MemoryUsage] Current %.1f MB / Max %.1f MB",
        (memory_get_usage() / 1024 / 1024),
        (memory_get_peak_usage() / 1024 / 1024)
    ) . PHP_EOL;
}

try {
    $vatsim = new VatsimClient(new GuzzleClient(), ['bootUri' => Env::get('SLURP_BOOTURI')]);
    $vatsim->bootstrap();

    $bunny = (new BunnyClient([
        'host' => Env::get('RB_HOST'),
        'vhost' => Env::get('RB_VHOST'),
        'user' => Env::get('RB_USER'),
        'password' => Env::get('RB_PASS')
    ]))->connect();
    $channel = $bunny->channel();

    $slurp = new Processor($loop, $vatsim, $channel);
} catch (Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
    // just restart the container
    exit(1);
}

// run now
$slurp->run();
memoryUsage();

// set up future runs
$loop->addPeriodicTimer(Env::get('SLURP_TIMER'), function () use ($slurp) {
    $slurp->run();
});

// memory usage
$loop->addPeriodicTimer(120, function () {
    memoryUsage();
});
