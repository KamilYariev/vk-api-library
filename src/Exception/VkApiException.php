<?php

declare(strict_types=1);

namespace VkApi\Exception;

use RuntimeException;
use Throwable;

class VkApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $vkErrorCode = null,
        public readonly ?int $httpStatusCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $vkErrorCode ?? $httpStatusCode ?? 0, $previous);
    }
}
