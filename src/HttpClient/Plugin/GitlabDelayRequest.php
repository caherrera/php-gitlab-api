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
    private $lastRequest;

    public function __construct()
    {
        $this->lastRequest = [];
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
        if (count($this->lastRequest) < 5) {
            $this->lastRequest[] = new DateTime();
        } else {
            $diff              = ($this->lastRequest[0]->diff($this->lastRequest[count($this->lastRequest) - 1]))->s;
            $sleep             = 60 - $diff + 1;
            $this->lastRequest = [];
            sleep($sleep ?? 0);
        }
    }
}
