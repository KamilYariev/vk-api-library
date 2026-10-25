<?php

declare(strict_types=1);

namespace VkApi\Tests;

use RuntimeException;
use VkApi\Exception\NetworkException;
use VkApi\Http\HttpClientInterface;
use VkApi\Http\HttpResponse;
use Throwable;

final class FakeHttpClient implements HttpClientInterface
{
    /**
     * @var list<HttpResponse|Throwable>
     */
    private array $queue;

    /**
     * @var list<array{url: string, params: array<string, mixed>, headers: array<string, string>}>
     */
    public array $requests = [];

    /**
     * @param list<HttpResponse|Throwable> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function post(string $url, array $formParams, array $headers = []): HttpResponse
    {
        $this->requests[] = [
            'url' => $url,
            'params' => $formParams,
            'headers' => $headers,
        ];

        if ($this->queue === []) {
            throw new RuntimeException('FakeHttpClient: очередь ответов пуста.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}
