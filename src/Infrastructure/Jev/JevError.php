<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Infrastructure\Jev;

use RuntimeException;

final class JevError extends RuntimeException
{
    public static function requestFailed(int $status, string $body): self
    {
        return new self(sprintf('The Jev API request failed with %d: %s', $status, $body));
    }

    public static function invalidPayload(string $reason): self
    {
        return new self(sprintf('The Jev API returned an unexpected payload: %s', $reason));
    }
}
