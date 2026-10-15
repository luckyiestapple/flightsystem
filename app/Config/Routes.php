<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('api/place', 'Api\Places::index');
$routes->get('api/places/(:segment)', 'Api\Places::show/$1');
$routes->get('api/pilots', 'Api\Pilots::index');
$routes->get('api/pilots/(:segment)', 'Api\Pilots::show/$1');

$routes->group('api/', static function (RouteCollection $routes): void {
    $routes->get('search/airlines', 'Api\Airlines::search');
    $routes->get('search/airports', 'Api\Airports::search');
    $routes->get('search/routes', 'Api\Routes::search');
    $routes->get('search/flights', 'Api\Flights::search');
    $routes->get('airlines', 'Api\Airlines::index');
    $routes->get('airlines/(:segment)', 'Api\Airlines::show/$1');
    $routes->put('airlines/(:segment)', 'Api\Airlines::update/$1');
    $routes->patch('airlines/(:segment)', 'Api\Airlines::patch/$1');
    $routes->delete('airlines/(:segment)', 'Api\Airlines::delete/$1');
    $routes->get('airports', 'Api\Airports::index');
    $routes->get('airports/(:segment)', 'Api\Airports::show/$1');
    $routes->put('airports/(:segment)', 'Api\Airports::update/$1');
    $routes->patch('airports/(:segment)', 'Api\Airports::patch/$1');
    $routes->delete('airports/(:segment)', 'Api\Airports::delete/$1');
    $routes->get('routes', 'Api\Routes::index');
    $routes->get('routes/(:segment)', 'Api\Routes::show/$1');
    $routes->put('routes/(:segment)', 'Api\Routes::update/$1');
    $routes->patch('routes/(:segment)', 'Api\Routes::patch/$1');
    $routes->delete('routes/(:segment)', 'Api\Routes::delete/$1');
    $routes->get('flights', 'Api\Flights::index');
    $routes->get('flights/(:segment)', 'Api\Flights::show/$1');
    $routes->put('flights/(:segment)', 'Api\Flights::update/$1');
    $routes->patch('flights/(:segment)', 'Api\Flights::patch/$1');
    $routes->delete('flights/(:segment)', 'Api\Flights::delete/$1');
    $routes->post('airlines', 'Api\Airlines::create');
    $routes->post('airports', 'Api\Airports::create');
    $routes->post('routes', 'Api\Routes::create');
    $routes->post('flights', 'Api\Flights::create');
});
