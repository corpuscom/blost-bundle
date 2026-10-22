<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/examples',
        __DIR__ . '/packages',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->notName('*.blade.php')
    ->notPath('reference.php')
    ->append([__FILE__]);

return (new Config())
    ->setRules(['@PER-CS3x0' => true])
    ->setFinder($finder);
