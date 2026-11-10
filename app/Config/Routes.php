<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Inscripcion::index');
$routes->get('tour-365', 'Inscripcion::index');
$routes->post('tour-365/cotizar', 'Inscripcion::cotizar');
$routes->post('tour-365', 'Inscripcion::enviar');
$routes->get('tour-365/recibo/(:num)', 'Inscripcion::recibo/$1');
$routes->get('tour-365/recibo', 'Inscripcion::recibo');
