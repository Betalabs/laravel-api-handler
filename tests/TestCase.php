<?php

namespace Marcelgwerder\ApiHandler\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Marcelgwerder\ApiHandler\ApiHandlerServiceProvider;
use Marcelgwerder\ApiHandler\Tests\Fixtures\Comment;
use Marcelgwerder\ApiHandler\Tests\Fixtures\Post;
use Marcelgwerder\ApiHandler\Tests\Fixtures\Tag;
use Marcelgwerder\ApiHandler\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [ApiHandlerServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('tag_id');
        });

        Schema::create('images', function (Blueprint $table) {
            $table->increments('id');
            $table->morphs('imageable');
            $table->string('url');
        });

        $ana = User::query()->create(['first_name' => 'Ana']);
        $bruno = User::query()->create(['first_name' => 'Bruno']);

        $first = Post::query()->create(['user_id' => $ana->id, 'title' => 'Alpha', 'description' => 'first post', 'views' => 10]);
        Post::query()->create(['user_id' => $bruno->id, 'title' => 'Bravo', 'description' => 'second post', 'views' => 20]);
        Post::query()->create(['user_id' => $ana->id, 'title' => 'Charlie', 'description' => null, 'views' => 30]);
        Post::query()->create(['user_id' => $bruno->id, 'title' => 'Delta', 'description' => 'fourth post', 'views' => 40]);

        Comment::query()->create(['post_id' => $first->id, 'title' => 'Nice']);
        Comment::query()->create(['post_id' => $first->id, 'title' => 'Great']);

        $php = Tag::query()->create(['name' => 'php']);
        $laravel = Tag::query()->create(['name' => 'laravel']);
        $first->tags()->attach([$php->id, $laravel->id]);
    }
}
