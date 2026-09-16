<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The business calendar used by Dashboard and Sales reporting.
 *
 * Timestamps stay in UTC in the database. Calendar dates entered or displayed
 * by the operator are Philippine dates, so query boundaries cross this class
 * on their way to storage and stored timestamps cross it on their way back to
 * calendar-day labels.
 */
final class ReportingTime
{
    public const BUSINESS_TIMEZONE = 'Asia/Manila';

    public const STORAGE_TIMEZONE = 'UTC';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::BUSINESS_TIMEZONE);
    }

    public static function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::BUSINESS_TIMEZONE)->startOfDay();
    }

    public static function local(CarbonInterface $timestamp): CarbonImmutable
    {
        return CarbonImmutable::instance($timestamp)->setTimezone(self::BUSINESS_TIMEZONE);
    }

    public static function storage(CarbonInterface $timestamp): CarbonImmutable
    {
        return CarbonImmutable::instance($timestamp)->setTimezone(self::STORAGE_TIMEZONE);
    }

    public static function dateStartForStorage(string $date): CarbonImmutable
    {
        return self::storage(self::date($date));
    }

    public static function nextDateStartForStorage(string $date): CarbonImmutable
    {
        return self::storage(self::date($date)->addDay());
    }
}
