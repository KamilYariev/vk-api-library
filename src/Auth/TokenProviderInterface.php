<?php

declare(strict_types=1);

namespace VkApi\Auth;

interface TokenProviderInterface
{
    public function getToken(): string;
}
