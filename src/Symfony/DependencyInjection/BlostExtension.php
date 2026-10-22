<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Symfony\DependencyInjection;

use Corpuscom\Blost\Contract\ArticlePublisher;
use Corpuscom\Blost\Http\BlostApi;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class BlostExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setAlias(ArticlePublisher::class, $config['publisher']);
        $container->register(BlostApi::class, BlostApi::class)
            ->setArguments([new Reference(ArticlePublisher::class), $config['token'], $config['name']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }
}
