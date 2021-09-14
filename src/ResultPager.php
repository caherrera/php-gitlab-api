<?php

declare(strict_types=1);

namespace Gitlab;

use Gitlab\Api\ApiInterface;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Message\ResponseMediator;
use ValueError;

/**
 * This is the result pager class.
 *
 * @author Ramon de la Fuente <ramon@future500.nl>
 * @author Mitchel Verschoof <mitchel@future500.nl>
 * @author Graham Campbell <graham@alt-three.com>
 */
final class ResultPager implements ResultPagerInterface
{
    /**
     * The default number of entries to request per page.
     *
     * @var int
     */
    private const PER_PAGE = 50;

    /**
     * The client to use for pagination.
     *
     * @var Client
     */
    private $client;

    /**
     * The number of entries to request per page.
     *
     * @var int
     */
    private $perPage;

    /**
     * The pagination result from the API.
     *
     * @var array<string,string>
     */
    private $pagination;

    /**
     * Create a new result pager instance.
     *
     * @param  Client  $client
     * @param  int|null  $perPage
     *
     * @return void
     */
    public function __construct(Client $client, int $perPage = null)
    {
        if (null !== $perPage && ($perPage < 1)) {
            throw new ValueError(\sprintf('%s::__construct(): Argument #2 ($perPage) must be greater than 1, or null', self::class));
        }

        $this->client     = $client;
        $this->perPage    = $perPage ?? self::PER_PAGE;
        $this->pagination = [];
    }

    /**
     * Fetch all results from an api call.
     *
     * @param  ApiInterface  $api
     * @param  string  $method
     * @param  array  $parameters
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    public function fetchAll(ApiInterface $api, string $method, array $parameters = [])
    {
        return \iterator_to_array($this->fetchAllLazy($api, $method, $parameters));
    }

    /**
     * Lazily fetch all results from an api call.
     *
     * @param  ApiInterface  $api
     * @param  string  $method
     * @param  array  $parameters
     *
     * @return \Generator
     * @throws \Http\Client\Exception
     *
     */
    public function fetchAllLazy(ApiInterface $api, string $method, array $parameters = [])
    {
        static $num_request_per_minute = 0;
        /** @var mixed $value */
        foreach ($this->fetch($api, $method, $parameters) as $value) {
            yield $value;
        }
        $num_request_per_minute++;

        while ($this->hasNext()) {
            /** @var mixed $value */
            foreach ($this->fetchNext() as $value) {
                yield $value;
            }
            $num_request_per_minute++;
            if ($num_request_per_minute > 5) {
                sleep(60);
            }
        }
    }

    /**
     * Fetch a single result from an api call.
     *
     * @param  ApiInterface  $api
     * @param  string  $method
     * @param  array  $parameters
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    public function fetch(ApiInterface $api, string $method, array $parameters = [])
    {
        $result = $api->perPage($this->perPage)->$method(...$parameters);

        if ( ! \is_array($result)) {
            throw new RuntimeException('Pagination of this endpoint is not supported.');
        }

        $this->postFetch();

        return $result;
    }

    /**
     * Refresh the pagination property.
     *
     * @return void
     */
    private function postFetch(): void
    {
        $response = $this->client->getLastResponse();

        $this->pagination = null === $response ? [] : ResponseMediator::getPagination($response);
    }

    /**
     * Check to determine the availability of a next page.
     *
     * @return bool
     */
    public function hasNext()
    {
        return isset($this->pagination['next']);
    }

    /**
     * Fetch the next page.
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    public function fetchNext()
    {
        return $this->get('next');
    }

    /**
     * @param  string  $key
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    private function get(string $key)
    {
        $pagination = $this->pagination[$key] ?? null;

        if (null === $pagination) {
            return [];
        }

        $result = $this->client->getHttpClient()->get($pagination);

        $content = ResponseMediator::getContent($result);

        if ( ! \is_array($content)) {
            throw new RuntimeException('Pagination of this endpoint is not supported.');
        }

        $this->postFetch();

        return $content;
    }

    /**
     * Check to determine the availability of a previous page.
     *
     * @return bool
     */
    public function hasPrevious()
    {
        return isset($this->pagination['prev']);
    }

    /**
     * Fetch the previous page.
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    public function fetchPrevious()
    {
        return $this->get('prev');
    }

    /**
     * Fetch the first page.
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    public function fetchFirst()
    {
        return $this->get('first');
    }

    /**
     * Fetch the last page.
     *
     * @return array
     * @throws \Http\Client\Exception
     *
     */
    public function fetchLast()
    {
        return $this->get('last');
    }
}
