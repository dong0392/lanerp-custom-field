<?php

namespace lanerp\dong\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SoftDeleteScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 添加 whereNull('deleted_at') 条件
        $prefix = trim(strrchr($builder->getQuery()->from, ' '));
        $prefix = $prefix ?: $builder->getQuery()->from;
        $builder->whereNull($prefix . '.deleted_at');
    }
}
