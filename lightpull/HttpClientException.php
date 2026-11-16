<?php

declare(strict_types=1);

namespace Lightpull;

use RuntimeException;

/** Any failed outgoing request. getCode() is the HTTP status, or 0 if there was no response. */
final class HttpClientException extends RuntimeException
{
}
