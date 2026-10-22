<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Tests;

use Corpuscom\Blost\Laravel\BlostServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

final class LaravelIntegrationTest extends TestCase
{
    public function testProviderRegistersRoutesAndResolvesHostPublisher(): void
    {
        if (!class_exists(Application::class)) {
            self::markTestSkipped('Laravel is not installed in the Symfony-only dependency matrix.');
        }
        $app = new Application(__DIR__);
        $app->instance('config', new Repository(['blost' => ['token' => 'secret', 'name' => 'Laravel test', 'publisher' => InMemoryPublisher::class]]));
        Facade::setFacadeApplication($app);
        $app->singleton(InMemoryPublisher::class);
        $app->register(\Illuminate\Filesystem\FilesystemServiceProvider::class);
        $app->register(BlostServiceProvider::class);
        $app->boot();
        try {
            $router = $app['router'];
            $request = Request::create('/blost/v1/status');
            $app->instance('request', $request);
            self::assertSame(401, $router->dispatch($request)->getStatusCode());
            $request = Request::create('/blost/v1/status', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer secret']);
            $app->instance('request', $request);
            $response = $router->dispatch($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('Laravel test', json_decode($response->getContent(), true)['name']);
            foreach (['First', 'Updated'] as $title) {
                $request = Request::create('/blost/v1/articles/'.BlostApiTest::ID, 'PUT', [], [], [],
                    ['HTTP_AUTHORIZATION' => 'Bearer secret', 'CONTENT_TYPE' => 'application/json'], json_encode(['title' => $title, 'content' => '<p>HTML</p>']));
                $app->instance('request', $request);
                $response = $router->dispatch($request);
                self::assertSame(200, $response->getStatusCode(), $response->getContent());
            }
            $publisher = $app->make(InMemoryPublisher::class);
            self::assertCount(1, $publisher->articles);
            self::assertSame('Updated', $publisher->articles[BlostApiTest::ID]->title);
            $router->getRoutes()->refreshNameLookups();
            self::assertEmpty($router->getRoutes()->getByName('blost.publish')->middleware());
            // Controller routes remain serializable for artisan route:cache.
            self::assertNotEmpty($router->getRoutes()->compile());
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
            $app->flush();
        }
    }
}
