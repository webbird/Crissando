<?php
$ROUTES = array(
    '/' => array(
        'methods' => 'GET,POST',
        'stack'   => 'CRPage::load,CRPage::view',
        'auth'    => false,
    ),
    '/page/*'  => '/',
    '/login*' => array(
        'methods' => 'GET,POST',
        'stack'   => 'CRBackend::run',
        'auth'    => true,
    ),
    '/admin/*' => array(
        'methods' => 'GET,POST',
        'stack'   => 'CRBackend::run',
        'auth'    => true,
        'role'    => 'admin',
        'info'    => 'You need to login with an admin account to access the backend',
    ),
    '/backend/*' => '/admin/*',
);

