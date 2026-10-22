<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Tests;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher;
use Corpuscom\Blost\Http\BlostApi;
use Corpuscom\Blost\PublishedArticle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BlostApiTest extends TestCase
{
    public const ID = 'b1057c0e-7a11-4c0d-9b1e-0b10570c0de1';

    private function request(string $body, string $token = 'secret', string $contentType = 'application/json'): Request
    {
        return Request::create('/blost/v1/articles/' . self::ID, 'PUT', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => $contentType,
        ], $body);
    }

    public function testStatusRequiresAuthenticationIncludingWhenTokenIsUnconfigured(): void
    {
        $publisher = new InMemoryPublisher();
        foreach (['', 'wrong'] as $token) {
            $response = (new BlostApi($publisher, 'secret'))->status($this->request('', $token));
            self::assertSame(401, $response->getStatusCode());
            self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        }
        self::assertSame(401, (new BlostApi($publisher, ''))->status($this->request('', ''))->getStatusCode());
        $response = (new BlostApi($publisher, 'secret', 'Example'))->status($this->request(''));
        self::assertSame(['protocol_version' => '1', 'name' => 'Example'], json_decode($response->getContent(), true));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function testRetryUsesSameIdentityAndPreservesRichContentAndMetadata(): void
    {
        $publisher = new InMemoryPublisher();
        $api = new BlostApi($publisher, 'secret');
        $content = '<h2>Árvíztűrő</h2><p><strong>Editorial HTML</strong></p>';
        $payload = ['title' => 'Title', 'content' => $content, 'excerpt' => '<p>Lead</p>', 'meta_title' => 'SEO',
            'meta_description' => 'Description', 'image_url' => 'https://example.com/image.webp',
            'language' => 'Hungarian', 'published_at' => '2026-10-22T10:00:00.123Z'];
        $first = $api->publish($this->request(json_encode($payload)), strtoupper(self::ID));
        $payload['title'] = 'Updated';
        $second = $api->publish($this->request(json_encode($payload)), self::ID);
        self::assertSame(200, $first->getStatusCode());
        self::assertSame($first->getContent(), $second->getContent());
        self::assertCount(1, $publisher->articles);
        $article = $publisher->articles[self::ID];
        self::assertSame('Updated', $article->title);
        self::assertSame($content, $article->content);
        self::assertSame('SEO', $article->metaTitle);
        self::assertSame('Description', $article->metaDescription);
        self::assertSame('Hungarian', $article->language);
        self::assertSame('2026-10-22T10:00:00+00:00', $article->publishedAt->format(DATE_ATOM));
        $api->publish($this->request('{"title":"Clear metadata","content":"Body"}'), self::ID);
        self::assertNull($publisher->articles[self::ID]->imageUrl);
        self::assertNull($publisher->articles[self::ID]->metaTitle);
    }

    public static function invalidPayloads(): iterable
    {
        yield ['{', 400];
        yield ['[]', 422];
        yield ['null', 422];
        yield ['{"title":"","content":"Body"}', 422];
        yield ['{"title":[],"content":"Body"}', 422];
        yield ['{"title":"Title","content":12}', 422];
        yield ['{"title":"Title","content":"Body","excerpt":false}', 422];
        yield ['{"title":"Title","content":"Body","image_url":"file:///etc/passwd"}', 422];
        yield ['{"title":"Title","content":"Body","image_url":"data:image/png;base64,eA=="}', 422];
        yield ['{"title":"Title","content":"Body","published_at":"tomorrow"}', 422];
        yield ['{"title":"Title","content":"Body","published_at":"2026-02-30T00:00:00Z"}', 422];
        yield [str_repeat('x', BlostApi::MAX_BODY_BYTES + 1), 413];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadNeverReachesPublisher(string $body, int $status): void
    {
        $publisher = new InMemoryPublisher();
        self::assertSame($status, (new BlostApi($publisher, 'secret'))->publish($this->request($body), self::ID)->getStatusCode());
        self::assertSame([], $publisher->articles);
    }

    public function testAuthenticationPrecedesValidationAndRejectsInvalidContentTypeAndId(): void
    {
        $publisher = new InMemoryPublisher();
        $api = new BlostApi($publisher, 'secret');
        self::assertSame(401, $api->publish($this->request('{', 'wrong'), 'bad')->getStatusCode());
        self::assertSame(422, $api->publish($this->request('{}'), 'bad')->getStatusCode());
        self::assertSame(415, $api->publish($this->request('{}', 'secret', 'text/plain'), self::ID)->getStatusCode());
        self::assertSame([], $publisher->articles);
    }

    public function testFailedPersistenceIsNeverAcknowledged(): void
    {
        $publisher = new class implements ArticlePublisher {
            public function publish(Article $article): PublishedArticle
            {
                throw new \RuntimeException('Storage unavailable');
            }
        };
        $this->expectException(\RuntimeException::class);
        (new BlostApi($publisher, 'secret'))->publish($this->request('{"title":"Title","content":"Body"}'), self::ID);
    }
}
