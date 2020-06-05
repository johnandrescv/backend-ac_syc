<?php

trait Reportes {

    /* 
    ------------- 
    ---------- DASHBOARD
    -------------
    */

    /* 
    ------------- 
    ---------- DIARIO
    -------------
    */

    public function getCountTotalRegistrosByDay($salida) {
        $response = array();
        $salida = $salida? "AND !(fecha_salida is NULL) " : "";
        $stmt= $this->conn->prepare("SELECT COUNT(*) as total FROM historial WHERE CAST(fecha_creacion as DATE) = CAST(NOW() as DATE) $salida");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $row['total'];
        } else return 0;
    }

    public function getRegistroUsuarioByDay($tipo_usuario) {
        $response = array();
        $stmt= $this->conn->prepare("SELECT COUNT(h.id_historial) as total FROM historial h, usuarios u WHERE h.id_usuario = u.id_usuario AND u.id_tipo = ? AND CAST(h.fecha_creacion as DATE) = CAST(NOW() as DATE)");
        $stmt->bind_param("s",$tipo_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $row['total'];
        } else return 0;
    }

    /* 
    ------------- 
    ---------- VISITAR POR DÍA
    -------------
    */

    public function  getTotalVisitasByDay() {
        $response = array();
        $tipos_usuarios = $this->getTipos();
        $local = array();
        $stmt= $this->conn->prepare('SELECT COUNT(*) as total, DATE_FORMAT(CAST(fecha_creacion as DATETIME),  "%d-%m-%Y %H") as fecha FROM historial WHERE DATE_FORMAT(CAST(fecha_creacion as DATE),  "%d-%m-%Y") = DATE_FORMAT(CAST(NOW() as DATE),  "%d-%m-%Y") GROUP BY DATE_FORMAT(CAST(fecha_creacion as DATETIME),  "%d-%m-%Y %H") ORDER BY DATE_FORMAT(CAST(fecha_creacion as DATETIME),  "%d-%m-%Y %H") desc');
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = array(
                'total' => $row["total"],
                'fecha' => $row["fecha"],
            );
            $response[] = $res;
        }
        return $response;
    }

    /* 
    ------------- 
    ---------- TIPOS DE USUARIO POR DÍA
    -------------
    */

    public function getTotalVisitasByTipoUsuario() {
        $response = array();
        $stmt= $this->conn->prepare('SELECT COUNT(h.id_historial) as total, u.id_tipo, CAST(NOW() AS DATE) as fecha FROM historial h, usuarios u WHERE h.id_usuario = u.id_usuario AND DATE_FORMAT(CAST(h.fecha_creacion as DATE),  "%d-%m-%Y") = DATE_FORMAT(CAST(NOW() as DATE),  "%d-%m-%Y") GROUP BY u.id_tipo ORDER BY u.id_tipo;');
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $tipo = $this->getTipoById($row["id_tipo"]);
            $res = array(
                'total' => $row["total"],
                'tipo' => $tipo["nombres"],
                'fecha' => $row["fecha"],
            );
			$response[] = $res;
        }
        return $response;
    }

    /* 
    ------------- 
    ---------- MENSUAL
    -------------
    */
    
    public function getCountTotalRegistrosByMensual($salida) {
        $response = array();
        $salida = $salida? "AND !(fecha_salida is NULL) " : "";
        $stmt= $this->conn->prepare("SELECT COUNT(*) as total FROM historial WHERE DATE_FORMAT(CAST(fecha_creacion as DATE),  '%m-%Y') = DATE_FORMAT(CAST(NOW() as DATE), '%m-%Y') $salida");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $row['total'];
        } else return 0;
    }

    public function getRegistroUsuarioByMensual($tipo_usuario) {
        $response = array();
        $stmt= $this->conn->prepare("SELECT COUNT(h.id_historial) as total FROM historial h, usuarios u WHERE h.id_usuario = u.id_usuario AND u.id_tipo = ? AND DATE_FORMAT(CAST(h.fecha_creacion as DATE),  '%m-%Y') = DATE_FORMAT(CAST(NOW() as DATE), '%m-%Y')");
        $stmt->bind_param("s",$tipo_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            return $row['total'];
        } else return 0;
    }

    /* 
    ------------- 
    ---------- VISITAR POR MES
    -------------
    */

    public function getTotalVisitasByMonth() {
        $response = array();
        $stmt= $this->conn->prepare('SELECT COUNT(*) as total, DATE_FORMAT(CAST(fecha_creacion as DATE),  "%d-%m-%Y") as fecha FROM historial WHERE DATE_FORMAT(CAST(fecha_creacion as DATE),  "%m-%Y") = DATE_FORMAT(CAST(NOW() as DATE),  "%m-%Y") GROUP BY DATE_FORMAT(CAST(fecha_creacion as DATE),  "%d-%m-%Y") ORDER BY DATE_FORMAT(CAST(fecha_creacion as DATE),  "%d-%m-%Y") desc;');
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $res = array(
                'total' => $row["total"],
                'fecha' => $row["fecha"],
            );
			$response[] = $res;
        }
        return $response;
    }

        /* 
    ------------- 
    ---------- TIPOS DE USUARIO MENSUAL
    -------------
    */

    public function getTotalVisitasMensualByTipoUsuario() {
        $response = array();
        $stmt= $this->conn->prepare('SELECT COUNT(h.id_historial) as total, u.id_tipo, DATE_FORMAT(CAST(NOW() AS DATE), "%m-%Y") as fecha FROM historial h, usuarios u WHERE h.id_usuario = u.id_usuario AND DATE_FORMAT(CAST(h.fecha_creacion as DATE),  "%m-%Y") = DATE_FORMAT(CAST(NOW() as DATE),  "%m-%Y") GROUP BY u.id_tipo ORDER BY u.id_tipo;');
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $tipo = $this->getTipoById($row["id_tipo"]);
            $res = array(
                'total' => $row["total"],
                'tipo' => $tipo["nombres"],
                'fecha' => $row["fecha"],
            );
			$response[] = $res;
        }
        return $response;
    }
    
}

?>
