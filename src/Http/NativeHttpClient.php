<?php

declare(strict_types=1);

namespace VkApi\Http;

use VkApi\Exception\NetworkException;

final class NativeHttpClient implements HttpClientInterface
{
    public function __construct(private readonly int $timeoutSeconds = 10)
    {
        if ($this->timeoutSeconds < 1) {
            throw new \InvalidArgumentException('Тайм-аут должен быть не меньше одной секунды.');
        }
    }

    public function post(string $url, array $formParams, array $headers = []): HttpResponse
    {
        $headerLines = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'User-Agent: VkApiLibrary/1.0',
        ];

        foreach ($headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1) {
                throw new \InvalidArgumentException('Некорректное имя HTTP-заголовка.');
            }

            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => http_build_query($formParams),
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $statusCode = 0;
        $parsedHeaders = [];

        foreach ($responseHeaders as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                continue;
            }

            $separator = strpos($headerLine, ':');
            if ($separator !== false) {
                $name = strtolower(trim(substr($headerLine, 0, $separator)));
                $parsedHeaders[$name] = trim(substr($headerLine, $separator + 1));
            }
        }

        if ($body === false && $statusCode === 0) {
            throw new NetworkException('Не удалось выполнить HTTP-запрос к VK API.');
        }

        return new HttpResponse(
            $statusCode,
            is_string($body) ? $body : '',
            $parsedHeaders
        );
    }
}
