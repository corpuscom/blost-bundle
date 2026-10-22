<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('blost');
        $tree->getRootNode()->children()
            ->scalarNode('token')->defaultValue('%env(BLOST_TOKEN)%')->end()
            ->scalarNode('name')->defaultValue('Blost connector')->end()
            ->scalarNode('publisher')->isRequired()->cannotBeEmpty()->end()
            ->end();

        return $tree;
    }
}
