<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class OwnTracksDayWindow
{
    /**
     * Convert one user-selected local calendar day into a UTC half-open range.
     *
     * @return array{
     *     selectedDate: string,
     *     selectedTimeZone: string,
     *     from: int,
     *     to: int,
     *     durationSeconds: int
     * }
     */
    public static function fromLocalDate(
        string $selectedDate,
        string $selectedTimeZone
    ): array {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $selectedDate) !== 1) {
            throw new InvalidArgumentException('Selected date is invalid.');
        }
        if (
            $selectedTimeZone === ''
            || strlen($selectedTimeZone) > 64
        ) {
            throw new InvalidArgumentException('Selected time zone is invalid.');
        }
        try {
            $timeZone = new DateTimeZone($selectedTimeZone);
            $start = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $selectedDate,
                $timeZone
            );
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'Selected time zone is invalid.',
                0,
                $exception
            );
        }
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $start === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $start->format('Y-m-d') !== $selectedDate
        ) {
            throw new InvalidArgumentException('Selected date is invalid.');
        }
        $end = $start->modify('+1 day');
        $from = $start->getTimestamp();
        $to = $end->getTimestamp();

        return [
            'selectedDate' => $selectedDate,
            'selectedTimeZone' => $selectedTimeZone,
            'from' => $from,
            'to' => $to,
            'durationSeconds' => $to - $from,
        ];
    }
}
