<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ListQueryFilters
{
    public static function applyDateFilters(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('date')) {
            static::applySingleDate($query, $column, (string) $request->query('date'));
        }

        if ($request->filled('month')) {
            $query->whereMonth($column, $request->query('month'));
        }

        if ($request->filled('year')) {
            $query->whereYear($column, $request->query('year'));
        }

        if ($request->filled('start_date')) {
            static::applyStartDate($query, $column, (string) $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            static::applyEndDate($query, $column, (string) $request->query('end_date'));
        }
    }

    public static function searchTerm(Request $request): ?string
    {
        $search = trim((string) $request->query('search', ''));

        return $search === '' ? null : $search;
    }

    private static function applySingleDate(Builder $query, string $column, string $date): void
    {
        if (static::isDateOnlyColumn($column)) {
            $query->whereDate($column, $date);

            return;
        }

        $start = static::localDate($date)->startOfDay()->utc();
        $end = static::localDate($date)->endOfDay()->utc();

        $query->whereBetween($column, [$start, $end]);
    }

    private static function applyStartDate(Builder $query, string $column, string $date): void
    {
        if (static::isDateOnlyColumn($column)) {
            $query->whereDate($column, '>=', $date);

            return;
        }

        $query->where($column, '>=', static::localDate($date)->startOfDay()->utc());
    }

    private static function applyEndDate(Builder $query, string $column, string $date): void
    {
        if (static::isDateOnlyColumn($column)) {
            $query->whereDate($column, '<=', $date);

            return;
        }

        $query->where($column, '<=', static::localDate($date)->endOfDay()->utc());
    }

    private static function localDate(string $date): Carbon
    {
        return Carbon::parse($date, 'Asia/Jakarta');
    }

    private static function isDateOnlyColumn(string $column): bool
    {
        return str_ends_with($column, '_date') || in_array($column, [
            'date',
            'order_date',
            'issue_date',
        ], true);
    }
}
