<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog\Entity;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\SymfonyBlog\Doctrine\UtcDateTimeType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'blost_posts')]
#[ORM\Index(name: 'blost_posts_published_at', columns: ['published_at'])]
class Post
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    public string $id;

    #[ORM\Column(type: Types::TEXT)]
    public string $title;

    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $excerpt = null;

    #[ORM\Column(name: 'meta_title', type: Types::TEXT, nullable: true)]
    public ?string $metaTitle = null;

    #[ORM\Column(name: 'meta_description', type: Types::TEXT, nullable: true)]
    public ?string $metaDescription = null;

    #[ORM\Column(name: 'image_url', type: Types::TEXT, nullable: true)]
    public ?string $imageUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $language = null;

    #[ORM\Column(name: 'published_at', type: UtcDateTimeType::NAME)]
    public \DateTimeImmutable $publishedAt;

    public function apply(Article $article): void
    {
        $this->id = $article->id;
        $this->title = $article->title;
        $this->content = $article->content;
        $this->excerpt = $article->excerpt;
        $this->metaTitle = $article->metaTitle;
        $this->metaDescription = $article->metaDescription;
        $this->imageUrl = $article->imageUrl;
        $this->language = $article->language;
        $this->publishedAt = ($article->publishedAt ?? $this->publishedAt ?? new \DateTimeImmutable())
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
