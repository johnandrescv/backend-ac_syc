<?php
    $name = $_GET['nombre'];
    $dirname = "file:///media/Imagenes/".$name;
    echo '<img src="'.$dirname.'" /><br />';
?>