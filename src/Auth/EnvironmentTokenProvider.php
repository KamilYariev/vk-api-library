<?php

declare(strict_types=1);

namespace VkApi\Auth;

use VkApi\Exception\AuthenticationException;

final class EnvironmentTokenProvider implements TokenProviderInterface
{
    public function __construct(
        private readonly string $environmentVariable = 'VK_ACCESS_TOKEN'
    ) {
        if ($this->environmentVariable === '') {
            throw new \InvalidArgumentException('Имя переменной окружения не может быть пустым.');
        }
    }

    public function getToken(): string
    {
        $token = getenv($this->environmentVariable);

        if (!is_string($token) || trim($token) === '') {
            throw new AuthenticationException(
                sprintf('Не задан токен VK API в переменной окружения %s.', $this->environmentVariable)
            );
        }

        return trim($token);
    }
}
