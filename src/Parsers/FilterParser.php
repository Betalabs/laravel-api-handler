<?php

namespace Marcelgwerder\ApiHandler;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FilterParser implements Parser
{
    protected $filters = [];

    public function parse(Request $request): void
    {
        dd($request->all());

        // ?filter-not[blub][blub]
    }

    public function apply(Builder $builder, Model $model)
    {

    }
}
