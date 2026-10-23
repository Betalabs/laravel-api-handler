<?php

namespace Marcelgwerder\ApiHandler\Tests;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Marcelgwerder\ApiHandler\ApiHandlerException;
use Marcelgwerder\ApiHandler\Facades\ApiHandler;
use Marcelgwerder\ApiHandler\Tests\Fixtures\Post;

class ParseSingleTest extends TestCase
{
    public function testFindsByPrimaryKey()
    {
        $post = ApiHandler::parseSingle(Post::query(), 2, [])->getResult();

        $this->assertSame('Bravo', $post->title);
    }

    public function testFindsByColumnValuePairs()
    {
        $post = ApiHandler::parseSingle(Post::query(), ['title' => 'Charlie', 'views' => 30], [])->getResult();

        $this->assertSame(3, $post->id);
    }

    public function testWithFieldsAndRelations()
    {
        $response = ApiHandler::parseSingle(Post::query(), 1, [
            '_fields' => 'id,title,user_id',
            '_with' => 'user',
        ])->getResponse();

        $body = $response->getData(true);
        $this->assertSame(1, $body['id']);
        $this->assertSame('Alpha', $body['title']);
        $this->assertSame('Ana', $body['user']['first_name']);
        $this->assertArrayNotHasKey('description', $body);
    }

    public function testMissingObjectReturnsNull()
    {
        $this->assertNull(ApiHandler::parseSingle(Post::query(), 999, [])->getResult());
    }

    public function testMissingObjectFailsWhenRequested()
    {
        $this->expectException(ModelNotFoundException::class);

        ApiHandler::parseSingle(Post::query(), 999, [])->getResponse(true);
    }

    public function testRelationWithoutAnnotationIsRejected()
    {
        try {
            ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'hiddenComments']);
            $this->fail('ApiHandlerException was not thrown');
        } catch (ApiHandlerException $e) {
            $this->assertSame(400, $e->getHttpCode());
            $this->assertStringContainsString('hiddenComments', $e->getMessage());
        }
    }

    public function testCreatedUpdatedAndDeletedResponses()
    {
        $created = ApiHandler::created(['id' => 5]);
        $this->assertSame(201, $created->getStatusCode());
        $this->assertSame(['id' => 5], $created->getData(true));

        $this->assertSame(200, ApiHandler::updated(['id' => 5])->getStatusCode());
        $this->assertSame(204, ApiHandler::updated()->getStatusCode());

        $this->assertSame(200, ApiHandler::deleted(['id' => 5])->getStatusCode());
        $this->assertSame(204, ApiHandler::deleted()->getStatusCode());
    }

    public function testQueryParamsDefaultToTheRequest()
    {
        $this->app['request']->query->replace(['_fields' => 'id,title']);

        $post = ApiHandler::parseSingle(Post::query(), 4)->getResult();

        $this->assertSame(['id', 'title'], array_keys($post->getAttributes()));
        $this->assertSame('Delta', $post->title);
    }
}
