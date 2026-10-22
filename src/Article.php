<?php

declare(strict_types=1);

namespace Corpuscom\Blost;

final readonly class Article
{
    public function __construct(
        public string $id,
        public string $title,
        public string $content,
        public ?string $excerpt,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $imageUrl,
        public ?string $language,
        public ?\DateTimeImmutable $publishedAt,
    ) {}
}
