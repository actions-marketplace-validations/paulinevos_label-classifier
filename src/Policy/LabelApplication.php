<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Policy;

use RuntimeException;

enum LabelApplication: string
{
    case Auto = 'auto';
    case Advisory = 'advisory';
    case Manual = 'manual';

    /** @throws RuntimeException on an unknown application mode */
    public static function fromString(string $value): self
    {
        $application = self::tryFrom($value);

        if ($application === null) {
            throw new RuntimeException(sprintf(
                '"%s" is not a label application mode. Use one of: auto, advisory, manual.',
                $value,
            ));
        }

        return $application;
    }
}
