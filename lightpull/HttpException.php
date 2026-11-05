<?php

declare(strict_types=1);

namespace Lightpull;

use RuntimeException;

/**
 * Stops the request with an HTTP status. A 3xx status with a Location header
 * becomes a redirect; anything else renders the error page.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $message = '',
        private readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function redirect(string $url): self
    {
        return new self(302, '', ['Location' => $url]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
