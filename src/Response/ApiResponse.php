<?php

declare(strict_types=1);

namespace VkApi\Response;

use JsonException;
use VkApi\Exception\InvalidResponseException;
use VkApi\Http\HttpResponse;

final class ApiResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(private array $payload)
    {
    }

    public static function fromHttpResponse(HttpResponse $httpResponse): self
    {
        try {
            $payload = json_decode($httpResponse->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidResponseException(
                'VK API вернул ответ, который не удалось разобрать как JSON.',
                null,
                $httpResponse->statusCode,
                $exception
            );
        }

        if (!is_array($payload)) {
            throw new InvalidResponseException(
                'VK API вернул JSON неожиданного формата.',
                null,
                $httpResponse->statusCode
            );
        }

        return new self($payload);
    }

    public function hasError(): bool
    {
        return isset($this->payload['error']) && is_array($this->payload['error']);
    }

    public function errorCode(): ?int
    {
        if (!$this->hasError()) {
            return null;
        }

        $code = $this->payload['error']['error_code'] ?? null;

        return is_numeric($code) ? (int) $code : null;
    }

    public function errorMessage(): string
    {
        if (!$this->hasError()) {
            return 'Неизвестная ошибка VK API.';
        }

        $message = $this->payload['error']['error_msg'] ?? 'Неизвестная ошибка VK API.';

        return is_string($message) ? $message : 'Неизвестная ошибка VK API.';
    }

    public function response(): mixed
    {
        return $this->payload['response'] ?? null;
    }
}
