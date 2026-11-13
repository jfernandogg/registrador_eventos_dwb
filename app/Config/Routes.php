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
$routes->get('pagar/(:segment)/(:num)', 'Inscripcion::pagar/$1/$2');
$routes->post('webhook/bold', 'Webhook::bold');
$routes->get('admin/entrar', 'Admin::entrar');
$routes->post('admin/entrar', 'Admin::entrar');
$routes->get('admin/salir', 'Admin::salir');
$routes->get('admin/aceptar-invitacion', 'Admin::aceptarInvitacion');
$routes->post('admin/aceptar-invitacion', 'Admin::aceptarInvitacion');
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
    $routes->get('usuarios', 'Admin::usuarios');
    $routes->post('usuarios/crear', 'Admin::crearUsuario');
    $routes->post('usuarios/(:num)/rol', 'Admin::cambiarRol/$1');
    $routes->post('usuarios/(:num)/toggle', 'Admin::toggleActivo/$1');
    $routes->get('cambiar-clave', 'Admin::cambiarClave');
    $routes->post('cambiar-clave', 'Admin::cambiarClave');
    $routes->get('integraciones', 'Admin::integraciones');
    $routes->post('integraciones/correo', 'Admin::guardarCorreo');
    $routes->post('integraciones/correo/probar', 'Admin::probarCorreo');
    $routes->post('integraciones/bold', 'Admin::guardarBold');
    $routes->post('integraciones/bold/probar', 'Admin::probarBold');
});
