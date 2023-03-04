<?php

declare(strict_types=1);

namespace Vatradar\Slurp;

use Bunny\Channel;
use DateTimeImmutable;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use Vatradar\Vatsimclient\Client;

class Processor
{
    private LoopInterface $loop;
    private Client $vatsim;
    private Channel $mq;
    private DateTimeImmutable $ts;

    public function __construct(LoopInterface $loop, Client $vatsim, Channel $mq)
    {
        $this->loop = $loop;
        $this->vatsim = $vatsim;
        $this->mq = $mq;
        $this->ts = new DateTimeImmutable();
    }

    public function run(): void
    {
        $this->get()->then(function ($data) {
            if (!empty($data)) {
                $this->put($data);
            }
        });
    }

    public function get(): PromiseInterface
    {
        $deferred = new Deferred();

        $this->loop->futureTick(function () use ($deferred) {
            try {
                $data = $this->vatsim->retrieve();
            } catch (Throwable) {
                $deferred->reject("Retrieval failure");
                return;
            }

            $timestamp = DateTimeImmutable::createFromFormat('YmdHis', $data->general->update);

            if (($this->ts->diff($timestamp))->format('%s') > 0) {
                $this->ts = $timestamp;
                $deferred->resolve($data);
            } else {
                $deferred->resolve();
            }
        });

        return $deferred->promise();
    }


    public function put(mixed $data): PromiseInterface
    {
        $deferred = new Deferred();

        $this->loop->futureTick(function () use ($deferred, $data) {
            try {
                $this->mq->publish(serialize($data), [], 'vatsim.input');
            } catch (Throwable) {
                $deferred->reject("Exchange Push Failure");
            }

            $deferred->resolve(true);
        });

        return $deferred->promise();
    }
}
