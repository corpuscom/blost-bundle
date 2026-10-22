<?php

declare(strict_types=1);

namespace Corpuscom\Blost;

final readonly class PublishedArticle
{
    public function __construct(public string $url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new \InvalidArgumentException('The published article must have an absolute HTTP(S) URL.');
        }
    }
}
