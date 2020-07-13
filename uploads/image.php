<?php
    $name = $_GET['nombre'];
    $dirname = "media/Imagenes/";
    $image = glob($dirname.$name);
    echo '<img src="'.$image.'" /><br />';
?>