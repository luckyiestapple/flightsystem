<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api/v1', static function (RouteCollection $routes): void {
    $routes->get('airlines', 'Api\Airlines::index');
    $routes->get('airlines/(:segment)', 'Api\Airlines::show/$1');
    $routes->put('airlines/(:segment)', 'Api\Airlines::update/$1');
    $routes->get('airports', 'Api\Airports::index');
    $routes->get('airports/(:segment)', 'Api\Airports::show/$1');
    $routes->put('airports/(:segment)', 'Api\Airports::update/$1');
    $routes->get('routes', 'Api\Routes::index');
    $routes->get('routes/(:segment)', 'Api\Routes::show/$1');
    $routes->put('routes/(:segment)', 'Api\Routes::update/$1');
    $routes->get('flights', 'Api\Flights::index');
    $routes->get('flights/(:segment)', 'Api\Flights::show/$1');
    $routes->put('flights/(:segment)', 'Api\Flights::update/$1');
    $routes->post('airlines', 'Api\Airlines::create');
    $routes->post('airports', 'Api\Airports::create');
    $routes->post('routes', 'Api\Routes::create');
    $routes->post('flights', 'Api\Flights::create');
});
