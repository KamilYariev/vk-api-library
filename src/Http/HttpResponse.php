<?php

declare(strict_types=1);

namespace VkApi\Http;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers = []
    ) {
        if ($this->statusCode < 0 || $this->statusCode > 599) {
            throw new \InvalidArgumentException('HTTP status code должен находиться в диапазоне 0–599.');
        }
    }
}
