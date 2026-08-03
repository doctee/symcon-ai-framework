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
    public const FAILURE_GROUP_ENDPOINT_TIMEOUT = 'group_endpoint_timeout';
    public const FAILURE_GROUP_MEMBER_OFFLINE = 'group_member_offline';
    public const FAILURE_GROUP_MEMBER_STALE = 'group_member_stale';
    public const FAILURE_GROUP_PARTIAL_FEEDBACK = 'group_partial_feedback';
    public const FAILURE_GROUP_PROJECTION_MISMATCH = 'group_projection_mismatch';
    public const FAILURE_MANUAL_ACTIVATION_REQUIRED = 'manual_activation_required';

    public function __construct(
        private readonly string $failureClass,
        private readonly string $capability,
        /** @var array<string, mixed> */
        private readonly array $details = []
    ) {
        if (
            !in_array(
                $failureClass,
                [
                    self::FAILURE_FEEDBACK_TIMEOUT,
                    self::FAILURE_DEVICE_OFFLINE,
                    self::FAILURE_GROUP_ENDPOINT_TIMEOUT,
                    self::FAILURE_GROUP_MEMBER_OFFLINE,
                    self::FAILURE_GROUP_MEMBER_STALE,
                    self::FAILURE_GROUP_PARTIAL_FEEDBACK,
                    self::FAILURE_GROUP_PROJECTION_MISMATCH,
                    self::FAILURE_MANUAL_ACTIVATION_REQUIRED,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException('Unsupported ControlLight command failure class.');
        }

        parent::__construct(
            $failureClass === self::FAILURE_MANUAL_ACTIVATION_REQUIRED
                ? sprintf('Manual activation required: %s [%s]', $capability, $failureClass)
                : sprintf(
                    'Authoritative feedback confirmation timed out: %s [%s]',
                    $capability,
                    $failureClass
                )
        );
    }

    public function failureClass(): string
    {
        return $this->failureClass;
    }

    public function capability(): string
    {
        return $this->capability;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
