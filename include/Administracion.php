<?php
/**
 * Class to handle all db operations
 * Version 1.1
 * 18/10/2019 14:36
 * 
 */
date_default_timezone_set('America/Guayaquil');

require_once dirname(__FILE__) . '/Garitas.php';
require_once dirname(__FILE__) . '/Reportes.php';

class DbHandler {

    use Garitas;
    use Reportes;
    private $conn;
 
    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
 
    /* 
	------------- 
	---------- GENERAL USE FUNCTIONS
	-------------
	*/
 
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    private function dateTimeToFecha($datetime) {
        $parts = explode(" ", $datetime);
        $fecha_temp = explode("-", $parts[0]);
        $hora_temp = explode(":", $parts[1]);
        return $fecha_temp[2] . "/" . $fecha_temp[1] . "/" . $fecha_temp[0] . " " . $hora_temp[0] . ":" . $hora_temp[1];
    }
	
	private function generateRandomString($length=20,$uc=TRUE,$n=TRUE,$sc=FALSE){
		$source = 'abcdefghijklmnopqrstuvwxyz';
		if($uc==1) $source .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		if($n==1) $source .= '1234567890';
		if($sc==1) $source .= '|$=^*_';if($length>0){
			$rstr = "";
			$source = str_split($source,1);
			for($i=1; $i<=$length; $i++){
				mt_srand((double)microtime() * 1000000);
				$num = mt_rand(1,count($source));
				$rstr .= $source[$num-1];
			}
		}
		return $rstr;
    }
    
	function renameDuplicates($image, $file1){
       $path = "../uploads/images/";
       $fileName = pathinfo($path . $file1, PATHINFO_FILENAME);
       $fileExtension = ".png" ;
       $returnValue = $fileName . $fileExtension;
       $copy = 1;
       while(file_exists($path . $returnValue))
       {
           $returnValue = $fileName . '-ms-'. $copy . $fileExtension;
           $copy++;
       }
       if(file_put_contents($path. $returnValue, base64_decode($image)))
            return $returnValue;
        else
            return false;
   }

	private function getDayName($dia) {
		switch ($dia) {
			case "1":
				return "Lunes";
				break;
			case "2":
				return "Martes";
				break;
			case "3":
				return "Miércoles";
				break;
			case "4":
				return "Jueves";
				break;
			case "5":
				return "Viernes";
				break;
			case "6":
				return "Sábado";
				break;
			case "7":
				return "Domingo";
				break;
		}
    }
	
	private function getMonthName($month) {
		switch ($month) {
			case "01":
				return "Enero";
				break;
			case "02":
				return "Febrero";
				break;
			case "03":
				return "Marzo";
				break;
			case "04":
				return "Abril";
				break;
			case "05":
				return "Mayo";
				break;
			case "06":
				return "Junio";
				break;
			case "07":
				return "Julio";
				break;
			case "08":
				return "Agosto";
				break;
			case "09":
				return "Septiembre";
				break;
			case "10":
				return "Octubre";
				break;
			case "11":
				return "Noviembre";
				break;
			case "12":
				return "Diciembre";
				break;
		}
    }
    
    private function roundToNextPrecisionMins(\DateTime $dt, $precision = 10) {
        $s = $precision * 60;
        $dt->setTimestamp($s * ceil($dt->getTimestamp() / $s));
        return $dt;
    }
	
    private function changeEstadoRecord($tabla, $campo, $id, $estado) {
        $sql_statement = "UPDATE " . $tabla . " SET estado = ? WHERE " . $campo . " = ?";
        $stmt = $this->conn->prepare($sql_statement);
        $stmt->bind_param("ss", $estado, $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
	
    private function changeEstadoRecordTwoFields($tabla, $campo, $id, $campo2, $id2, $estado) {
        $sql_statement = "UPDATE " . $tabla . " SET estado = ? WHERE " . $campo . " = ? AND " . $campo2 . " = ?";
        $stmt = $this->conn->prepare($sql_statement);
        $stmt->bind_param("sss", $estado, $id, $id2);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
	
    private function deleteRecord($tabla, $campo, $id) {
        return $this->changeEstadoRecord($tabla, $campo, $id, ESTADO_ELIMINADO);
    }
	
	private function deleteRecordTwoFields($tabla, $campo, $id, $campo2, $id2, $creation_user = false) {
        return $this->changeEstadoRecordTwoFields($tabla, $campo, $id, $campo2, $id2, ESTADO_ELIMINADO) && (!$creation_user || $this->createEliminacion($tabla, $id, $id2, $creation_user, ""));
    }

    /* 
    ------------- 
    ---------- ROLES ADMINISTRADOR
    -------------
    */

    public function getRoles() {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_rol FROM roles order by nombres");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getRolById($row["id_rol"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }    

    public function getRolById($id) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from roles where id_rol = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_rol' => $row['id_rol'],
                'nombres' => $row['nombres'],
            );
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    /* 
    ------------- 
    ---------- TIPOS DE USUARIO
    -------------
    */

    public function getTipos($estado = ESTADO_ACTIVO) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_tipo FROM tipos WHERE estado = ? order by id_tipo");
        $stmt->bind_param("s", $estado);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getTipoById($row["id_tipo"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }    

    public function getTipoById($id) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from tipos where id_tipo = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_tipo' => $row['id_tipo'],
                'nombres' => $row['nombres'],
                'fecha_creacion' => $row['fecha_creacion'],
            );
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    /* 
    ------------- 
    ---------- ADMINISTRADORES
    -------------
    */
    public function checkLoginAdmin($usuario, $clave) {
        $stmt = $this->conn->prepare("SELECT id_administrador, clave FROM administradores WHERE usuario = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->bind_result($id, $clave_hash);
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            $stmt->close();
            return array(PassHash::check_password($clave_hash, $clave), $id);
        } else {
            $stmt->close();
            return array(FALSE);
        }
    }

    private function isAdminExists($usuario, $correo, $id = false) {
        if ($id) {
            $stmt = $this->conn->prepare("SELECT id_administrador from administradores WHERE (usuario = ? OR correo = ?) AND id_administrador != ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("sss", $usuario, $correo, $id);
        } else {
            $stmt = $this->conn->prepare("SELECT id_administrador from administradores WHERE (usuario = ? OR correo = ?) AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("ss", $usuario, $correo);
        }
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function createAdmin($nombres, $correo, $usuario, $clave, $rol) {
        if (!$this->isAdminExists($usuario, $correo)) {
            $response = array();
            $password_hash = PassHash::hash($clave);
            $api_key = $this->generateApiKey();
            $stmt = $this->conn->prepare("INSERT INTO administradores(nombres, correo, usuario, clave, rol, api_key) values (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $nombres, $correo, $usuario, $password_hash, $rol, $api_key);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function editAdmin($id, $nombres, $correo, $usuario, $rol) {
        if (!$this->isAdminExists($usuario, $correo, $id)) {
            $response = array();
            $stmt = $this->conn->prepare("UPDATE administradores SET nombres = ?, correo = ?, usuario = ?, rol=? WHERE id_administrador = ?");
            $stmt->bind_param("sssss", $nombres, $correo, $usuario, $rol, $id);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function deleteAdmin($id) {
        return $this->deleteRecord("administradores", "id_administrador", $id) ? OPERATION_SUCCESSFUL : OPERATION_FAILED;
    }

    public function getAdminById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from administradores where id_administrador = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_administrador' => $row['id_administrador'],
                'nombres' => $row['nombres'],
                'correo' => $row['correo'],
                'usuario' => $row['usuario'],
                'rol' => $this->getRolById($row['rol']),
                'accesos' => $this->getAccesosByAdmin($row['id_administrador']),
                'fecha_creacion' => $row['fecha_creacion'],
            );
            if($detailed) {
                $response["api_key"] = $row['api_key'];
            }
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function getAdmins($pagina, $estado = ESTADO_ACTIVO) {
        $pagina = $pagina * 50;
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_administrador FROM administradores WHERE estado = ? order by id_administrador limit ?,50");
        $stmt->bind_param("ss", $estado, $pagina);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getAdminById($row["id_administrador"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    public function getAdminByApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id_administrador FROM administradores WHERE api_key = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getAdminById($row["id_administrador"]);
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function isValidApiKeyAdmin($api_key) {
        $stmt = $this->conn->prepare("SELECT id_administrador from administradores WHERE api_key = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /* 
    ------------- 
    ---------- ACCESOS
    -------------
    */

    private function isAccesoExists($ip, $id = false) {
        if ($id) {
            $stmt = $this->conn->prepare("SELECT id_acceso from accesos WHERE ip = ? AND id_acceso != ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("ss", $ip, $id);
        } else {
            $stmt = $this->conn->prepare("SELECT id_acceso from accesos WHERE ip = ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("s", $ip);
        }
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function createAcceso($nombres, $ip, $url, $camara, $is_salida) {
        if (!$this->isAccesoExists($ip)) {
            $response = array();
            $stmt = $this->conn->prepare("INSERT INTO accesos(nombres, ip, url, camara, is_salida) values (?,?,?,?,?)");
            $stmt->bind_param("sssss", $nombres, $ip, $url, $camara, $is_salida);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function editAcceso($id, $nombres, $ip, $url, $camara, $is_salida) {
        if (!$this->isAccesoExists($ip, $id)) {
            $response = array();
            $stmt = $this->conn->prepare("UPDATE accesos SET nombres = ?, url = ?, is_salida = ?, camara = ? WHERE id_acceso = ?");
            $stmt->bind_param("sssss", $nombres, $url, $is_salida, $camara, $id);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function deleteAcceso($id) {
        return $this->deleteRecord("accesos", "id_acceso", $id) ? OPERATION_SUCCESSFUL : OPERATION_FAILED;
    }

    public function getAccesoById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from accesos where id_acceso = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_acceso' => $row['id_acceso'],
                'nombres' => $row['nombres'],
                'ip' => $row['ip'],
                'url' => $row['url'],
                'camara' => $row['camara'],
                'is_salida' => $row['is_salida'],
                'fecha_creacion' => $row['fecha_creacion'],
            );
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function getAccesos($pagina, $estado = ESTADO_ACTIVO) {
        $pagina = $pagina * 50;
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_acceso FROM accesos WHERE estado = ? order by id_acceso limit ?,50");
        $stmt->bind_param("ss", $estado, $pagina);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getAccesoById($row["id_acceso"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    /* 
    ------------- 
    ---------- USUARIOS
    -------------
    */

    private function isUsuarioExists($dni, $id = false) {
        if ($id) {
            $stmt = $this->conn->prepare("SELECT id_usuario from usuarios WHERE dni = ? AND id_usuario != ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("ss", $dni, $id);
        } else {
            $stmt = $this->conn->prepare("SELECT id_usuario from usuarios WHERE dni = ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("s", $dni);
        }
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function createUsuario($dni, $nombres, $id_tipo) {
        if (!$this->isUsuarioExists($dni)) {
            $response = array();
            $stmt = $this->conn->prepare("INSERT INTO usuarios(dni, nombres, id_tipo) values (?,?,?)");
            $stmt->bind_param("sss", $dni, $nombres, $id_tipo);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function editUsuario($id, $dni, $nombres, $id_tipo) {
        if (!$this->isUsuarioExists($dni, $id)) {
            $response = array();
            $stmt = $this->conn->prepare("UPDATE usuarios SET nombres = ?, id_tipo = ? WHERE id_usuario = ?");
            $stmt->bind_param("sss", $nombres, $id_tipo, $id);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function deleteUsuario($id) {
        return $this->deleteRecord("usuarios", "id_usuario", $id) ? OPERATION_SUCCESSFUL : OPERATION_FAILED;
    }

    public function getUsuarioById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from usuarios where id_usuario = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_usuario' => $row['id_usuario'],
                'dni' => $row['dni'],
                'nombres' => $row['nombres'],
                'tipo' => $this->getTipoById($row['id_tipo']),
                'fecha_creacion' => $row['fecha_creacion'],
            );
            if($row['id_tipo'] === 2){
                $response['invitaciones'] = $this->getInvitacionesByInvitado($row['id_usuario']);
            }
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function getUsuarios($pagina, $estado = ESTADO_ACTIVO) {
        $pagina = $pagina * 50;
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_usuario FROM usuarios WHERE estado = ? order by id_usuario limit ?,50");
        $stmt->bind_param("ss", $estado, $pagina);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getUsuarioById($row["id_usuario"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    public function searchUsuariosByName($texto) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_usuario FROM usuarios WHERE nombres LIKE '%".$texto."%'");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getUsuarioById($row["id_usuario"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    public function searchUsuariosByDNI($texto) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_usuario FROM usuarios WHERE dni = ? AND estado != '". ESTADO_ELIMINADO ."'");
        $stmt->bind_param("s", $texto);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getUsuarioById($row["id_usuario"]);
        } else return false;
    }

    /* 
    ------------- 
    ---------- SOCIOS
    -------------
    */

    public function gestionSocios($data) {
        $this->deactivateUser(1);
        $socios = json_decode($data, true);
        foreach($socios as $socio) {
            $existe = $this->searchSociosByDNI($socio["dni"]);
            if($existe === false) {
                $this->createUsuario($socio["dni"], $socio["nombres"], 1);
            }else{
                $this->activateUser($existe["id_usuario"]);
            }
        }
        return true;
    }

    
    public function searchSociosByDNI($texto) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_usuario FROM usuarios WHERE dni = ?");
        $stmt->bind_param("s", $texto);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getUsuarioById($row["id_usuario"]);
        } else return false;
    }

    public function activateUser($id) {
        $response = array();
        $stmt = $this->conn->prepare("UPDATE usuarios SET estado = 'A' WHERE id_usuario = ?");
        $stmt->bind_param("s", $id);
        $result = $stmt->execute();
        //printf("Error: %s.\n", $stmt->error);
        $stmt->close();

        if ($result) {
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }
    }

    private function deactivateUser($id_tipo) {
        $response = array();
        $stmt = $this->conn->prepare("UPDATE usuarios SET estado = 'E' WHERE id_tipo = ?");
        $stmt->bind_param("s", $id_tipo);
        $result = $stmt->execute();
        //printf("Error: %s.\n", $stmt->error);
        $stmt->close();

        if ($result) {
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }
    }
    /* 
    ------------- 
    ---------- INVITACIONES
    -------------
    */

    private function isInvitacionExists($id_invitado, $id = false) {
        if ($id) {
            $stmt = $this->conn->prepare("SELECT id_invitado from invitaciones WHERE id_invitado = ? AND id_invitacion != ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("ss", $id_invitado, $id);
        } else {
            $stmt = $this->conn->prepare("SELECT id_invitado from invitaciones WHERE id_invitado = ? AND estado != '" . ESTADO_ELIMINADO . "'");
            $stmt->bind_param("s", $id_invitado);
        }
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function createInvitacion($id_invitado, $id_autorizacion, $fecha_caducidad) {
        if (!$this->isInvitacionExists($id_invitado)) {
            $response = array();
            $stmt = $this->conn->prepare("INSERT INTO invitaciones(id_invitado, id_autorizacion, fecha_caducidad) values (?,?,?)");
            $stmt->bind_param("sss", $id_invitado, $id_autorizacion, $fecha_caducidad);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function editInvitacion($id, $id_invitado, $fecha_caducidad) {
        if (!$this->isInvitacionExists($id_invitado, $id)) {
            $response = array();
            $stmt = $this->conn->prepare("UPDATE invitaciones SET fecha_caducidad = ? WHERE id_invitacion = ?");
            $stmt->bind_param("ss", $fecha_caducidad, $id);
            $result = $stmt->execute();
            //printf("Error: %s.\n", $stmt->error);
            $stmt->close();

            if ($result) {
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        } else return RECORD_DUPLICATED;
        return $response;
    }

    public function deleteInvitacion($id) {
        return $this->deleteRecord("invitaciones", "id_invitacion", $id) ? OPERATION_SUCCESSFUL : OPERATION_FAILED;
    }

    public function getInvitaciones() {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_invitacion FROM invitaciones WHERE estado != '".ESTADO_ELIMINADO."' order by id_invitacion");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getInvitacionesById($row["id_invitacion"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    public function getInvitacionesByInvitado($id_invitado) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_invitacion FROM invitaciones WHERE id_invitado = ? AND estado != '".ESTADO_ELIMINADO."'");
        $stmt->bind_param("s", $id_invitado);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getInvitacionesById($row["id_invitacion"]);
            $res['socio'] = $this->getUsuarioById($res["id_autorizacion"]);
            return $res;
        } else return false;
    }

    public function getInvitacionesById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from invitaciones where id_invitacion = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_invitacion' => $row['id_invitacion'],
                'id_invitado' => $row['id_invitado'],
                'id_autorizacion' => $row['id_autorizacion'],
                'fecha_caducidad' => $row['fecha_caducidad'],
                'fecha_creacion' => $row['fecha_creacion'],
            );
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function deletePastInvitaciones() {
        $today = date("Y-m-d");
        $invitaciones = $this->getInvitaciones();
        foreach($invitaciones as $invitacion) {
            if ($today > $invitacion['fecha_caducidad']){
                $response = $this->deleteInvitacion($invitacion['id_invitacion']);
                if(!$response){
                    error_log('La invitacion con ID'.$invitacion['id_invitacion'].'. No se pudo eliminar');
                }
            }
        }
        return true;
    }
    /* 
    ------------- 
    ---------- ASIGNACION DE ACCESOS
    -------------
    */

    public function assignAccesoBatch($id_administrador, $accesos) { 
        if ($this->deleteAssignAccesos($id_administrador) == OPERATION_SUCCESSFUL) {
            $response = array();
            $acceso_array = explode(",", $accesos);
            for($i = 0; $i < count($acceso_array); $i++){
                $result = $this->assignAcceso($id_administrador, $acceso_array[$i]);
                if($result != OPERATION_SUCCESSFUL){
                    $response[] = $i;
                }
            }
            return OPERATION_SUCCESSFUL;
        }else
            return OPERATION_FAILED;
    }

    public function assignAcceso($id_administrador, $id_acceso) {
        $stmt = $this->conn->prepare("INSERT INTO administradores_accesos(id_administrador, id_acceso) values(?,?)");
        $stmt->bind_param("ss", $id_administrador, $id_acceso);
        $result = $stmt->execute();
        //printf("Error: %s.\n", $stmt->error);
        $stmt->close();

        if ($result) {
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }        
    }

    public function deleteAssignAccesos($id) {
        return $this->deleteRecord("administradores_accesos", "id_administrador", $id) ? OPERATION_SUCCESSFUL : OPERATION_FAILED;
    }

    public function getAccesosByAdmin($id_administrador) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_acceso FROM administradores_accesos WHERE id_administrador = ? AND estado != '".ESTADO_ELIMINADO."' order by id_acceso");
        $stmt->bind_param("s", $id_administrador);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getAccesoById($row["id_acceso"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    /* 
    ------------- 
    ---------- HISTORIAL
    -------------
    */

    public function createEntradaHistorial($descripcion, $id_usuario, $id_invitacion, $id_administrador, $id_acceso) {
        $response = array();
        $stmt = $this->conn->prepare("INSERT INTO historial(descripcion, id_usuario, id_invitacion, id_administrador, id_acceso) values (?,?,?,?,?)");
        $stmt->bind_param("sssss", $descripcion, $id_usuario, $id_invitacion, $id_administrador, $id_acceso);
        $result = $stmt->execute();
        //printf("Error: %s.\n", $stmt->error);
        $stmt->close();

        if ($result) {
            $this->createLog($id_administrador, $id_acceso);
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }
        return $response;
    }

    public function createSalidaHistorial($id_historial, $id_administrador, $id_acceso) {
        $response = array();
        $stmt = $this->conn->prepare("UPDATE historial SET fecha_salida = NOW() WHERE id_historial = ?");
        $stmt->bind_param("s", $id_historial);
        $result = $stmt->execute();
        //printf("Error: %s.\n", $stmt->error);
        $stmt->close();

        if ($result) {
            $this->createLog($id_administrador, $id_acceso);
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }
        return $response;
    }

    public function getHistorialById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from historial where id_historial = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $auth = $row['id_invitacion']? $this->getInvitacionesById($row['id_invitacion']) : false;
            $response = array(
                'id_historial' => $row['id_historial'],
                'usuario' => $this->getUsuarioById($row['id_usuario']),
                'autorizacion' => $auth['socio']['nombres'],
                'administrador' => $this->getAdminById($row['id_administrador']),
                'acceso' => $this->getAccesoById($row['id_acceso']),
                'fecha_entrada' => $row['fecha_creacion'],
                'fecha_salida' => $row['fecha_salida'],
            );
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function getLastEntradaHistorialByUsuario($id_usuario) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_historial FROM historial WHERE id_usuario = ? order by id_historial desc limit 1");
        $stmt->bind_param("s", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getHistorialById($row["id_historial"]);
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function getHistorialByDate($fecha_inicio, $fecha_fin, $pagina, $administrador, $acceso, $tipo_usuario) {
        $response = array();
        $pagina = $pagina*50;
        $administrador = $administrador? " AND h.id_administrador = $administrador " : "";
        $acceso = $acceso? " AND h.id_acceso = $acceso " : "";
        if($tipo_usuario){
            $stmt = $this->conn->prepare("SELECT h.id_historial FROM historial h, usuarios u WHERE h.id_usuario = u.id_usuario AND u.id_tipo = ? AND CAST(h.fecha_creacion AS DATE) BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) $administrador $acceso ORDER BY h.fecha_creacion desc LIMIT ?,50");
            $stmt->bind_param("ssss", $tipo_usuario, $fecha_inicio, $fecha_fin, $pagina);
        }else{
            $stmt = $this->conn->prepare("SELECT h.id_historial FROM historial h WHERE CAST(h.fecha_creacion AS DATE) BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) $administrador $acceso ORDER BY h.fecha_creacion desc LIMIT ?,50");
            $stmt->bind_param("sss", $fecha_inicio, $fecha_fin, $pagina);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getHistorialById($row["id_historial"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }
    /* 
    ------------- 
    ---------- LOGS DE PUERTA
    -------------
    */

    public function createLog($id_administrador, $id_acceso) {
        $response = array();
        $stmt = $this->conn->prepare("INSERT INTO logs(id_administrador, id_acceso) values (?,?)");
        $stmt->bind_param("ss", $id_administrador, $id_acceso);
        $result = $stmt->execute();
        //printf("Error: %s.\n", $stmt->error);
        $stmt->close();

        if ($result) {
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }
        return $response;
    }

    public function getLogsByDate($fecha_inicio, $fecha_fin, $pagina, $administrador, $acceso) {
        $response = array();
        $administrador = $administrador? " AND id_administrador = $administrador " : "";
        $acceso = $acceso? " AND id_acceso = $acceso " : "";
        $pagina = $pagina*50;
        $stmt = $this->conn->prepare("SELECT id_log FROM logs WHERE CAST(fecha_creacion AS DATE) BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) $administrador $acceso ORDER BY fecha_creacion desc limit ?,50;");
        $stmt->bind_param("sss", $fecha_inicio, $fecha_fin, $pagina);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getLogById($row["id_log"]);
            if ($res != RECORD_DOES_NOT_EXIST)
				$response[] = $res;
        }
        return $response;
    }

    public function getLogById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from logs where id_log = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_log' => $row['id_log'],
                'administrador' => $this->getAdminById($row['id_administrador']),
                'acceso' => $this->getAccesoById($row['id_acceso']),
                'fecha_creacion' => $row['fecha_creacion'],
            );
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }
    /* 
    ------------- 
    ---------- SYSTEM
    -------------
    */

    public function inactiveEmpresas(){
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_empresa FROM pagos WHERE CAST(NOW() as DATE) > CAST(fecha_caducidad as DATE)");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = $this->getEmpresaById($row["id_empresa"]);
            if ($res != RECORD_DOES_NOT_EXIST){
                $this->sendEmailInactivacion($res["correo"]);
                $this->changeEstadoRecord("empresas", "id_empresa", $res["id_empresa"], ESTADO_INACTIVO);
            }
        }
        return OPERATION_SUCCESSFUL;
    }

    public function inactiveCategoriasUsuario(){
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_categoria_usuario FROM categorias_usuarios WHERE CAST(NOW() as DATE) > CAST(fecha_limite as DATE)");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $this->deleteRecord("categorias_usuarios", "id_categoria_usuario", $row["id_categoria_usuario"]);
        }
        return OPERATION_SUCCESSFUL;
    }

    /* 
    ------------- 
    ---------- EMAILS
    -------------
    */
    private function sendEmailNewAccount($correo, $clave){
        $mensaje = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Cuenta</title>
        </head>
        <body>
            <div width="100%" style="background-color:#303030;padding-bottom: 20px;padding-top: 20px" align="center">
                <img src="'.URL_RESOURCES.'arxlogb.png" width="15%" alt="">
            </div>
            <div align="center" style="font-family:\'Gill Sans\', \'Gill Sans MT\', Calibri, \'Trebuchet MS\', sans-serif">
                <h1 style="font-size:400%">¡Bienvenido!</h1>
                <p style="font-size:200%">Tu cuenta ha sido creada con éxito</p>
                <p style="font-size:150%"> Tu clave de acceso es: <span style="background-color: yellow; color: black;">' . $clave . '</span></p>
            </div>
            <div style="font-family:\'Gill Sans\', \'Gill Sans MT\', Calibri, \'Trebuchet MS\', sans-serif>
                </td>
                </tr>
                <p align="center " style="padding-top:60px ">Cordialmente,<br> El Equipo de ARX<br>
                    <p align="center ">Aplicación desarrollada por TyM Smart S.A.
                        <a href="https://www.tymsmart.com " target="_blank " rel="noopener noreferrer " data-auth="NotApplicable ">www.tymsmart.com</a></p>
            </div>
        </body>
        </html>';
        return $this->sendEmail($mensaje, $correo);
    }

    private function sendEmailInactivacion($correo){
        $mensaje = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Cuenta</title>
        </head>
        <body>
            <div width="100%" style="background-color:#303030;padding-bottom: 20px;padding-top: 20px" align="center">
                <img src="'.URL_RESOURCES.'arxlogb.png" width="15%" alt="">
            </div>
            <div align="center" style="font-family:\'Gill Sans\', \'Gill Sans MT\', Calibri, \'Trebuchet MS\', sans-serif">
                <h1 style="font-size:400%">¡Oh no!</h1>
                <p style="font-size:200%">Tu cuenta ha sido suspendida por falta de poco. Acercate a cancelar lo más pronto posible</p>
            </div>
            <div style="font-family:\'Gill Sans\', \'Gill Sans MT\', Calibri, \'Trebuchet MS\', sans-serif>
                </td>
                </tr>
                <p align="center " style="padding-top:60px ">Cordialmente,<br> El Equipo de ARX<br>
                    <p align="center ">Aplicación desarrollada por TyM Smart S.A.
                        <a href="https://www.tymsmart.com " target="_blank " rel="noopener noreferrer " data-auth="NotApplicable ">www.tymsmart.com</a></p>
            </div>
        </body>
        </html>';
        return $this->sendEmail($mensaje, $correo, "Inactivación por falta de pago");
    }

    private function sendEmailRecover($auth_code, $correo) {
        $mensaje = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Recuperacion Contraseña</title>
        </head>
        <body>
            <header>
                <div width="100px" style="background-color:#303030;padding-bottom: 20px;padding-top: 20px" align="center">
                    <img src="'.URL_RESOURCES.'arxlogb.png" width="15%" alt="">
                </div>
            </header>
            <div style="font-family:\Gill Sans\">
                <h1 align="center" style="font-size:200%">&#161;Recuperaci&oacute;n de contrase&ntilde;a!</h1>
                <table width="452" border="0" align="center" cellpadding="0" cellspacing="0" height="80">
                        <tbody>
                        <tr>
                        <td colspan="3" height="21">
                        <table width="452" border="0" cellpadding="0" cellspacing="0">
                        <tbody>
                        <tr>
                        <td rowspan="2" valign="middle" bgcolor="303030" width="126" class="x_blanco8">
                        <div align="center"><i><b><font color="#FFFFFF">Detalle</font></b></i></div>
                        </td>
                        <td height="12" valign="top" width="319"></td>
                        </tr>
                        <tr>
                        <td bgcolor="303030" width="319" class="x_style1" style="color:#2684C2"></td>
                        </tr>
                        </tbody>
                        </table>
                        </td>
                        </tr>
                        <tr>
                        <td width="1" bgcolor="303030"></td>
                        <td width="450" height="80" valign="top">
                        <table width="90%" border="0" cellspacing="0" cellpadding="4" align="center">
                        <tbody>
                        <tr>
                        <td class="x_gris8" width="100%" valign="top" colspan="2">
                        <div align="justify"><font color="#666666">Notificaci&oacute;n electr&oacute;nica de clave temporal para cuenta de usuario de <span data-markjs="true" class="markiqmlgw9t4" style="background-color: yellow; color: black;"></span> ARX</font></div>
                        </td>
                        </tr>
                        <tr>
                        <td class="x_gris8" width="145" valign="top">
                        <div align="justify"><font color="#666666">C&oacute;digo de seguridad </font></div>
                        </td>
                        <td class="x_negro8" width="244">' . $auth_code . '</td>
                        </tr>
                        <tr>
                        <td class="x_gris8" width="100%" valign="top" colspan="2">
                        <div align="justify"><font color="#666666">Recuerde el cuidado especial de su informaci&oacute;n de acceso a <span data-markjs="true" class="markiqmlgw9t4" style="background-color: yellow; color: black;"></span> ARX, misma que por ning&uacute;n motivo debe ser compartida con terceros.</font></div>
                        </td>
                        </tr>
                        </tbody>
                        </table>
                        </td>
                        <td width="1" bgcolor="303030"></td>
                        </tr>
                        <tr>
                        <td colspan="3" height="1" bgcolor="303030"></td>
                        </tr>
                        </tbody>
                        </table>
                        <p align="center" style="padding-top:60px ; font-family: \'Gill Sans\'">Cordialmente,<br>
                        El Equipo de ARX<br>
                    <p align="center">Este mensaje de correo electn&oacute;nico se ha generado autom&aacute;ticamente. Si necesita ayuda, vis&iacute;tenos
                    <a href="https://www.arxsmart.com" target="_blank" rel="noopener noreferrer" data-auth="NotApplicable">www.arxsmart.com</a></p>
            </div>
        </body>
        </html>';
        return $this->sendEmail($mensaje, $correo);
    }

    private function sendEmail($mensaje, $correo, $title = "Notificación ARX Bitácoras") {
        require_once '../mailing/PHPMailer-master/PHPMailerAutoload.php';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();                                        // Set mailer to use SMTP
            $mail->SMTPDebug = 0;                                 // Enable verbose debug output
            $mail->Host = 'smtp.gmail.com';                              // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                                // Enable SMTP authentication
            $mail->Username = 'info@arxsmart.com';                   // SMTP username
			$mail->Password = '$martArx.2019';                         // SMTP password
            $mail->SMTPSecure = 'tls';                              // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                      // TCP port to connect to
            $mail->IsHTML(true);
            $mail->From = 'info@arxsmart.com';
            $mail->FromName = utf8_decode('ARX Bitácoras');
            $mail->AddAddress($correo);
            $mail->Subject = utf8_decode($title);
            $mail->Body = $mensaje;
            $mail->send();
        } catch (phpmailerException $e) {
            //echo $e->errorMessage(); //Pretty error messages from PHPMailer
            return false;
        } catch (Exception $e) {
            //echo $e->getMessage(); //Boring error messages from anything else!
            return false;
        }
        return true;
    } 

    private function sendCategoriaEmail($id_punto, $title) {
        require_once '../mailing/PHPMailer-master/PHPMailerAutoload.php';
        $punto = $this->getPuntosById($id_punto);
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();                                        // Set mailer to use SMTP
            $mail->SMTPDebug = 0;                                 // Enable verbose debug output
            $mail->Host = 'smtp.gmail.com';                              // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                                // Enable SMTP authentication
            $mail->Username = 'info@arxsmart.com';                   // SMTP username
			$mail->Password = '$martArx.2019';                         // SMTP password
            $mail->SMTPSecure = 'tls';                              // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                      // TCP port to connect to
            $mail->IsHTML(true);
            $mail->From = 'info@arxsmart.com';
            $mail->FromName = utf8_decode('ARX Bitácoras');
            foreach($punto["correos"] as $correo) { 
                $mail->AddAddress($correo["correo"]);
            }
            $mail->Subject = utf8_decode($title);
            $mail->Body =  '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Recuperacion Contraseña</title>
            </head>
            <body>
                <header>
                    <div width="100px" style="background-color:#303030;padding-bottom: 20px;padding-top: 20px" align="center">
                        <img src="'.URL_RESOURCES.'arxlogb.png" width="15%" alt="">
                    </div>
                </header>
                <div style="font-family:\Gill Sans\">
                    <h1 align="center" style="font-size:200%">&#161;Usuario Detectado!</h1>
                    <table width="452" border="0" align="center" cellpadding="0" cellspacing="0" height="80">
                            <tbody>
                            <tr>
                            <td colspan="3" height="21">
                            <table width="452" border="0" cellpadding="0" cellspacing="0">
                            <tbody>
                            <tr>
                            <td rowspan="2" valign="middle" bgcolor="303030" width="126" class="x_blanco8">
                            <div align="center"><i><b><font color="#FFFFFF">Información</font></b></i></div>
                            </td>
                            <td height="12" valign="top" width="319"></td>
                            </tr>
                            <tr>
                            <td bgcolor="303030" width="319" class="x_style1" style="color:#2684C2"></td>
                            </tr>
                            </tbody>
                            </table>
                            </td>
                            </tr>
                            <tr>
                            <td width="1" bgcolor="303030"></td>
                            <td width="450" height="80" valign="top">
                            <table width="90%" border="0" cellspacing="0" cellpadding="4" align="center">
                            <tbody>
                            <tr>
                            <td class="x_gris8" width="100%" valign="top" colspan="2">
                            <div align="justify"><font color="#666666">Notificaci&oacute;n electr&oacute;nica de usuario asignado a una categoría encontrado gracias a <span data-markjs="true" class="markiqmlgw9t4" style="background-color: yellow; color: black;">ARX Bitácora</span></font></div>
                            </td>
                            </tr>
                            <tr>
                            <td class="x_gris8" width="145" valign="top">
                            <div align="justify"><font color="#666666">Descripción </font></div>
                            </td>
                            <td class="x_negro8" width="244">' . $title . '</td>
                            </tr>
                            <tr>
                            <td class="x_gris8" width="100%" valign="top" colspan="2">
                            <div align="justify"><font color="#666666">Recuerde el cuidado especial de su informaci&oacute;n de acceso a <span data-markjs="true" class="markiqmlgw9t4" style="background-color: yellow; color: black;"></span> ARX, misma que por ning&uacute;n motivo debe ser compartida con terceros.</font></div>
                            </td>
                            </tr>
                            </tbody>
                            </table>
                            </td>
                            <td width="1" bgcolor="303030"></td>
                            </tr>
                            <tr>
                            <td colspan="3" height="1" bgcolor="303030"></td>
                            </tr>
                            </tbody>
                            </table>
                            <p align="center" style="padding-top:60px ; font-family: \'Gill Sans\'">Cordialmente,<br>
                            El Equipo de ARX<br>
                        <p align="center">Este mensaje de correo electrónico se ha generado autom&aacute;ticamente. Si necesita ayuda, vis&iacute;tenos
                        <a href="https://www.arxsmart.com" target="_blank" rel="noopener noreferrer" data-auth="NotApplicable">www.arxsmart.com</a></p>
                </div>
            </body>
            </html>';
            $mail->send();
        } catch (phpmailerException $e) {
            //echo $e->errorMessage(); //Pretty error messages from PHPMailer
            return false;
        } catch (Exception $e) {
            //echo $e->getMessage(); //Boring error messages from anything else!
            return false;
        }
        return true;
    }
}