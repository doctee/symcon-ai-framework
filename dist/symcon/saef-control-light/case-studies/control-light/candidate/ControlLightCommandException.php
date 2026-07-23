<?php
declare(strict_types=1);

namespace SAEF\CaseStudy\ControlLight;

use InvalidArgumentException;
use RuntimeException;

/**
 * Classified ControlLight command failure for callers and diagnostics.
 */
final class ControlLightCommandException extends RuntimeException
{
    public const FAILURE_FEEDBACK_TIMEOUT = 'feedback_timeout';
    public const FAILURE_DEVICE_OFFLINE = 'device_offline';

    public function __construct(
        private readonly string $failureClass,
        private readonly string $capability
    ) {
        if (!in_array($failureClass, [self::FAILURE_FEEDBACK_TIMEOUT, self::FAILURE_DEVICE_OFFLINE], true)) {
            throw new InvalidArgumentException('Unsupported ControlLight command failure class.');
        }

        parent::__construct(sprintf(
            'Authoritative feedback confirmation timed out: %s [%s]',
            $capability,
            $failureClass
        ));
    }

    public function failureClass(): string
    {
        return $this->failureClass;
    }

    public function capability(): string
    {
        return $this->capability;
    }
}
