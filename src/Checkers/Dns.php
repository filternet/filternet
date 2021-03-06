<?php

namespace Filternet\Checkers;

use Filternet\Utility\Status;
use React\Dns\Resolver\Factory as DnsFactory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class Dns
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * Dns constructor.
     * @param $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Check domain is blocked?
     *
     * @param string $domain
     * @param string $server
     * @return PromiseInterface
     */
    public function check($domain, $server)
    {
        $dns = $this->createDns($server);

        $promise = \React\Promise\all([
            $dns->resolve($domain),
            $dns->resolve(strtoupper($domain)),
        ]);

        return $this->generateResult($promise, $domain);
    }

    /**
     * Is dns blocked?
     *
     * @param string $domain
     * @param callable $callback
     * @param string $server
     */
    public function blocked($domain, callable $callback, $server = '8.8.8.8')
    {
        $dns = $this->createDns($server);

        $dns->resolve($domain)
            ->then(function ($ip) use ($callback) {
                $callback($this->isBlocked($ip));
            });
    }

    /**
     * Create dns resolver object
     *
     * @param string $server
     * @return \React\Dns\Resolver\Resolver
     */
    protected function createDns($server)
    {
        $factory = new DnsFactory();

        return $factory->create($server, $this->loop);
    }

    /**
     * Is ip in blacklist?
     *
     * @param string $ip
     * @return bool
     */
    protected function isBlocked($ip)
    {
        return !!preg_match('/^10\.10\.34\.3[4-6]$/', $ip);
    }

    /**
     * Generate result data
     *
     * @param PromiseInterface $promise
     * @param string $domain
     * @return PromiseInterface
     */
    protected function generateResult(PromiseInterface $promise, $domain)
    {
        return $promise->then(function ($response) use ($domain) {

            $lowerStatus = $this->status($response[0]);
            $upperStatus = $this->status($response[1]);
            $date = date('Y-m-d H:i:s');

            return [
                [$domain, $response[0], $lowerStatus, $date],
                [strtoupper($domain), $response[1], $upperStatus, $date]
            ];

        });
    }

    protected function status($ip)
    {
        return $this->isBlocked($ip) ? Status::blocked() : Status::open();
    }
}