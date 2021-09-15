<?php

declare(strict_types=1);

namespace Gitlab\HttpClient\Plugin;

use DateTime;
use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A plugin to remember the last response.
 *
 * @author Carlos Herrera <caherrera@gmail.com>
 *
 * @internal
 */
final class GitlabDelayRequest implements Plugin
{
    private $requestsPerMinute;
    private $lastRequest;

    public function __construct()
    {
        $this->requestsPerMinute = 0;
        $this->lastRequest[]     = new DateTime();
    }

    /**
     * Handle the request and return the response coming from the next callable.
     *
     * @param  RequestInterface  $request
     * @param  callable  $next
     * @param  callable  $first
     *
     * @return Promise
     */
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        return $next($request)->then(function (ResponseInterface $response) {
            self::countRequest();

            return $response;
        });
    }

    private function countRequest()
    {
        $this->lastRequest[] = new DateTime();
        if ($this->requestsPerMinute < 5) {
            $this->requestsPerMinute++;
        } else {
            sleep(60);
            $this->requestsPerMinute = 0;
            $this->lastRequest       = [];
        }
    }
}
