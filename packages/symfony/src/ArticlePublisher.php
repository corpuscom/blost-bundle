<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher as PublisherContract;
use Corpuscom\Blost\PublishedArticle;
use Corpuscom\Blost\SymfonyBlog\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ArticlePublisher implements PublisherContract
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {}

    public function publish(Article $article): PublishedArticle
    {
        $url = $this->urls->generate('blost_blog_show', ['id' => $article->id], UrlGeneratorInterface::ABSOLUTE_URL);
        if ($this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Blost publication must own its transaction to acknowledge durable persistence.');
        }
        $this->em->wrapInTransaction(function () use ($article): void {
            $post = $this->em->find(Post::class, $article->id) ?? new Post();
            $post->apply($article);
            $this->em->persist($post);
        });
        // A concurrent insert's primary-key conflict propagates as 5xx. Retrying updates the winner.
        return new PublishedArticle($url);
    }
}
