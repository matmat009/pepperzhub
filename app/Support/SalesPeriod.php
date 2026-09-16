<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The window the Sales screen is reporting on.
 *
 * A value object rather than a pair of loose Carbons because three things have
 * to stay in step: the bounds themselves, the period they are compared
 * against, and the day list the trend chart plots. Deriving each independently
 * at the call site is how a chart ends up covering one range while the figure
 * above it covers another.
 *
 * The displayed dates are inclusive Philippine calendar days. Database
 * queries use the start of the first day and the exclusive start of the day
 * after the last, converted to UTC by queryStart() and queryEndExclusive().
 */
final class SalesPeriod
{
    public const THIS_MONTH = 'this_month';

    public const LAST_MONTH = 'last_month';

    public const CUSTOM = 'custom';

    /**
     * Longest custom range accepted.
     *
     * The trend chart draws one point per day and the series is built in PHP,
     * so this is the only thing standing between a typo'd year and a payload
     * with thousands of points in it. A little over a year covers any
     * comparison the operator would actually make.
     */
    public const MAX_DAYS = 366;

    private function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    /** @return list<string> */
    public static function keys(): array
    {
        return [self::THIS_MONTH, self::LAST_MONTH, self::CUSTOM];
    }

    public static function named(string $key): self
    {
        $anchor = $key === self::LAST_MONTH
            ? ReportingTime::now()->subMonthNoOverflow()
            : ReportingTime::now();

        return new self($key, $anchor->startOfMonth(), $anchor->endOfMonth());
    }

    public static function custom(CarbonImmutable $start, CarbonImmutable $end): self
    {
        $start = ReportingTime::local($start);
        $end = ReportingTime::local($end);

        // Swapped bounds are a slip, not an error worth a validation message:
        // the admin means the range between the two dates either way.
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return new self(self::CUSTOM, $start->startOfDay(), $end->endOfDay());
    }

    /**
     * Resolve whatever the request asked for.
     *
     * Falls back to the named period when custom is selected without both
     * dates, so a half-filled picker still renders a page rather than an error.
     */
    public static function resolve(?string $key, ?string $start, ?string $end): self
    {
        $key = in_array($key, self::keys(), true) ? $key : self::THIS_MONTH;

        if ($key === self::CUSTOM && filled($start) && filled($end)) {
            return self::custom(
                ReportingTime::date($start),
                ReportingTime::date($end),
            );
        }

        return self::named($key === self::CUSTOM ? self::THIS_MONTH : $key);
    }

    /**
     * What this period is measured against.
     *
     * A named month compares against the calendar month before it, because
     * that is what "last month" means to the person reading it — not "the 30
     * days before the 1st". A custom range has no calendar meaning, so it
     * compares against an equal-length window ending the day before it starts.
     */
    public function previous(): self
    {
        if ($this->key !== self::CUSTOM) {
            $anchor = $this->start->subMonthNoOverflow();

            return new self($this->key, $anchor->startOfMonth(), $anchor->endOfMonth());
        }

        $end = $this->start->subDay()->endOfDay();

        return new self(
            self::CUSTOM,
            $end->subDays($this->dayCount() - 1)->startOfDay(),
            $end,
        );
    }

    public function dayCount(): int
    {
        return (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }

    /**
     * Every day in the range as Y-m-d, so the chart has a point for days that
     * took nothing — a gap and a zero read very differently on a trend line.
     *
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];
        $cursor = $this->start->startOfDay();

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    public function queryStart(): CarbonImmutable
    {
        return ReportingTime::storage($this->start->startOfDay());
    }

    public function queryEndExclusive(): CarbonImmutable
    {
        return ReportingTime::storage($this->end->addDay()->startOfDay());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'label' => $this->label(),
            'days' => $this->dayCount(),
        ];
    }

    public function label(): string
    {
        if ($this->key !== self::CUSTOM) {
            return $this->start->format('F Y');
        }

        // Same month on both ends reads as "3 – 17 Sep 2026" rather than
        // repeating the month and year the reader already has.
        if ($this->start->isSameMonth($this->end)) {
            return $this->start->format('j').' – '.$this->end->format('j M Y');
        }

        return $this->start->format('j M Y').' – '.$this->end->format('j M Y');
    }
}
