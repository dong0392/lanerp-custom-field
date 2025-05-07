<?php

namespace lanerp\dong\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WithCompanyIdScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $prefix = trim(strrchr($builder->getQuery()->from, ' '));
        $prefix = $prefix ?: $builder->getQuery()->from;
        try {
            $companyId = user()->company_id;//
            $builder->where($prefix . '.company_id', $companyId);
        } catch (\Exception $e) {
            //未登录，默认不带公司id，最好使用->withoutCompanyId()方法
        }
    }
}
