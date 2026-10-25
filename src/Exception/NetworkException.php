<?php

declare(strict_types=1);

namespace VkApi\Exception;

use RuntimeException;
use Throwable;

final class NetworkException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
