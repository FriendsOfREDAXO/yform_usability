<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

use DateTimeImmutable;

/**
 * Zeitraum aus Vorgabe oder Von/Bis. Beide Enden sind ganze Tage, das Bis-Datum zählt mit.
 */
final class DateRange
{
    public const PRESETS = ['today', 'last7', 'last30', 'this_month', 'this_year', 'last_year', 'custom'];

    private function __construct(
        public readonly ?DateTimeImmutable $from,
        public readonly ?DateTimeImmutable $to,
        public readonly ?string $error = null,
    ) {
    }

    public static function resolve(string $preset, string $from, string $to, DateTimeImmutable $today): ?self
    {
        $today = $today->setTime(0, 0);
        $from = trim($from);
        $to = trim($to);

        if ('' === $preset && ('' !== $from || '' !== $to)) {
            $preset = 'custom';
        }

        switch ($preset) {
            case 'today':
                return new self($today, $today);
            case 'last7':
                return new self($today->modify('-6 days'), $today);
            case 'last30':
                return new self($today->modify('-29 days'), $today);
            case 'this_month':
                return new self($today->modify('first day of this month'), $today->modify('last day of this month'));
            case 'this_year':
                return new self($today->setDate((int) $today->format('Y'), 1, 1), $today->setDate((int) $today->format('Y'), 12, 31));
            case 'last_year':
                $year = (int) $today->format('Y') - 1;
                return new self($today->setDate($year, 1, 1), $today->setDate($year, 12, 31));
            case 'custom':
                if ('' === $from && '' === $to) {
                    return null;
                }
                $fromDate = '' === $from ? null : self::parseDate($from);
                $toDate = '' === $to ? null : self::parseDate($to);
                if (('' !== $from && null === $fromDate) || ('' !== $to && null === $toDate)) {
                    return new self(null, null, 'invalid_date');
                }
                if ($fromDate && $toDate && $fromDate > $toDate) {
                    return new self($fromDate, $toDate, 'from_after_to');
                }
                return new self($fromDate, $toDate);
        }

        return null;
    }

    /**
     * Akzeptiert JJJJ-MM-TT (native Datumseingabe) und TT.MM.JJJJ.
     */
    public static function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [, $y, $mo, $d] = $m;
        } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            [, $d, $mo, $y] = $m;
        } else {
            return null;
        }

        if (!checkdate((int) $mo, (int) $d, (int) $y)) {
            return null;
        }

        return (new DateTimeImmutable())->setDate((int) $y, (int) $mo, (int) $d)->setTime(0, 0);
    }

    public function sqlFrom(): ?string
    {
        return $this->from?->format('Y-m-d 00:00:00');
    }

    public function sqlToExclusive(): ?string
    {
        return $this->to?->modify('+1 day')->format('Y-m-d 00:00:00');
    }
}
