<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog\DependencyInjection;

use Corpuscom\Blost\SymfonyBlog\ArticlePublisher;
use Corpuscom\Blost\SymfonyBlog\BlogController;
use Corpuscom\Blost\SymfonyBlog\Doctrine\UtcDateTimeType;
use Corpuscom\Blost\SymfonyBlog\RouteLoader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class BlostBlogExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig('blost_blog'));
        $container->prependExtensionConfig('twig', ['globals' => ['blost_blog_title' => $config['title']]]);
        $container->prependExtensionConfig('blost', ['publisher' => ArticlePublisher::class]);
        $container->prependExtensionConfig('doctrine', ['dbal' => ['types' => [UtcDateTimeType::NAME => UtcDateTimeType::class]], 'orm' => ['mappings' => ['BlostBlog' => [
            'is_bundle' => false, 'type' => 'attribute', 'dir' => dirname(__DIR__) . '/Entity',
            'prefix' => 'Corpuscom\\Blost\\SymfonyBlog\\Entity',
        ]]]]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->register(ArticlePublisher::class)->setArguments([new Reference(EntityManagerInterface::class), new Reference('router')]);
        $container->register(BlogController::class)->setArguments([new Reference(EntityManagerInterface::class), new Reference('twig')])
            ->setPublic(true)->addTag('controller.service_arguments');
        $container->register(RouteLoader::class)->setArguments([$config['prefix'], $config['public']])->addTag('routing.loader');
    }
}
