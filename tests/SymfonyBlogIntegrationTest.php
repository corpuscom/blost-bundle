<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Tests;

use Corpuscom\Blost\Symfony\BlostBundle;
use Corpuscom\Blost\SymfonyBlog\BlostBlogBundle;
use Corpuscom\Blost\SymfonyBlog\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

final class SymfonyBlogIntegrationTest extends TestCase
{
    private SymfonyBlogTestKernel $kernel;
    private string $directory;
    private mixed $previousExceptionHandler;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(static function (): void {});
        restore_exception_handler();
        $this->directory = sys_get_temp_dir() . '/blost-blog-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/migrations', 0777, true);
        $this->kernel = new SymfonyBlogTestKernel($this->directory);
        foreach ([['command' => 'doctrine:migrations:diff'], ['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]] as $arguments) {
            $application = new Application($this->kernel);
            $application->setAutoExit(false);
            $output = new BufferedOutput();
            $input = new ArrayInput($arguments);
            $input->setInteractive(false);
            self::assertSame(0, $application->run($input, $output), $output->fetch());
            $this->kernel->shutdown();
        }
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->directory);
        for ($i = 0; $i < 10; ++$i) {
            $handler = set_exception_handler(static function (): void {});
            restore_exception_handler();
            if ($handler === $this->previousExceptionHandler) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testPublicationPersistsAndRendersWithDefaultsAndCustomPrefix(): void
    {
        self::assertSame(401, $this->publish([], false)->getStatusCode());
        self::assertSame(422, $this->publish(['title' => 'Missing content'])->getStatusCode());
        self::assertSame(0, $this->em()->getRepository(Post::class)->count([]));
        self::assertSame(200, $this->request('/blost/v1/status', ['HTTP_AUTHORIZATION' => 'Bearer secret'])->getStatusCode());
        $payload = ['title' => '<Title>', 'content' => '<h2>Editorial HTML</h2>', 'excerpt' => 'Intro',
            'meta_title' => 'SEO & title', 'meta_description' => 'A "description"', 'image_url' => 'https://example.com/image.jpg',
            'language' => 'Hungarian', 'published_at' => '2020-01-01T12:00:00+02:00'];
        $response = $this->publish($payload);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame('https://example.com/news/' . BlostApiTest::ID, json_decode($response->getContent(), true)['url']);
        $this->em()->clear();
        $post = $this->em()->find(Post::class, BlostApiTest::ID);
        self::assertSame('2020-01-01 10:00:00', $post->publishedAt->format('Y-m-d H:i:s'));
        self::assertSame('Hungarian', $post->language);
        $html = $this->request('/news/' . BlostApiTest::ID);
        self::assertSame(200, $html->getStatusCode(), $html->getContent());
        self::assertStringContainsString('<h2>Editorial HTML</h2>', $html->getContent());
        self::assertStringContainsString('&lt;Title&gt;', $html->getContent());
        self::assertStringContainsString('SEO &amp; title', $html->getContent());
        self::assertStringContainsString('A &quot;description&quot;', $html->getContent());
        self::assertStringContainsString('Test blog', $this->request('/news')->getContent());
        self::assertSame(404, $this->request('/blog')->getStatusCode());
        $response = $this->publish(['title' => 'Updated', 'content' => '<p>New</p>']);
        self::assertSame(200, $response->getStatusCode());
        $this->em()->clear();
        $post = $this->em()->find(Post::class, BlostApiTest::ID);
        self::assertSame(1, $this->em()->getRepository(Post::class)->count([]));
        self::assertSame('Updated', $post->title);
        self::assertNull($post->metaTitle);
        self::assertNull($post->metaDescription);
        self::assertNull($post->excerpt);
        self::assertNull($post->imageUrl);
        self::assertNull($post->language);
        self::assertSame('2020-01-01 10:00:00', $post->publishedAt->format('Y-m-d H:i:s'));
        self::assertSame(200, $this->publish(['title' => 'Future', 'content' => 'Later', 'published_at' => '2099-01-01T00:00:00Z'])->getStatusCode());
        self::assertSame(404, $this->request('/news/' . BlostApiTest::ID)->getStatusCode());
        self::assertStringNotContainsString('Future', $this->request('/news')->getContent());
    }

    public function testListingPaginatesWithoutRepeatingArticles(): void
    {
        for ($i = 0; $i < 21; ++$i) {
            $post = new Post();
            $post->apply(new \Corpuscom\Blost\Article(sprintf('00000000-0000-0000-0000-%012d', $i), 'Post ' . $i, 'Body', null, null, null, null, null, new \DateTimeImmutable('2020-01-01T00:00:00Z')));
            $this->em()->persist($post);
        }
        $this->em()->flush();
        $first = $this->request('/news')->getContent();
        self::assertSame(20, substr_count($first, '<article>'));
        self::assertStringContainsString('page=2', $first);
        self::assertStringNotContainsString('Post 20', $first);
        $second = $this->request('/news?page=2')->getContent();
        self::assertSame(1, substr_count($second, '<article>'));
        self::assertStringContainsString('Post 20', $second);
        self::assertStringNotContainsString('>Next<', $second);
    }

    public function testEnclosingTransactionCannotProduceAnAcknowledgement(): void
    {
        $connection = $this->em()->getConnection();
        $connection->beginTransaction();
        try {
            self::assertSame(500, $this->publish(['title' => 'Uncommitted', 'content' => 'Body'])->getStatusCode());
            self::assertSame(0, $this->em()->getRepository(Post::class)->count([]));
        } finally {
            $connection->rollBack();
        }
    }

    public function testDatabaseFailureIsNotAcknowledgedAndPrimaryKeyIsUnique(): void
    {
        $this->publish(['title' => 'First', 'content' => 'Body']);
        try {
            $this->em()->getConnection()->insert('blost_posts', ['id' => BlostApiTest::ID, 'title' => 'Duplicate', 'content' => 'Body', 'published_at' => '2020-01-01 00:00:00']);
            self::fail('Duplicate identities must be rejected.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(1, $this->em()->getRepository(Post::class)->count([]));
        }
        $this->em()->getConnection()->executeStatement('DROP TABLE blost_posts');
        self::assertSame(500, $this->publish(['title' => 'Fail', 'content' => 'Body'])->getStatusCode());
    }

    public function testNullPublicationDateDefaultsAndSurvivesRetry(): void
    {
        self::assertSame(200, $this->publish(['title' => 'First', 'content' => 'Body'])->getStatusCode());
        $this->em()->clear();
        $date = $this->em()->find(Post::class, BlostApiTest::ID)->publishedAt;
        self::assertEqualsWithDelta(time(), $date->getTimestamp(), 5);
        self::assertSame(200, $this->publish(['title' => 'Retry', 'content' => 'Body'])->getStatusCode());
        $this->em()->clear();
        self::assertEquals($date, $this->em()->find(Post::class, BlostApiTest::ID)->publishedAt);
    }

    private function em(): EntityManagerInterface
    {
        return $this->kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
    }

    private function request(string $path, array $server = []): \Symfony\Component\HttpFoundation\Response
    {
        return $this->kernel->handle(Request::create('https://example.com' . $path, 'GET', [], [], [], $server));
    }

    private function publish(array $payload, bool $authorized = true): \Symfony\Component\HttpFoundation\Response
    {
        return $this->kernel->handle(Request::create(
            'https://example.com/blost/v1/articles/' . BlostApiTest::ID,
            'PUT',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => $authorized ? 'Bearer secret' : ''],
            json_encode($payload),
        ));
    }
}

final class SymfonyBlogTestKernel extends Kernel
{
    public function __construct(private readonly string $directory)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new DoctrineBundle(), new DoctrineMigrationsBundle(), new BlostBundle(), new BlostBlogBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', ['secret' => 'test', 'http_method_override' => false,
                'router' => ['resource' => '@BlostBlogBundle/config/routes.php', 'utf8' => true], 'test' => true]);
            $container->loadFromExtension('twig', []);
            $container->loadFromExtension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->directory . '/db.sqlite'], 'orm' => []]);
            $container->loadFromExtension('doctrine_migrations', ['migrations_paths' => ['BlostTestMigrations' . md5($this->directory) => $this->directory . '/migrations']]);
            $container->loadFromExtension('blost', ['token' => 'secret']);
            $container->loadFromExtension('blost_blog', ['prefix' => '/news', 'title' => 'Test blog']);
        });
    }

    protected function getContainerClass(): string
    {
        return parent::getContainerClass() . '_' . md5($this->directory);
    }

    public function getCacheDir(): string
    {
        return $this->directory . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->directory . '/logs';
    }
}
