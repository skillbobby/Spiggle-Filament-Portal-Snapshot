<?php

namespace Spiggle\FilamentPortalSnapshot\Enums;

enum ScheduleFrequency: string
{
    case Hourly = 'hourly';
    case EverySixHours = 'every_six_hours';
    case Daily = 'daily';
    case Midnight = 'midnight';
    case Weekly = 'weekly';
    case Cron = 'cron';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Every hour',
            self::EverySixHours => 'Every 6 hours',
            self::Daily => 'Daily at a chosen time',
            self::Midnight => 'Every night at 00:00',
            self::Weekly => 'Weekly',
            self::Cron => 'Custom cron expression',
        };
    }

    public function toCron(?string $time = null, ?int $weekday = null, ?string $expression = null): string
    {
        if ($this === self::Cron) {
            return $expression ?: '* * * * *';
        }

        [$hour, $minute] = self::splitTime($time ?? '00:00');

        return match ($this) {
            self::Hourly => sprintf('%d * * * *', $minute),
            self::EverySixHours => sprintf('%d */6 * * *', $minute),
            self::Daily => sprintf('%d %d * * *', $minute, $hour),
            self::Midnight => '0 0 * * *',
            self::Weekly => sprintf('%d %d * * %d', $minute, $hour, $weekday ?? 1),
            default => '0 0 * * *',
        };
    }

    /** @return array{0: int, 1: int} */
    public static function splitTime(string $time): array
    {
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return [$hour, $minute];
    }

    public static function cronIsDue(string $expression, ?\DateTimeInterface $now = null): bool
    {
        $now = $now ? \DateTimeImmutable::createFromInterface($now) : new \DateTimeImmutable('now');
        $fields = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($fields) < 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = array_slice($fields, 0, 5);

        return self::fieldMatches($minute, (int) $now->format('i'))
            && self::fieldMatches($hour, (int) $now->format('G'))
            && self::fieldMatches($day, (int) $now->format('j'))
            && self::fieldMatches($month, (int) $now->format('n'))
            && self::fieldMatches($weekday, (int) $now->format('w'));
    }

    private static function fieldMatches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        foreach (explode(',', $field) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (str_contains($part, '/')) {
                [$range, $step] = explode('/', $part, 2);
                $step = max(1, (int) $step);

                if ($range === '*' || $range === '') {
                    if ($value % $step === 0) {
                        return true;
                    }
                    continue;
                }

                if (str_contains($range, '-')) {
                    [$from, $to] = array_map('intval', explode('-', $range, 2));
                    if ($value >= $from && $value <= $to && ($value - $from) % $step === 0) {
                        return true;
                    }
                    continue;
                }

                if ((int) $range === $value && $value % $step === 0) {
                    return true;
                }
                continue;
            }

            if (str_contains($part, '-')) {
                [$from, $to] = array_map('intval', explode('-', $part, 2));
                if ($value >= $from && $value <= $to) {
                    return true;
                }
                continue;
            }

            if ((int) $part === $value) {
                return true;
            }
        }

        return false;
    }
}
