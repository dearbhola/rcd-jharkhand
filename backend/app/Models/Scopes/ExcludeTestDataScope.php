<?php

namespace App\Models\Scopes;

use App\Support\TestDataMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExcludeTestDataScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app(TestDataMode::class)->includesTestData()) {
            $builder->where($model->qualifyColumn('is_test'), false);
        }
    }
}
