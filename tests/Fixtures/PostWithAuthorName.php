<?php

namespace Marcelgwerder\ApiHandler\Tests\Fixtures;

/**
 * Its appended accessor loads the "user" relation while the model is turned
 * into an array: the relations cleanup must remove it from the response.
 */
class PostWithAuthorName extends Post
{
    protected $table = 'posts';

    protected $appends = ['author_name'];

    public function getForeignKey()
    {
        // Same relations as Post (comments.post_id)
        return 'post_id';
    }

    public function getAuthorNameAttribute()
    {
        return $this->user->first_name;
    }
}
