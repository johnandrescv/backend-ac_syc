<?php

trait Garitas {

    public function getGuardiaById($id, $detailed = false) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT * from guardias where id_guardia = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $response = array(
                'id_guardia' => $row['id_guardia'],
                'dni' => $row['dni'],
                'nombres' => $row['nombres'],
                'id_empresa' => $row['id_empresa'],
                'fecha_creacion' => $row['fecha_creacion'],
            );
            if($detailed){
                $response['empresa'] = $this->getEmpresaById($row['id_empresa']);
                $response['api_key'] = $row['api_key'];
            }
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function getGuardiaByApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id_guardia FROM guardias WHERE api_key = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getGuardiaById($row["id_guardia"]);
        } else return RECORD_DOES_NOT_EXIST;
    }

    public function isValidApiKeyGuardia($api_key) {
        $stmt = $this->conn->prepare("SELECT id_guardia from guardias WHERE api_key = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function checkLoginGuardias($dni) {
        $stmt = $this->conn->prepare("SELECT id_guardia FROM guardias WHERE dni = ? AND estado = '" . ESTADO_ACTIVO . "'");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            $stmt->close();
            return $id;
        } else {
            $stmt->close();
            return FALSE;
        }
    }
    /* 
    ------------- 
    ---------- CREAR USUARIOS
    -------------
    */

    public function getUsuarioById($id, $detailed = false, $id_empresa = false) {
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
                'fecha_creacion' => $row['fecha_creacion'],
            );
            if($detailed){
                $response['notificacion'] = $this->getUsuarioCategoriaByEmpresa($id, $id_empresa);
            }
            return $response;
        } else return RECORD_DOES_NOT_EXIST;
    }

    private function isUsuariosExists($dni) {
        $stmt = $this->conn->prepare("SELECT id_usuario from usuarios WHERE dni = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function createUsuario($dni, $nombres) {
        if (!$this->isUsuariosExists($dni)) {
            $response = array();
            $stmt = $this->conn->prepare("INSERT INTO usuarios(dni, nombres) values (?,?)");
            $stmt->bind_param("ss", $dni, $nombres);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                $new_id = $this->conn->insert_id;
                return array(OPERATION_SUCCESSFUL, $new_id );
            } else {
                return array(OPERATION_FAILED);
            }
        } else return array(RECORD_DUPLICATED);
    }

    public function getUsuarioByDNI($dni) {
        $stmt = $this->conn->prepare("SELECT id_usuario FROM usuarios WHERE dni = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getUsuarioById($row["id_usuario"]);
        } else return RECORD_DOES_NOT_EXIST;
    }

    /* 
    ------------- 
    ---------- HISTORIAL
    -------------
    */

    public function searchUsuario($dni, $id_empresa, $id_punto) {
        $punto = $this->getPuntosById($id_punto);
        $stmt = $this->conn->prepare("SELECT id_usuario FROM usuarios WHERE dni = ? AND estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $usuario = $this->getUsuarioById($row["id_usuario"], true, $id_empresa);
            if($usuario["notificacion"]["categoria"] != false){
                $title = $usuario["nombres"].": ".$usuario["notificacion"]["categoria"]["nombre"].". Detectado en ".$punto["nombres"];
                $this->createNotificacion($title, $id_empresa, 1);
                $this->sendCategoriaEmail($id_punto, $title);
            }
            return $usuario;

        } else return false;
    }

    public function createHistorial($id_guardia, $dni, $nombres, $id_punto, $id_empresa, $detalle, $placa, $salida) {
        $usuario = $this->getUsuarioByDNI($dni);
        if($usuario == RECORD_DOES_NOT_EXIST){
            $create = $this->createUsuario($dni, $nombres);
            if($create[0] == OPERATION_SUCCESSFUL)
                return $this->createHistorialRequest($id_guardia, $create[1], $id_punto, $id_empresa, $detalle, $placa, $salida);
            else
                return OPERATION_FAILED;
        }else{
            return $this->createHistorialRequest($id_guardia, $usuario['id_usuario'], $id_punto, $id_empresa, $detalle, $placa, $salida);
        }
        
    }

    public function createHistorialRequest($id_guardia, $id_usuario, $id_punto, $id_empresa, $detalle, $placa, $salida){
        $response = array();
        $stmt = $this->conn->prepare("INSERT INTO historial(id_guardia, id_usuario, id_punto, id_empresa, detalle, placa, salida) values (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssss", $id_guardia, $id_usuario, $id_punto, $id_empresa, $detalle, $placa, $salida);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return OPERATION_SUCCESSFUL;
        } else {
            return OPERATION_FAILED;
        }
    }

    public function getHistorialByGuardia($id_guardia) {
        $response = array();
        $stmt = $this->conn->prepare("SELECT id_historial FROM historial WHERE id_guardia = ? AND CAST(fecha_creacion AS DATE) BETWEEN CAST(NOW() AS DATE) AND CAST(NOW() + INTERVAL 1 DAY AS DATE) AND estado != '" . ESTADO_ELIMINADO . "' ORDER BY fecha_creacion desc");
        $stmt->bind_param("s", $id_guardia);
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
    ---------- CATEGORIAS USUARIOS
    -------------
    */

    public function getUsuarioCategoriaByEmpresa($id_usuario, $id_empresa) {
        $stmt = $this->conn->prepare("SELECT u.id_categoria_usuario from categorias_usuarios u, categorias c WHERE c.id_categoria = u.id_categoria AND c.id_empresa = ? AND u.id_usuario = ? AND u.estado = '" . ESTADO_ACTIVO . "'");
        $stmt->bind_param("ss", $id_empresa, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $this->getCategoriasUsuariosById($row["id_categoria_usuario"]);
        } else return false;
    }

    private function isCategoriaUsuarioExists($id_usuario, $id_empresa) {
        $stmt = $this->conn->prepare("SELECT u.id_categoria_usuario from categorias_usuarios u, categorias c WHERE c.id_categoria = u.id_categoria AND c.id_empresa = ? AND u.id_usuario = ? AND u.estado != '" . ESTADO_ELIMINADO . "'");
        $stmt->bind_param("ss", $id_empresa, $id_usuario);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function createCategoriaHistorial($id_categoria, $id_usuario, $id_guardia, $fecha_limite, $estado, $id_empresa) {
        if(!$this->isCategoriaUsuarioExists($id_usuario, $id_empresa)){
            $response = array();
            if($fecha_limite == false) $fecha_limite = date('Y-m-d');
            $stmt = $this->conn->prepare("INSERT INTO categorias_usuarios(id_categoria, id_usuario, id_guardia, fecha_limite, estado) values (?,?,?,?,?)");
            $stmt->bind_param("sssss", $id_categoria, $id_usuario, $id_guardia, $fecha_limite, $estado);
            $result = $stmt->execute();
            $stmt->close();
    
            if ($result) {
                if($estado != ESTADO_ACTIVO)
                    $this->createNotificacion('Nueva solicitud de asignación de usuario', $id_empresa, 2);
                return OPERATION_SUCCESSFUL;
            } else {
                return OPERATION_FAILED;
            }
        }else
            return RECORD_DUPLICATED;
    }
}

?>