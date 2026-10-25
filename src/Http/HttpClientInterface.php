<?php

declare(strict_types=1);

namespace VkApi\Http;

interface HttpClientInterface
{
    /**
     * Отправляет POST-запрос с form-urlencoded параметрами.
     *
     * @param array<string, mixed> $formParams
     * @param array<string, string> $headers
     */
    public function post(string $url, array $formParams, array $headers = []): HttpResponse;
}
