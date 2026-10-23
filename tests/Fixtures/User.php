<?php

namespace Marcelgwerder\ApiHandler\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $guarded = [];

    /**
     * @Relation
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @Relation
     */
    public function latestPost()
    {
        return $this->hasOne(Post::class)->latestOfMany();
    }

    /**
     * @Relation
     */
    public function comments()
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }
}
