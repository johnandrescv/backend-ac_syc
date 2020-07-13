<?php
    header('Content-Type: image/jpeg'); 
    $name = $_GET['nombre'];
    readfile("file:///media/Imagenes/".$name);
?>