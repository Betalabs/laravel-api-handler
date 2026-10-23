<?php

namespace Marcelgwerder\ApiHandler\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = [];

    /**
     * @Relation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @Relation
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @Relation
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Polymorphic relation: not supported by "_with".
     *
     * @Relation
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Polymorphic relation: not supported by "_with".
     *
     * @Relation
     */
    public function cover()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    /**
     * Not annotated with Relation, so it can not be loaded with "_with".
     */
    public function hiddenComments()
    {
        return $this->hasMany(Comment::class);
    }
}
