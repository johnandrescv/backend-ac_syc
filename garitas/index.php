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
$guardia_id = false;
$emp_id = false;
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
        if ($db->isValidApiKeyGuardia($api_key)) {
            // get user primary key id
            global $guardia_id;
            global $emp_id;
            $guardia = $db->getGuardiaByApiKey($api_key);
            if ($guardia != RECORD_DOES_NOT_EXIST){
                $guardia_id = $guardia["id_guardia"];
                $emp_id = $guardia["id_empresa"];
            }
			else APIKeyError(2);
        } else APIKeyError(0);
    } else APIKeyError(1);
}


/* 
------------- 
---------- TIPOS
-------------
*/
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

$app->post('/login', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('dni'));

    // reading params
    $dni = $app->request->post('dni');
    $res = $db->checkLoginGuardias($dni);
    if ($res != false) {
        //include '../mailing/email_register.php';
        $response["error"] = false;
        $response["message"] = "Ha iniciado sesión exitosamente.";
        $response["guardia"] = $db->getGuardiaById($res,true);
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Datos incorrectos.";
        echoRespnse(400, $response);
    }
});


$app->post('/categorias/:id/usuarios', 'authenticateAPIKey', function($id) use ($app) {
    $response = array();
    $db = new DbHandler();
    global $guardia_id;
    global $emp_id;
    // check for required params
    verifyRequiredParams(array('id_usuario'));

    // reading params
    $id_usuario = $app->request->post('id_usuario');
    $fecha_limite = date('Y-m-d', strtotime(date('Y-m-d') . ' +10 day'));;

    $res = $db->createCategoriaHistorial($id, $id_usuario, $guardia_id, $fecha_limite, ESTADO_INACTIVO, $emp_id);
    if ($res == OPERATION_SUCCESSFUL) {
        //include '../mailing/email_register.php';
        $response["error"] = false;
        $response["message"] = "Se ha solicitado la asignación del usuario a la categoría exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El usuario ya se encuentra asignado a una categoría";
        echoRespnse(400, $response);
    }
});

/* 
------------- 
---------- PUNTOS
-------------
*/

$app->get('/puntos', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $emp_id;
    $estado = empty($app->request->get('estado')) ? ESTADO_ACTIVO : $app->request->get('estado');
    $response["error"] = false;
    $response["puntos"] = $db->getPuntosByEmpresa($emp_id, $estado);
    echoRespnse(200, $response);
});

/* 
------------- 
---------- CATEGORIAS
-------------
*/

$app->get('/categorias', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $emp_id;
    $estado = empty($app->request->get('estado')) ? ESTADO_ACTIVO : $app->request->get('estado');
    $response["error"] = false;
    $response["categorias"] = $db->getCategoriasByEmpresa($emp_id, $estado);
    echoRespnse(200, $response);
});

/* 
------------- 
---------- USUARIOS
-------------
*/

$app->post('/proveedor', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    // check for required params
    verifyRequiredParams(array('dni','nombres'));

    // reading params
    $dni = $app->request->post('dni');
    $nombres = $app->request->post('nombres');
    $id_tipo = 3;
    $imagen = empty($app->request->post('imagen')) ? '' : $app->request->post('imagen');
    $correo = empty($app->request->post('correo')) ? '' : $app->request->post('correo');
    $edad = empty($app->request->post('edad')) ? '' : $app->request->post('edad');
    
    $res = $db->createUsuario($dni, $nombres, $id_tipo, $imagen, '', '', $edad, $correo);
    if ($res == OPERATION_SUCCESSFUL) {
        $response["error"] = false;
        $response["message"] = "Se ha registrado el proveedor exitosamente.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    } else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "El proveedor ya se encuentra registrado.";
        echoRespnse(400, $response);
    }
});

/* 
------------- 
---------- HISTORIAL
-------------
*/

$app->get('/historial', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $guardia_id;

    $response["error"] = false;
    $response["registros"] = $db->getHistorialByGuardia($guardia_id);
    echoRespnse(200, $response);
});

$app->post('/search', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $guardia_id;
    global $emp_id;
    // check for required params
    verifyRequiredParams(array('dni', 'id_punto'));

    // reading params
    $dni = $app->request->post('dni');
    $id_punto = $app->request->post('id_punto');

    $response["error"] = false;
    $response["usuarios"] = $db->searchUsuario($dni, $emp_id, $id_punto);
    echoRespnse(201, $response);
});

$app->post('/historial', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $guardia_id;
    global $emp_id;
    // check for required params
    verifyRequiredParams(array('dni', 'nombres', 'id_punto', 'detalle'));

    // reading params
    $dni = $app->request->post('dni');
    $nombres = $app->request->post('nombres');
    $id_punto = $app->request->post('id_punto');
    $detalle = $app->request->post('detalle');
    $placa = empty($app->request->post('placa')) ? '' : $app->request->post('placa');
    $salida = empty($app->request->post('salida')) ? 0 : $app->request->post('salida');
    
    $res = $db->createHistorial($guardia_id, $dni, $nombres, $id_punto, $emp_id, $detalle, $placa, $salida);
    if ($res == OPERATION_SUCCESSFUL) {
        //include '../mailing/email_register.php';
        $response["error"] = false;
        $response["message"] = "Registro creado con éxito.";
        echoRespnse(201, $response);
    } else if ($res == OPERATION_FAILED) {
        $response["error"] = true;
        $response["message"] = $GLOBALS["request_error"];
        echoRespnse(500, $response);
    }else if ($res == RECORD_DUPLICATED) {
        $response["error"] = true;
        $response["message"] = "Error del cliente";
        echoRespnse(400, $response);
    }
});

/* 
------------- 
---------- OPEN GARITA
-------------
*/

$app->post('/open_garita', 'authenticateAPIKey', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    global $guardia_id;
    global $emp_id;
    // check for required params
    verifyRequiredParams(array('id_punto'));

    // reading params
    $dni = $app->request->post('dni');
    $id_punto = $app->request->post('id_punto');

    $response["error"] = false;
    $response["usuarios"] = $db->searchUsuario($dni, $emp_id, $id_punto);
    echoRespnse(201, $response);
});

$app->run();

?>