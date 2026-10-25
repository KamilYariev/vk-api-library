<?php

declare(strict_types=1);

namespace VkApi\Auth;

use VkApi\Exception\AuthenticationException;

/**
 * Удобен для тестов и краткоживущих скриптов.
 * Не передавайте сюда токен, записанный непосредственно в исходном коде приложения.
 */
final class StaticTokenProvider implements TokenProviderInterface
{
    public function __construct(private readonly string $token)
    {
        if (trim($this->token) === '') {
            throw new AuthenticationException('Токен VK API не может быть пустым.');
        }
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
