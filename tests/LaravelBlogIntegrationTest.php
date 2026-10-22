<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Tests;

use Corpuscom\Blost\LaravelBlog\BlostBlogServiceProvider;
use Corpuscom\Blost\LaravelBlog\Post;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

final class LaravelBlogIntegrationTest extends TestCase
{
    private Application $app;
    private string $directory;
    private object $migration;

    protected function setUp(): void
    {
        if (!class_exists(Application::class)) {
            self::markTestSkipped('Laravel is absent in the Symfony-only matrix.');
        }
        $this->directory = sys_get_temp_dir().'/blost-laravel-blog-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
        $this->app = new Application($this->directory);
        $this->app->instance('config', new Repository([
            'app' => ['name' => 'Test app', 'key' => 'test', 'locale' => 'en'],
            'blost' => ['token' => 'secret'],
            'blost-blog' => ['prefix' => 'news', 'title' => 'Test blog'],
            'view' => ['paths' => [], 'compiled' => $this->directory],
            'database' => ['default' => 'test', 'connections' => ['test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        ]));
        Facade::setFacadeApplication($this->app);
        $this->app->register(\Illuminate\Filesystem\FilesystemServiceProvider::class);
        $this->app->register(DatabaseServiceProvider::class);
        $this->app->register(\Illuminate\View\ViewServiceProvider::class);
        $this->app->register(\Illuminate\Pagination\PaginationServiceProvider::class);
        $this->app->register(BlostBlogServiceProvider::class);
        $this->app->boot();
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app->instance('request', Request::create('https://example.com'));
        $this->app['url']->forceRootUrl('https://example.com');
        $this->app['url']->forceScheme('https');
        $this->migration = require __DIR__.'/../packages/laravel/database/migrations/2026_10_23_000000_create_blost_posts_table.php';
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        if (!isset($this->app)) {
            return;
        }
        $this->app['db']->disconnect();
        Model::unsetConnectionResolver();
        Model::unsetEventDispatcher();
        Model::clearBootedModels();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $this->app->flush();
        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->directory);
    }

    public function testPublishesIdempotentlyAndRendersWithEscapedMetadata(): void
    {
        self::assertSame(401, $this->publish([], false)->getStatusCode());
        self::assertSame(422, $this->publish(['title' => 'Missing content'])->getStatusCode());
        self::assertSame(0, Post::query()->count());
        self::assertSame(200, $this->request('/blost/v1/status', ['HTTP_AUTHORIZATION' => 'Bearer secret'])->getStatusCode());
        $payload = ['title' => '<Title>', 'content' => '<h2>Editorial HTML</h2>', 'excerpt' => 'Intro',
            'meta_title' => 'SEO & title', 'meta_description' => 'A "description"', 'image_url' => 'https://example.com/image.jpg',
            'language' => 'Hungarian', 'published_at' => '2020-01-01T12:00:00+02:00'];
        $response = $this->publish($payload);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://example.com/news/'.BlostApiTest::ID, json_decode($response->getContent(), true)['url']);
        $post = Post::query()->findOrFail(BlostApiTest::ID);
        self::assertSame('2020-01-01 10:00:00', $post->published_at->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $post->published_at->timezoneName);
        self::assertSame('Hungarian', $post->language);
        $html = $this->request('/news/'.BlostApiTest::ID)->getContent();
        self::assertStringContainsString('<h2>Editorial HTML</h2>', $html);
        self::assertStringContainsString('&lt;Title&gt;', $html);
        self::assertStringContainsString('SEO &amp; title', $html);
        self::assertStringContainsString('A &quot;description&quot;', $html);
        self::assertStringContainsString('Test blog', $this->request('/news')->getContent());
        self::assertSame(200, $this->publish(['title' => 'Updated', 'content' => '<p>Updated</p>'])->getStatusCode());
        self::assertSame(1, Post::query()->count());
        $post->refresh();
        self::assertSame('Updated', $post->title);
        self::assertNull($post->excerpt);
        self::assertNull($post->meta_title);
        self::assertNull($post->meta_description);
        self::assertNull($post->image_url);
        self::assertNull($post->language);
        self::assertSame('2020-01-01 10:00:00', $post->published_at->format('Y-m-d H:i:s'));
        self::assertNotEmpty(ServiceProvider::pathsToPublish(BlostBlogServiceProvider::class, 'blost-blog-migrations'));
        // Exercise the compiled route collection used by route:cache.
        $this->app['router']->setCompiledRoutes($this->app['router']->getRoutes()->compile());
        self::assertSame(200, $this->request('/news/'.BlostApiTest::ID)->getStatusCode());
        self::assertSame(200, $this->publish(['title' => 'Cached routes', 'content' => 'Body'])->getStatusCode());
        $this->migration->down();
        self::assertFalse($this->app['db']->connection()->getSchemaBuilder()->hasTable('blost_posts'));
    }

    public function testListingPaginatesWithoutRepeatingArticles(): void
    {
        for ($i = 0; $i < 21; ++$i) {
            Post::query()->create(['id' => sprintf('00000000-0000-0000-0000-%012d', $i), 'title' => 'Post '.$i, 'content' => 'Body', 'published_at' => '2020-01-01 00:00:00']);
        }
        $first = $this->request('/news')->getContent();
        self::assertSame(20, substr_count($first, '<article>'));
        self::assertStringContainsString('page=2', $first);
        self::assertStringNotContainsString('Post 20', $first);
        $second = $this->request('/news?page=2')->getContent();
        self::assertSame(1, substr_count($second, '<article>'));
        self::assertStringContainsString('Post 20', $second);
        self::assertStringNotContainsString('>Next<', $second);
    }

    public function testFutureArticlesAreHidden(): void
    {
        $this->publish(['title' => 'Future article', 'content' => 'Later', 'published_at' => '2099-01-01T00:00:00Z']);
        self::assertStringNotContainsString('Future article', $this->request('/news')->getContent());
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->request('/news/'.BlostApiTest::ID);
    }

    public function testNullPublicationDateDefaultsAndSurvivesRetry(): void
    {
        $this->publish(['title' => 'First', 'content' => 'Body']);
        $date = Post::query()->findOrFail(BlostApiTest::ID)->published_at;
        self::assertEqualsWithDelta(time(), $date->getTimestamp(), 5);
        $this->publish(['title' => 'Retry', 'content' => 'Body']);
        self::assertEquals($date, Post::query()->findOrFail(BlostApiTest::ID)->published_at);
    }

    public function testEnclosingTransactionCannotProduceAnAcknowledgement(): void
    {
        $connection = $this->app['db']->connection();
        $connection->beginTransaction();
        try {
            $this->expectException(\LogicException::class);
            $this->publish(['title' => 'Uncommitted', 'content' => 'Body']);
        } finally {
            $connection->rollBack();
            self::assertSame(0, Post::query()->count());
        }
    }

    public function testDatabaseFailurePropagatesAndDuplicateIdentityIsRejected(): void
    {
        $this->publish(['title' => 'First', 'content' => 'Body']);
        try {
            $this->app['db']->table('blost_posts')->insert(['id' => BlostApiTest::ID, 'title' => 'Duplicate', 'content' => 'Body', 'published_at' => '2020-01-01 00:00:00']);
            self::fail('Duplicate identities must be rejected.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(1, Post::query()->count());
        }
        $this->migration->down();
        $this->expectException(QueryException::class);
        $this->publish(['title' => 'Fails', 'content' => 'Body']);
    }

    private function request(string $path, array $server = []): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('https://example.com'.$path, 'GET', [], [], [], $server);
        $this->app->instance('request', $request);

        return $this->app['router']->dispatch($request);
    }

    private function publish(array $payload, bool $authorized = true): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('https://example.com/blost/v1/articles/'.BlostApiTest::ID, 'PUT', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => $authorized ? 'Bearer secret' : ''], json_encode($payload));
        $this->app->instance('request', $request);

        return $this->app['router']->dispatch($request);
    }
}
