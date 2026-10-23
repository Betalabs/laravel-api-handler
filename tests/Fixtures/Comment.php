<?php

namespace Marcelgwerder\ApiHandler\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $guarded = [];

    /**
     * @Relation
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
