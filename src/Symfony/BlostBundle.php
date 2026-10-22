<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BlostBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__, 2);
    }
}
