<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** Rows per page: 25 unless the caller asks for another number, never more than 100. */
    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 25)));
    }

    /** ?updated_since=2026-10-01 — only what changed on or after that date, for keeping another system in step. */
    protected function updatedSince(Builder $query, Request $request, string $column = 'updated_at'): Builder
    {
        $since = $request->query('updated_since');

        return $since ? $query->where($column, '>=', $since) : $query;
    }
}
