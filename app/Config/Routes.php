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
$routes->get('admin/entrar', 'Admin::entrar');
$routes->post('admin/entrar', 'Admin::entrar');
$routes->get('admin/salir', 'Admin::salir');
$routes->group('admin', ['filter' => 'admin'], static function ($routes) {
    $routes->get('/', 'Admin::eventos');
    $routes->get('eventos/nuevo', 'Admin::nuevo');
    $routes->post('eventos', 'Admin::crear');
    $routes->get('eventos/(:segment)/inscritos/(:num)', 'Admin::detalleInscrito/$1/$2');
    $routes->get('eventos/(:segment)/inscritos', 'Admin::inscritos/$1');
    $routes->get('eventos/(:segment)/exportar', 'Admin::exportar/$1');
    $routes->get('eventos/(:segment)/comprobantes/(:num)', 'Admin::comprobante/$1/$2');
    $routes->get('eventos/(:segment)', 'Admin::editar/$1');
    $routes->post('eventos/(:segment)', 'Admin::guardar/$1');
});
