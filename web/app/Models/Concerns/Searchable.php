<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

trait Searchable
{
    /**
     * @return array<string>
     */
    abstract protected static function searchableColumns(): array;

    public static function search(string $query = '', int $limit = 25): Collection
    {
        return static::query()
            ->when($query, function (Builder $builder) use ($query) {
                $builder->whereAny(static::searchableColumns(), 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }
}
