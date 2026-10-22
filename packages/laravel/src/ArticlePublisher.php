<?php

declare(strict_types=1);

namespace Corpuscom\Blost\LaravelBlog;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher as PublisherContract;
use Corpuscom\Blost\PublishedArticle;
use Illuminate\Contracts\Routing\UrlGenerator;

final class ArticlePublisher implements PublisherContract
{
    public function __construct(private readonly UrlGenerator $urls)
    {
    }

    public function publish(Article $article): PublishedArticle
    {
        // Generate before writing: a broken route configuration must not acknowledge publication.
        $url = $this->urls->route('blost.blog.show', ['id' => $article->id]);
        $connection = (new Post())->getConnection();
        if ($connection->transactionLevel() !== 0) {
            throw new \LogicException('Blost publication must own its transaction to acknowledge durable persistence.');
        }
        $connection->transaction(function () use ($article): void {
            // firstOrCreate uses a savepoint and the primary key to recover a concurrent insert.
            $post = Post::query()->firstOrCreate(['id' => $article->id], [
                'title' => $article->title,
                'content' => $article->content,
                'published_at' => $this->utc($article->publishedAt),
            ]);
            $post->fill([
                'title' => $article->title,
                'content' => $article->content,
                'excerpt' => $article->excerpt,
                'meta_title' => $article->metaTitle,
                'meta_description' => $article->metaDescription,
                'image_url' => $article->imageUrl,
                'language' => $article->language,
                'published_at' => $article->publishedAt === null ? $post->published_at : $this->utc($article->publishedAt),
            ]);
            $post->saveOrFail();
        });

        return new PublishedArticle($url);
    }

    private function utc(?\DateTimeImmutable $date): \DateTimeImmutable
    {
        return ($date ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'));
    }
}
