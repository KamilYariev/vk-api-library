<?php

declare(strict_types=1);

namespace VkApi\Model;

use VkApi\Exception\InvalidResponseException;

final class VKUser
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public ?string $avatarUrl
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException('ID пользователя должен быть положительным числом.');
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function fromApi(array $user): self
    {
        $id = $user['id'] ?? null;
        if (!is_numeric($id) || (int) $id <= 0) {
            throw new InvalidResponseException('VK API вернул пользователя без корректного ID.');
        }

        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        $avatarUrl = $user['photo_200'] ?? $user['photo_100'] ?? null;

        return new self(
            (int) $id,
            is_string($firstName) ? $firstName : '',
            is_string($lastName) ? $lastName : '',
            is_string($avatarUrl) && $avatarUrl !== '' ? $avatarUrl : null
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
