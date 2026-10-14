<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api/v1', static function (RouteCollection $routes): void {
    $routes->get('airlines', 'Api\Airlines::index');
    $routes->get('airports', 'Api\Airports::index');
    $routes->get('routes', 'Api\Routes::index');
    $routes->get('flights', 'Api\Flights::index');
    $routes->post('airlines', 'Api\Airlines::create');
    $routes->post('airports', 'Api\Airports::create');
    $routes->post('routes', 'Api\Routes::create');
    $routes->post('flights', 'Api\Flights::create');
});
