<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Contract;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\PublishedArticle;

interface ArticlePublisher
{
    /**
     * Atomically upsert by Article::$id (a stable Blost UUID).
     * Persist all fields, including nulls which clear optional metadata.
     * Return only after durable storage; throw on failure so Blost can retry.
     */
    public function publish(Article $article): PublishedArticle;
}
