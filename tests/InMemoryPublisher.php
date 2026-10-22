<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Tests;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher;
use Corpuscom\Blost\PublishedArticle;

final class InMemoryPublisher implements ArticlePublisher
{
    /** @var array<string, Article> */
    public array $articles = [];

    public function publish(Article $article): PublishedArticle
    {
        $this->articles[$article->id] = $article;

        return new PublishedArticle('https://example.com/articles/'.$article->id);
    }
}
