<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Tests;

use Corpuscom\Blost\Symfony\BlostBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

final class SymfonyIntegrationTest extends TestCase
{
    public function testBundleBootsAndServesAuthenticatedRoutes(): void
    {
        $kernel = new class('test', true) extends Kernel {
            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new BlostBundle()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container): void {
                    $container->loadFromExtension('framework', ['secret' => 'test', 'http_method_override' => false,
                        'router' => ['resource' => '@BlostBundle/config/routes.php', 'utf8' => true], 'test' => true]);
                    $container->register(InMemoryPublisher::class)->setPublic(true);
                    $container->loadFromExtension('blost', ['token' => 'secret', 'name' => 'Symfony test', 'publisher' => InMemoryPublisher::class]);
                });
            }

            public function getCacheDir(): string
            {
                return sys_get_temp_dir().'/blost-symfony-tests-'.getmypid();
            }

            public function getLogDir(): string
            {
                return $this->getCacheDir();
            }
        };
        try {
            $response = $kernel->handle(Request::create('/blost/v1/status'));
            self::assertSame(401, $response->getStatusCode());
            $response = $kernel->handle(Request::create('/blost/v1/status', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer secret']));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('Symfony test', json_decode($response->getContent(), true)['name']);
            foreach (['First', 'Updated'] as $title) {
                $response = $kernel->handle(Request::create('/blost/v1/articles/'.BlostApiTest::ID, 'PUT', [], [], [],
                    ['HTTP_AUTHORIZATION' => 'Bearer secret', 'CONTENT_TYPE' => 'application/json'], json_encode(['title' => $title, 'content' => '<p>HTML</p>'])));
                self::assertSame(200, $response->getStatusCode(), $response->getContent());
            }
            $publisher = $kernel->getContainer()->get(InMemoryPublisher::class);
            self::assertCount(1, $publisher->articles);
            self::assertSame('Updated', $publisher->articles[BlostApiTest::ID]->title);
        } finally {
            $kernel->shutdown();
        }
    }
}
