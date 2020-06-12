<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: *");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('America/Guayaquil');

require_once '../include/Administracion.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';
 
\Slim\Slim::registerAutoloader();
 
$app = new \Slim\Slim();
 
// Global variables
$request_error = "Ha ocurrido un error al enviar su solicitud.";
$user_id = false;

/* 
------------- 
---------- GENERAL USE FUNCTIONS
-------------
*/
	
/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'La dirección de correo no es válida.';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
    // setting response content type to json
    $app->contentType('application/json'); 
    echo json_encode($response);
}

function APIKeyError($code) {
    $app = \Slim\Slim::getInstance();
	switch ($code) {
		case "0":
            $response["error"] = true;
            $response["message"] = "Acceso denegado. La clave del API no es válida.";
            echoRespnse(401, $response);
			break;
		case "1":
			$response["error"] = true;
			$response["message"] = "Se necesita una clave de API para acceder a esta funcionalidad.";
			echoRespnse(400, $response);
			break;
		default:
			$response['error'] = true;
			$response['message'] = $GLOBALS["request_error"];
			echoRespnse(500, $response);
			break;
	}
    $app->stop();
}

function authenticateAPIKey(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    // Verifying Authorization Header
    if (isset($headers['token'])) {
        $db = new DbHandler();
        $api_key = $headers['token'];
        // validating api key
        if ($db->isValidApiKeyAdmin($api_key)) {
            // get user primary key id
            global $user_id;
            $user = $db->getAdminByApiKey($api_key);
            if ($user != RECORD_DOES_NOT_EXIST)
                $user_id = $user["id_administrador"];
			else APIKeyError(2);
        } else APIKeyError(0);
    } else APIKeyError(1);
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header( "HTTP/1.1 200 OK" );
    exit();
}
$app->options('/{routes:.+}', function ($request, $response, $args) {
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header( "HTTP/1.1 200 OK" );
            exit();
            
        }
    });

    /* 
------------- 
----------  ROLES ADMINISTRADOR
-------------
*/

$app->get('/roles', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    $response["error"] = false;
    $response["roles"] = $db->getRoles();
    echoRespnse(200, $response);
});
   

/* 
------------- 
---------- TIPOS DE USUARIO
-------------
*/

$app->get('/tipos', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    $response["error"] = false;
    $response["tipos"] = $db->getTipos();
    echoRespnse(200, $response);
});
   
/* 
------------- 
---------- DASHBOARD
-------------
*/

$app->get('/dashboard', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $user_id;
    $mensual = empty($app->request->get('mensual')) ? false : true;
    if($mensual){
        $response["error"] = false;
        $response["entradas"] = $db->getCountTotalRegistrosByMensual(false);
        $response["salidas"] = $db->getCountTotalRegistrosByMensual(true);
        $response["socios"] = $db->getRegistroUsuarioByMensual(1);
        $response["invitados"] = $db->getRegistroUsuarioByMensual(2);
        $response["proveedores"] = $db->getRegistroUsuarioByMensual(3);
        $response["visitas"] = $db->getTotalVisitasByMonth();
        $response["tipo"] = $db->getTotalVisitasMensualByTipoUsuario();
        echoRespnse(200, $response);
    }else{
        $response["error"] = false;
        $response["entradas"] = $db->getCountTotalRegistrosByDay(false);
        $response["salidas"] = $db->getCountTotalRegistrosByDay(true);
        $response["socios"] = $db->getRegistroUsuarioByDay(1);
        $response["invitados"] = $db->getRegistroUsuarioByDay(2);
        $response["proveedores"] = $db->getRegistroUsuarioByDay(3);
        $response["visitas"] = $db->getTotalVisitasByDay();
        $response["tipo"] = $db->getTotalVisitasByTipoUsuario();
        echoRespnse(200, $response);
    }
});

/* 
------------- 
---------- ADMINISTRADORES
-------------
*/

$app->post('/admin', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('nombres', 'correo', 'usuario', 'clave', 'rol'));

    // reading params
    $nombres = $app->request->post('nombres');
    $correo = $app->request->post('correo');
    $usuario = $app->request->post('usuario');
    $clave = $app->request->post('clave');
    $rol = $app->request->post('rol');

    validateEmail($correo);

    $res = $db->createAdmin($nombres, $correo, $usuario, $clave, $rol);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha registrado el administrador exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El usuario o correo ya se encuentra registrado.";
        echoRespnse(400, $response);
    }
});

$app->put('/admin/:id', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('nombres', 'correo', 'usuario', 'rol'));

    // reading params
    $nombres = $app->request->put('nombres');
    $correo = $app->request->put('correo');
    $usuario = $app->request->put('usuario');
    $rol = $app->request->put('rol');
    validateEmail($correo);

    $res = $db->editAdmin($id, $nombres, $correo, $usuario, $rol);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha actualizado el administrador exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El usuario o correo se encuentran registrado.";
        echoRespnse(400, $response);
    }
});

$app->delete('/admin/:id', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    global $user_id;
    $res = $db->deleteAdmin($id);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha eliminado el administrador exitosamente.";
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }
});

$app->get('/admin', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    $pagina = empty($app->request->get('pagina')) ? 0 : $app->request->get('pagina');
    $estado = empty($app->request->get('estado')) ? ESTADO_ACTIVO : $app->request->get('estado');
    $response["error"] = false;
    $response["administradores"] = $db->getAdmins($estado);
    echoRespnse(200, $response);
});

$app->get('/admin/rol', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    $rol = empty($app->request->get('rol')) ? 3 : $app->request->get('rol');
    $response["error"] = false;
    $response["administradores"] = $db->getAdminByRol($rol);
    echoRespnse(200, $response);
});


$app->post('/login', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('usuario', 'clave'));

    // reading params
    $usuario = $app->request->post('usuario');
    $clave = $app->request->post('clave');
    
    $res = $db->checkLoginAdmin($usuario, $clave);
    if ($res[0]) {
        $response["error"] = false;
        $response["message"] = "Ha iniciado sesión exitosamente.";
        $response["administrador"] = $db->getAdminById($res[1],true);
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Datos incorrectos.";
        echoRespnse(400, $response);
    }
});

/* 
------------- 
---------- ASIGNACION DE ACCESOS
-------------
*/

$app->post('/admin/:id/accesos', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('accesos'));

    // reading params
    $accesos = $app->request->post('accesos');

    $res = $db->assignAccesoBatch($id, $accesos);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se han asignado los accesos exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }
});

/* 
------------- 
---------- ACCESOS
-------------
*/

$app->post('/acceso', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('nombres', 'ip', 'url', 'camara', 'is_salida'));

    // reading params
    $nombres = $app->request->post('nombres');
    $ip = $app->request->post('ip');
    $url = $app->request->post('url');
    $camara = $app->request->post('camara');
    $is_salida = $app->request->post('is_salida');

    $res = $db->createAcceso($nombres, $ip, $url, $camara, $is_salida);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha registrado el acceso exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "La ip del accesso ya se encuentra registrada.";
        echoRespnse(400, $response);
    }
});

$app->put('/acceso/:id', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('nombres', 'ip', 'url', 'camara', 'is_salida'));

    // reading params
    $nombres = $app->request->put('nombres');
    $ip = $app->request->put('ip');
    $url = $app->request->put('url');
    $camara = $app->request->put('camara');
    $is_salida = $app->request->put('is_salida');
    $res = $db->editAcceso($id, $nombres, $ip, $url, $camara, $is_salida);

    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha actualizado el acceso exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "La ip ya se encuentra registrada.";
        echoRespnse(400, $response);
    }
});

$app->delete('/acceso/:id', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    global $user_id;
    $res = $db->deleteAcceso($id);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha eliminado el acceso exitosamente.";
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }
});

$app->get('/acceso', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    $pagina = empty($app->request->get('pagina')) ? 0 : $app->request->get('pagina');
    $estado = empty($app->request->get('estado')) ? ESTADO_ACTIVO : $app->request->get('estado');
    $response["error"] = false;
    $response["accesos"] = $db->getAccesos($pagina, $estado);
    echoRespnse(200, $response);
});

/* 
------------- 
---------- USUARIOS
-------------
*/

$app->post('/usuarios/invitados', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('dni','nombres'));

    // reading params
    $dni = $app->request->post('dni');
    $nombres = $app->request->post('nombres');
    $id_tipo = 2;

    $res = $db->createUsuario($dni, $nombres, $id_tipo);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha registrado el invitado exitosamente.";
        $response["usuarios"] = $db->searchUsuariosByDNI($dni);
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El invitado ya se encuentra registrado.";
        echoRespnse(400, $response);
    }
});

$app->post('/usuarios', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('dni','nombres', 'id_tipo'));

    // reading params
    $dni = $app->request->post('dni');
    $nombres = $app->request->post('nombres');
    $id_tipo = $app->request->post('id_tipo');

    $res = $db->createUsuario($dni, $nombres, $id_tipo);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha registrado el usuario exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El usuario ya se encuentra registrado.";
        echoRespnse(400, $response);
    }
});

$app->put('/usuarios/:id', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('dni', 'nombres', 'id_tipo'));

    // reading params
    $dni = $app->request->put('dni');
    $nombres = $app->request->put('nombres');
    $id_tipo = $app->request->put('id_tipo');

    $res = $db->editUsuario($id, $dni, $nombres, $id_tipo);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha actualizado el usuario exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El usuario ya se encuentra registrada.";
        echoRespnse(400, $response);
    }
});

$app->delete('/usuarios/:id', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();

    $res = $db->deleteUsuario($id);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha eliminado el usuario exitosamente.";
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }
});

$app->post('/usuarios/search', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    verifyRequiredParams(array('texto'));
    $texto = $app->request()->post('texto');
    $db = new DbHandler();
    $response["error"] = false;
    $response["usuarios"] = $db->searchUsuariosByName($texto);
    echoRespnse(200, $response);
});

$app->get('/usuarios', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    $pagina = empty($app->request->get('pagina')) ? 0 : $app->request->get('pagina');
    $estado = empty($app->request->get('estado')) ? ESTADO_ACTIVO : $app->request->get('estado');
    $response["error"] = false;
    $response["usuarios"] = $db->getUsuarios($pagina, $estado);
    echoRespnse(200, $response);
});

/* 
------------- 
---------- INVITACIONES
-------------
*/

$app->post('/invitaciones', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('id_invitado','id_autorizacion', 'fecha_caducidad'));

    // reading params
    $id_invitado = $app->request->post('id_invitado');
    $id_autorizacion = $app->request->post('id_autorizacion');
    $fecha_caducidad = $app->request->post('fecha_caducidad');

    $res = $db->createInvitacion($id_invitado, $id_autorizacion, $fecha_caducidad);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha generado la invitación exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El usuario ya se encuentra invitado.";
        echoRespnse(400, $response);
    }
});

$app->put('/invitaciones/:id', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('id_invitado', 'fecha_caducidad'));

    // reading params
    $id_invitado = $app->request->put('id_invitado');
    $fecha_caducidad = $app->request->put('fecha_caducidad');

    $res = $db->editInvitacion($id, $id_invitado, $fecha_caducidad);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha actualizado la invitación exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "La invitación no es válida.";
        echoRespnse(400, $response);
    }
});

$app->delete('/invitaciones/:id', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();

    $res = $db->deleteInvitacion($id);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha eliminado la invitación exitosamente.";
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }
});

$app->post('/system/invitaciones', function() use ($app) {
    $response = array();
    $db = new DbHandler();

    $res = $db->deletePastInvitaciones();
    $response["error"] = false;
    $response["message"] = "Se ha eliminado la invitación exitosamente.";
    echoRespnse(200, $response);
});
/* 
------------- 
---------- HISTORIAL
-------------
*/

$app->post('/usuarios/search_dni', function() use ($app) {
    $response = array();
    verifyRequiredParams(array('texto'));
    $texto = $app->request()->post('texto');
    $db = new DbHandler();
    $response["error"] = false;
    $response["usuarios"] = $db->searchUsuariosByDNI($texto);
    echoRespnse(200, $response);
});

$app->post('/historial', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    verifyRequiredParams(array('id_usuario','id_administrador', 'id_acceso'));
    $id_administrador = $app->request()->post('id_administrador');
    $id_acceso = $app->request()->post('id_acceso');
    $id_usuario = $app->request()->post('id_usuario');
    $db = new DbHandler();
    $acceso = $db->getAccesoById($id_acceso);
    if($acceso['is_salida']){
        $last = $db->getLastEntradaHistorialByUsuario($id_usuario);
        if($last == RECORD_DOES_NOT_EXIST){
            $response["error"] = false;
            $response["message"] = "El usuario no posee accesos";
            echoRespnse(400, $response);
        }else{
            $res = $db->createSalidaHistorial($last['id_historial'], $id_administrador, $id_usuario);
            if($res == OPERATION_SUCCESSFUL){
                $response["error"] = false;
                $response["message"] = "Salida registrada correctamente";
                echoRespnse(200, $response);
            }else{
                $response["error"] = false;
                $response["message"] = $GLOBALS["request_error"];
                echoRespnse(500, $response);
            }
        }
    }else{
        $descripcion = empty($app->request->get('descripcion')) ? '' : $app->request->post('descripcion');
        $id_invitacion = empty($app->request->get('id_invitacion')) ? 0 : $app->request->post('id_invitacion');
        $res = $db->createEntradaHistorial($descripcion, $id_usuario, $id_invitacion, $id_administrador, $id_acceso);
        if($res == OPERATION_SUCCESSFUL){
            $response["error"] = false;
            $response["message"] = "Entrada registrada correctamente";
            echoRespnse(200, $response);
        }else{
            $response["error"] = false;
            $response["message"] = $GLOBALS["request_error"];
            echoRespnse(500, $response);
        }
    }
});

$app->get('/historial', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    verifyRequiredParams(array('fecha_inicio','fecha_fin','pagina'));
    global $user_id;

    $fecha_inicio = $app->request->get('fecha_inicio');
    $fecha_fin = $app->request->get('fecha_fin');
    $pagina = $app->request->get('pagina');
    $administrador = empty($app->request->get('administrador')) ? false : $app->request->get('administrador');
    $acceso = empty($app->request->get('acceso')) ? false : $app->request->get('acceso');
    $tipo_usuario = empty($app->request->get('tipo_usuario')) ? false : $app->request->get('tipo_usuario');

    $response["error"] = false;
    $response["registros"] = $db->getHistorialByDate($fecha_inicio, $fecha_fin, $pagina, $administrador, $acceso, $tipo_usuario);
    echoRespnse(200, $response);
});

/* 
------------- 
---------- LOGS
-------------
*/

$app->post('/logs', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('id_administrador','id_acceso'));

    // reading params
    $id_administrador = $app->request->post('id_administrador');
    $id_acceso = $app->request->post('id_acceso');

    $res = $db->createLog($id_administrador, $id_acceso);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha registrado el log exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }
});

$app->get('/logs', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    verifyRequiredParams(array('fecha_inicio','fecha_fin','pagina'));
    global $user_id;

    $fecha_inicio = $app->request->get('fecha_inicio');
    $fecha_fin = $app->request->get('fecha_fin');
    $pagina = $app->request->get('pagina');
    $administrador = empty($app->request->get('administrador')) ? false : $app->request->get('administrador');
    $acceso = empty($app->request->get('acceso')) ? false : $app->request->get('acceso');

    $response["error"] = false;
    $response["registros"] = $db->getLogsByDate($fecha_inicio, $fecha_fin, $pagina, $administrador, $acceso);
    echoRespnse(200, $response);
});
/* 
------------- 
---------- SYSTEM
-------------
*/

$app->put('/system/socios', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    verifyRequiredParams(array('data'));
    $data = $app->request->put('data');
    $db->gestionSocios($data);
    $response["error"] = false;
    $response["message"] = "Se ha actualizado correctamente la plantilla de socios";
    echoRespnse(200, $response);
});

$app->run();
?>