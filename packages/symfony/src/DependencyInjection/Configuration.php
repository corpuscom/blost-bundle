<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('blost_blog');
        $tree->getRootNode()->children()
            ->scalarNode('prefix')->defaultValue('/blog')->cannotBeEmpty()->end()
            ->scalarNode('title')->defaultValue('Blog')->end()
            ->booleanNode('public')->defaultTrue()->end()
            ->end();

        return $tree;
    }
}
