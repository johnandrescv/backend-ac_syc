<?php
/**
* Database configuration
*/
define('URL_IMAGES', 'https://bitapi.arxsmart.com/uploads/images/');
define('URL_RESOURCES', 'http://bitapi.arxsmart.com/uploads/default/');

// define('DB_USERNAME', 'root');
// define('DB_PASSWORD', 'agosto27'); 
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'Acc@ssC@ntrol!2020'); 
define('DB_HOST', 'localhost:3306');
define('DB_NAME', 'ac_syc');

define('OPERATION_SUCCESSFUL', 0);
define('OPERATION_FAILED', 1);
define('RECORD_DOES_NOT_EXIST', 2);
define('RECORD_DUPLICATED', 3);
define('ACCESS_DENIED', 4);
define('WRONG_FORMAT', 5);

define('LOG_IN', "I");
define('LOG_OUT', "O");

define('ESTADO_ACTIVO', 'A');
define('ESTADO_INACTIVO', 'I');
define('ESTADO_ELIMINADO', 'E');

/**
* EN PAGOS
* A = PENDIENTE
* I = PAGADOS
* ROLES DE ADMINISTRADORES
* 1 = MASTER 
* 2 = REPORTES
* 3 = PORTEROS
*/
?>