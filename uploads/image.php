<?php
    $name = $_GET['nombre'];
    $dirname = "file:///media/Imagenes/";
    $image = glob($dirname.$name);
    echo '<img src="'.$image.'" /><br />';
    echo $image;
?>