<?php
    $name = $_GET['nombre'];
    $dirname = "file:///media/Imagenes/";
    $dir = "file:///media/Imagenes/".$name;
    $image = glob($dirname.$name);
    echo '<img src="'.$dir.'" /><br />';
    echo $dir;
    var_dump($image);
?>