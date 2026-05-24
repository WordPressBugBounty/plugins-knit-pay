<?php

declare(strict_types=1);

namespace GautamMKGarg\PsrForWordPress\Cache\Exception;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Exception thrown when an invalid cache key or argument is provided.
 *
 * Extends PHP's native InvalidArgumentException while also implementing
 * the PSR-16 InvalidArgumentException marker interface.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
}
