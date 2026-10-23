<?php 

namespace Marcelgwerder\ApiHandler;

use \Illuminate\Database\Query\Builder as QueryBuilder;

class CountMetaProvider extends MetaProvider
{
    /**
     * Query builder object
     *
     * @var mixed
     */
    protected $builder;

    public function __construct($title, $builder)
    {
        $builder = clone $builder;

        if($builder instanceof QueryBuilder) {
            $this->builder = $builder;
        } else {
            // toBase() keeps the global scopes of the Eloquent builder
            $this->builder = $builder->toBase();
        }

        $this->title = $title;

        //Remove offset from builder because a count doesn't work in combination with an offset
        $this->builder->offset = null;

        //Remove orders from builder because they are irrelevant for counts and can cause errors with renamed columns
        $this->builder->orders = null;
        //Their bindings must go too, otherwise the count query sends more values than placeholders
        $this->builder->bindings['order'] = [];
    }

    /**
     * Get the meta information
     *
     * @return string
     */
    public function get()
    {
        if (!empty($this->builder->groups)) {
            //Only a count column is required
            $this->builder->columns = [];
            $this->builder->selectRaw('count(*) as aggregate');
            $this->builder->limit = null;

            //Use the original builder as a subquery and count over it because counts over groups return the number of rows for each group, not for the total results
            $query = $this->builder->newQuery()->selectRaw('count(*) as aggregate from (' . $this->builder->toSql() . ') as count_table', $this->builder->getBindings());
            
            return intval($query->first()->aggregate);
        }

        return intval($this->builder->count());
    }
}
