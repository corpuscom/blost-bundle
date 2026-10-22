<?php

declare(strict_types=1);

namespace App\Blost;

use App\Entity\BlogPost;
use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher as PublisherContract;
use Corpuscom\Blost\PublishedArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adapt BlogPost/setters to your CMS. Its blostId column needs a unique index.
 * BlogPost must expose getPublishedAt() and your public blog_show route.
 * A unique-conflict on concurrent creation intentionally propagates as 5xx;
 * a Blost retry then finds the existing row and updates it.
 */
final class ArticlePublisher implements PublisherContract
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function publish(Article $article): PublishedArticle
    {
        $post = $this->em->wrapInTransaction(function () use ($article): BlogPost {
            $post = $this->em->getRepository(BlogPost::class)->findOneBy(['blostId' => $article->id]) ?? new BlogPost();
            $post->setBlostId($article->id);
            $post->setTitle($article->title);
            $post->setContent($article->content);
            $post->setExcerpt($article->excerpt);
            $post->setMetaTitle($article->metaTitle);
            $post->setMetaDescription($article->metaDescription);
            $post->setImageUrl($article->imageUrl);
            $post->setLanguage($article->language);
            $post->setPublishedAt($article->publishedAt ?? $post->getPublishedAt() ?? new \DateTimeImmutable());
            $this->em->persist($post);

            return $post;
        });

        return new PublishedArticle($this->urls->generate('blog_show', ['id' => $post->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
