<?php

declare(strict_types=1);

use Bunny\Client as BunnyClient;
use CuyZ\Valinor\MapperBuilder;
use GuzzleHttp\Client as GuzzleClient;
use React\EventLoop\Loop;
use VatRadar\DataObjects\Vatsim\VatsimData;
use VatRadar\Env\Env;
use Slurp\Processor;
use VatRadar\VatsimClient\Client as VatsimClient;
use VatRadar\VatsimClient\DataFetcher;
use VatRadar\VatsimClient\IterableSanitizer;
use VatRadar\VatsimClient\Mapper;

require __DIR__ . '/../vendor/autoload.php';

Env::init(__DIR__.'/../');
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
    $fetcher = new DataFetcher(new GuzzleClient(), Env::get('SLURP_BOOTURI'));
    $mapper = new Mapper(new MapperBuilder(), VatsimData::class);
    $vatsim = new VatsimClient($fetcher, new IterableSanitizer(), $mapper);

    $bunny = (new BunnyClient([
        'host' => Env::get('RB_HOST'),
        'vhost' => Env::get('RB_VHOST'),
        'user' => Env::get('RB_USER'),
        'password' => Env::get('RB_PASS')
    ]))->connect();
    $channel = $bunny->channel();

} catch (Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
    // just restart the container
    exit(1);
}

function slurp(): void
{
    global $loop, $channel, $vatsim;
    $slurp = new Processor($loop, $vatsim, $channel);
    $slurp->run();
    unset($slurp);
}

// run now
slurp();
memoryUsage();

// set up future runs
$loop->addPeriodicTimer(Env::get('SLURP_TIMER'), function () {
    slurp();
});

// memory usage
$loop->addPeriodicTimer(120, function () {
    memoryUsage();
});
