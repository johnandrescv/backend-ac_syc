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
    $imagen = empty($app->request->post('imagen')) ? '' : $app->request->post('imagen');
    $id_tipo = 2;

    $res = $db->createUsuario($dni, $nombres, $id_tipo, $imagen);
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
    $imagen = empty($app->request->post('imagen')) ? '' : $app->request->post('imagen');

    $res = $db->createUsuario($dni, $nombres, $id_tipo, $imagen);
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
    $imagen = empty($app->request->put('imagen')) ? '' : $app->request->put('imagen');
    $res = $db->editUsuario($id, $dni, $nombres, $id_tipo, $imagen);
    
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
    $response["usuarios"] = $db->getUsuarios($estado);
    echoRespnse(200, $response);
});

/* 
------------- 
---------- INVITACIONES
-------------
*/

$app->post('/generate', function() use ($app) {
    $response = array();
    $db = new DbHandler();
    
    verifyRequiredParams(array('dni_socio', 'dni_invitado', 'nombres_invitado', 'fecha_invitacion'));

    // reading params
    $dni_socio = $app->request->post('dni_socio');
    $dni_invitado = $app->request->post('dni_invitado');
    $nombres = $app->request->post('nombres_invitado');
    $fecha_invitacion = $app->request->post('fecha_invitacion');
    $imagen = empty($app->request->post('imagen')) ? '' : $app->request->post('imagen');
    $id_tipo = 2;
    $socio = $db->searchUsuariosByDNI($dni_socio);    
    $invitado = $db->searchUsuariosByDNI($dni_invitado);
    if ($invitado === false) {
        $res = $db->createUsuario($dni_invitado, $nombres, $id_tipo, $imagen);
        if ($res == OPERATION_SUCCESSFUL) {
            $invitado = $db->searchUsuariosByDNI($dni_invitado);
        } else {
            $response["error"] = true;
            $response["message"] = "Vuelva a intentar más adelante.";
            echoRespnse(400, $response);
            return;
        }
    }

    if ($socio === false || $socio['tipo']['id_tipo'] === 2) {
        $response["error"] = true;
        $response["message"] = "El socio no existe.";
        echoRespnse(400, $response);
        return;
    }

    $res = $db->createInvitacion($invitado['id_usuario'], $socio['id_usuario'], $fecha_invitacion);
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

$app->run();
?>