<?php

namespace Marcelgwerder\ApiHandler\Tests;

use Marcelgwerder\ApiHandler\CountMetaProvider;
use Marcelgwerder\ApiHandler\Facades\ApiHandler;
use Marcelgwerder\ApiHandler\Tests\Fixtures\Post;
use PHPUnit\Framework\Attributes\DataProvider;

class ParseMultipleTest extends TestCase
{
    public static function filtersProvider(): array
    {
        return [
            'equals' => [['title' => 'Bravo'], ['Bravo']],
            'equals any of' => [['title' => 'Bravo|Delta'], ['Bravo', 'Delta']],
            'not' => [['title-not' => 'Bravo'], ['Alpha', 'Charlie', 'Delta']],
            'not any of' => [['title-not' => 'Bravo|Delta'], ['Alpha', 'Charlie']],
            'like with wildcard' => [['title-lk' => '*rav*'], ['Bravo']],
            'not like' => [['title-not-lk' => '*a*'], []],
            'greater than' => [['views-gt' => '20'], ['Charlie', 'Delta']],
            'smaller than' => [['views-st' => '20'], ['Alpha']],
            'min' => [['views-min' => '20'], ['Bravo', 'Charlie', 'Delta']],
            'max' => [['views-max' => '20'], ['Alpha', 'Bravo']],
            'in' => [['id-in' => '1,3'], ['Alpha', 'Charlie']],
            'not in' => [['id-not-in' => '1,3'], ['Bravo', 'Delta']],
            'null' => [['description' => 'null'], ['Charlie']],
            'not null' => [['description-not' => 'null'], ['Alpha', 'Bravo', 'Delta']],
            'combined' => [['views-min' => '20', 'title-not' => 'Delta'], ['Bravo', 'Charlie']],
        ];
    }

    #[DataProvider('filtersProvider')]
    public function testFilters(array $params, array $expectedTitles)
    {
        $this->assertSame($expectedTitles, $this->titles($params + ['_sort' => 'id']));
    }

    public function testSortAscendingAndDescending()
    {
        $this->assertSame(['Alpha', 'Bravo', 'Charlie', 'Delta'], $this->titles(['_sort' => 'views']));
        $this->assertSame(['Delta', 'Charlie', 'Bravo', 'Alpha'], $this->titles(['_sort' => '-views']));
    }

    public function testSortByMultipleColumns()
    {
        $this->assertSame(['Bravo', 'Delta', 'Alpha', 'Charlie'], $this->titles(['_sort' => '-user_id,title']));
    }

    public function testLimitAndOffset()
    {
        $this->assertSame(['Bravo', 'Charlie'], $this->titles(['_sort' => 'id', '_limit' => '2', '_offset' => '1']));
    }

    public function testFieldsRestrictTheSelectedColumns()
    {
        $post = ApiHandler::parseMultiple(Post::query(), [], ['_fields' => 'id,title', '_sort' => 'id'])
            ->getResult()
            ->first();

        $this->assertSame(['id', 'title'], array_keys($post->getAttributes()));
    }

    public function testWithLoadsAnnotatedRelations()
    {
        $post = ApiHandler::parseMultiple(Post::query(), [], ['_with' => 'user,comments', '_sort' => 'id'])
            ->getResult()
            ->first();

        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame('Ana', $post->user->first_name);
        $this->assertSame(['Nice', 'Great'], $post->comments->pluck('title')->all());
    }

    public function testFulltextSearchOnGivenColumns()
    {
        $titles = ApiHandler::parseMultiple(Post::query(), ['title', 'description'], ['_q' => 'second', '_sort' => 'id'])
            ->getResult()
            ->pluck('title')
            ->all();

        $this->assertSame(['Bravo'], $titles);
    }

    public function testMetaCountsInHeaders()
    {
        $response = ApiHandler::parseMultiple(Post::query(), [], [
            'views-min' => '20',
            '_limit' => '1',
            '_offset' => '1',
            '_sort' => 'id',
            '_config' => 'meta-total-count,meta-filter-count',
        ])->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('4', $response->headers->get('Meta-Total-Count'));
        $this->assertSame('3', $response->headers->get('Meta-Filter-Count'));
        $this->assertSame(['Charlie'], array_column($response->getData(true), 'title'));
    }

    public function testMetaCountIgnoresOrderBindings()
    {
        // Orders are removed from the count query, so their bindings must go
        // too: MySQL rejects extra bindings (SQLite silently ignores them)
        $query = Post::query()
            ->where('views', '>=', 20)
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', ['Delta']);

        $countQuery = (new \ReflectionProperty(CountMetaProvider::class, 'builder'))
            ->getValue(new CountMetaProvider('Meta-Filter-Count', $query));

        $this->assertSame(substr_count($countQuery->toSql(), '?'), count($countQuery->getBindings()));
        $this->assertSame([20], $countQuery->getBindings());

        $response = ApiHandler::parseMultiple($query, [], ['_config' => 'meta-filter-count'])->getResponse();
        $this->assertSame('3', $response->headers->get('Meta-Filter-Count'));
        $this->assertSame('Delta', $response->getData(true)[0]['title']);
    }

    public function testEnvelopeResponse()
    {
        $response = ApiHandler::parseMultiple(Post::query(), [], [
            'views-max' => '20',
            '_sort' => 'id',
            '_config' => 'response-envelope,meta-filter-count',
        ])->getResponse();

        $body = $response->getData(true);
        $this->assertSame(['filter_count' => 2], $body['meta']);
        $this->assertSame(['Alpha', 'Bravo'], array_column($body['data'], 'title'));
    }

    public function testCountMode()
    {
        $response = ApiHandler::parseMultiple(Post::query(), [], [
            'views-min' => '30',
            '_config' => 'mode-count,meta-filter-count',
        ])->getResponse();

        $this->assertSame(['Meta-Filter-Count' => 2], $response->getData(true));
    }

    private function titles(array $params): array
    {
        return ApiHandler::parseMultiple(Post::query(), [], $params)
            ->getResult()
            ->pluck('title')
            ->all();
    }
}
