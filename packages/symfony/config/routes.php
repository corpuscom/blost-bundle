<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@BlostBundle/config/routes.php', 'php');
    $routes->import('.', 'blost_blog');
};
