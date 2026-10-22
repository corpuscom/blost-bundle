<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteLoader extends Loader
{
    public function __construct(private readonly string $prefix, private readonly bool $public)
    {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        if ($this->public) {
            $prefix = '/'.trim($this->prefix, '/');
            $routes->add('blost_blog_index', new Route($prefix, ['_controller' => [BlogController::class, 'index']], methods: ['GET']));
            $routes->add('blost_blog_show', new Route(rtrim($prefix, '/').'/{id}', ['_controller' => [BlogController::class, 'show']],
                ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['GET']));
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'blost_blog';
    }
}
