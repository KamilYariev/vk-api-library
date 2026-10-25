<?php

declare(strict_types=1);

namespace VkApi\Model;

enum DialogType: string
{
    case USER = 'user';
    case CHAT = 'chat';
    case GROUP = 'group';
    case EMAIL = 'email';
    case UNKNOWN = 'unknown';

    public static function fromApiValue(mixed $value): self
    {
        if (!is_string($value)) {
            return self::UNKNOWN;
        }

        return self::tryFrom($value) ?? self::UNKNOWN;
    }
}
