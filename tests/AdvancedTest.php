<?php

namespace Marcelgwerder\ApiHandler\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Marcelgwerder\ApiHandler\ApiHandlerException;
use Marcelgwerder\ApiHandler\CountMetaProvider;
use Marcelgwerder\ApiHandler\Facades\ApiHandler;
use Marcelgwerder\ApiHandler\Parser;
use Marcelgwerder\ApiHandler\Tests\Fixtures\Post;
use Marcelgwerder\ApiHandler\Tests\Fixtures\PostWithAuthorName;
use Marcelgwerder\ApiHandler\Tests\Fixtures\User;

class AdvancedTest extends TestCase
{
    public function testAcceptsARelationAsBuilder()
    {
        $titles = ApiHandler::parseMultiple(User::query()->find(1)->posts(), [], ['_sort' => '-id'])
            ->getResult()
            ->pluck('title')
            ->all();

        $this->assertSame(['Charlie', 'Alpha'], $titles);
    }

    public function testAcceptsAModelInstanceAsBuilder()
    {
        $this->assertSame('Bravo', ApiHandler::parseSingle(new Post(), 2, [])->getResult()->title);
    }

    public function testAcceptsAQueryBuilder()
    {
        $titles = ApiHandler::parseMultiple(DB::table('posts'), [], ['views-min' => '30', '_sort' => 'id'])
            ->getResult()
            ->pluck('title')
            ->all();
        $this->assertSame(['Charlie', 'Delta'], $titles);

        $single = ApiHandler::parseSingle(DB::table('posts'), 4, [])->getResult();
        $this->assertSame('Delta', $single->title);
    }

    public function testRejectsAnInvalidBuilder()
    {
        $this->expectException(InvalidArgumentException::class);

        new Parser('not a builder', []);
    }

    public function testNestedRelationsWithFieldsAndSorts()
    {
        // Fields are supported on the first relation level only (a field such
        // as comments.post.title is applied to "comments" and breaks the SQL)
        $post = ApiHandler::parseSingle(Post::query(), 1, [
            '_fields' => 'title,comments.title',
            '_with' => 'comments.post',
            '_sort' => '-comments.title',
        ])->getResult();

        $this->assertSame(['Nice', 'Great'], $post->comments->pluck('title')->all());
        $this->assertSame(['title', 'post_id'], array_keys($post->comments->first()->getAttributes()));
        $this->assertSame('Alpha', $post->comments->first()->post->title);
    }

    public function testBelongsToManyHasOneAndHasManyThroughRelations()
    {
        $post = ApiHandler::parseSingle(Post::query(), 1, ['_fields' => 'id,title,tags.name', '_with' => 'tags'])->getResult();
        $this->assertSame(['php', 'laravel'], $post->tags->pluck('name')->sort()->reverse()->values()->all());

        $user = ApiHandler::parseSingle(User::query(), 1, ['_fields' => 'id,first_name', '_with' => 'latestPost,comments'])->getResult();
        $this->assertSame('Charlie', $user->latestPost->title);
        $this->assertSame(['Nice', 'Great'], $user->comments->pluck('title')->all());
    }

    public function testUnsupportedRelationTypeThrowsInsteadOfEndingTheRequest()
    {
        try {
            ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'images']);
            $this->fail('ApiHandlerException was not thrown');
        } catch (ApiHandlerException $e) {
            $this->assertSame(400, $e->getHttpCode());
            $this->assertStringContainsString('images', $e->getMessage());
        }
    }

    public function testDefaultFulltextSearchMatchesAnyKeyword()
    {
        $titles = ApiHandler::parseMultiple(Post::query(), ['title', 'description'], ['_q' => 'Charlie fourth', '_sort' => 'id'])
            ->getResult()
            ->pluck('title')
            ->all();

        $this->assertSame(['Charlie', 'Delta'], $titles);
    }

    public function testEmptyFulltextSearchReturnsNothing()
    {
        $this->assertCount(0, ApiHandler::parseMultiple(Post::query(), ['title'], ['_q' => ''])->getResult());
    }

    public function testNativeFulltextSearchBuildsMatchAgainst()
    {
        // MySQL only: check the generated SQL without running it
        Config::set('apihandler.fulltext', 'native');

        $sql = ApiHandler::parseMultiple(Post::query(), ['title', 'description'], ['_q' => 'first'])->getBuilder()->toSql();

        $this->assertStringContainsString('MATCH(posts.title,posts.description) AGAINST(', $sql);
        $this->assertStringContainsString('as `_score`', $sql);
    }

    public function testMultipleLikeValuesAreCombinedWithOr()
    {
        $titles = ApiHandler::parseMultiple(Post::query(), [], ['title-lk' => 'Al*|*lta', '_sort' => 'id'])
            ->getResult()
            ->pluck('title')
            ->all();

        $this->assertSame(['Alpha', 'Delta'], $titles);
    }

    public function testAliasedColumnsAreNotQualified()
    {
        $titles = ApiHandler::parseMultiple(Post::query()->select('posts.*', 'views as popularity'), [], ['_sort' => '-popularity'])
            ->getResult()
            ->pluck('title')
            ->all();

        $this->assertSame(['Delta', 'Charlie', 'Bravo', 'Alpha'], $titles);
    }

    public function testCleanupRemovesRelationsNotRequested()
    {
        Config::set('apihandler.cleanup_relations', true);

        $result = ApiHandler::parseMultiple(Post::query(), [], ['_with' => 'comments', '_sort' => 'id', '_limit' => '1']);
        // Loaded after parsing (e.g. by an accessor): must not be in the response
        $result->getBuilder()->getModel();
        $rows = $result->getResponse()->getData(true);

        $this->assertArrayHasKey('comments', $rows[0]);
        $this->assertArrayNotHasKey('user', $rows[0]);

        $single = ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'comments.post'])->cleanup(true)->getResult();
        $this->assertSame('Alpha', $single['comments'][0]['post']['title']);
    }

    public function testCleanupDropsRelationsLoadedOutsideTheWithParam()
    {
        $result = ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'comments']);
        $post = $result->getBuilder()->first();
        $post->load('user');

        $cleaned = $result->cleanupRelations($post);

        $this->assertArrayHasKey('comments', $cleaned);
        $this->assertArrayHasKey('user', $cleaned);
        $this->assertSame('plain value', $result->cleanupRelations('plain value'));
        $this->assertSame([], $result->cleanupRelationsOnModels('not a collection'));
    }

    public function testCountWithGroupBy()
    {
        $query = Post::query()->select('user_id')->groupBy('user_id');

        $this->assertSame(2, (new CountMetaProvider('Meta-Total-Count', $query))->get());
        $this->assertSame(4, (new CountMetaProvider('Meta-Total-Count', DB::table('posts')))->get());
    }

    public function testResultAccessors()
    {
        $result = ApiHandler::parseMultiple(Post::query(), [], ['_config' => 'mode-count,meta-total-count']);

        $this->assertSame('count', $result->getMode());
        $this->assertCount(1, $result->getMetaProviders());
        $this->assertSame('Meta-Total-Count', $result->getMetaProviders()[0]->getTitle());
        $this->assertStringContainsString('"Meta-Total-Count":4', (string) $result);

        $parser = new Parser(Post::query(), []);
        $parser->setConfigHandler(['custom' => true]);
        $this->assertSame(['custom' => true], (new \ReflectionProperty(Parser::class, 'config'))->getValue($parser));
    }

    public function testMultipleResultOrFailReturnsTheCollection()
    {
        $this->assertCount(4, ApiHandler::parseMultiple(Post::query(), [], [])->getResultOrFail());
    }

    public function testParseMultipleDefaultsToTheRequestParams()
    {
        $this->app['request']->query->replace(['title' => 'Bravo']);

        $this->assertSame(['Bravo'], ApiHandler::parseMultiple(Post::query())->getResult()->pluck('title')->all());
    }

    public function testResponseDefaultOptionDisablesTheEnvelope()
    {
        Config::set('apihandler.envelope', true);

        $response = ApiHandler::parseMultiple(Post::query(), [], ['_config' => 'response-default', '_sort' => 'id'])->getResponse();

        $this->assertSame('Alpha', $response->getData(true)[0]['title']);
    }

    public function testFieldsOnARelationAddTheKeyOfTheNextLevel()
    {
        $user = ApiHandler::parseSingle(User::query(), 1, [
            '_fields' => 'first_name,posts.title',
            '_with' => 'posts.tags',
        ])->getResult();

        $alpha = $user->posts->firstWhere('title', 'Alpha');
        $this->assertArrayHasKey('id', $alpha->getAttributes());
        $this->assertSame(['php', 'laravel'], $alpha->tags->pluck('name')->sort()->reverse()->values()->all());
    }

    public function testPolymorphicOneRelationIsAlsoRejected()
    {
        $this->expectException(ApiHandlerException::class);

        ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'cover']);
    }

    public function testUnknownRelationIsRejected()
    {
        try {
            ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'doesNotExist']);
            $this->fail('ApiHandlerException was not thrown');
        } catch (ApiHandlerException $e) {
            $this->assertStringContainsString('doesNotExist', $e->getMessage());
        }
    }

    public function testSingleResultOrFailWithCleanup()
    {
        $post = ApiHandler::parseSingle(Post::query(), 1, ['_with' => 'comments'])->cleanup(true)->getResultOrFail();

        $this->assertIsArray($post);
        $this->assertCount(2, $post['comments']);
    }

    public function testCleanupWithAnEmptyRelationCollection()
    {
        $post = ApiHandler::parseSingle(Post::query(), 2, ['_with' => 'comments'])->cleanup(true)->getResult();

        $this->assertSame([], $post['comments']);
    }

    public function testCleanupRemovesRelationsLoadedByAccessors()
    {
        $post = ApiHandler::parseSingle(PostWithAuthorName::query(), 1, ['_with' => 'comments'])->cleanup(true)->getResult();

        $this->assertSame('Ana', $post['author_name']);
        $this->assertArrayHasKey('comments', $post);
        $this->assertArrayNotHasKey('user', $post);

        $kept = ApiHandler::parseSingle(PostWithAuthorName::query(), 1, ['_with' => 'comments'])->cleanup(false)->getResult()->toArray();
        $this->assertArrayHasKey('user', $kept);
    }
}
