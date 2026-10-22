<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BlostBlogBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
