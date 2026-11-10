<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Inscripcion::portada');
$routes->get('e/(:segment)', 'Inscripcion::index/$1');
$routes->post('e/(:segment)/cotizar', 'Inscripcion::cotizar/$1');
$routes->post('e/(:segment)', 'Inscripcion::enviar/$1');
$routes->get('e/(:segment)/recibo/(:num)', 'Inscripcion::recibo/$1/$2');
$routes->addRedirect('tour-365', 'e/tour-365');
$routes->addRedirect('tour-365/recibo/(:num)', 'e/tour-365/recibo/$1');
