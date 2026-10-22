<?php

declare(strict_types=1);

use Corpuscom\Blost\Http\BlostApi;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('blost_status', '/blost/v1/status')
        ->controller([BlostApi::class, 'status'])->methods(['GET']);
    $routes->add('blost_publish', '/blost/v1/articles/{id}')
        ->controller([BlostApi::class, 'publish'])->methods(['PUT']);
};
