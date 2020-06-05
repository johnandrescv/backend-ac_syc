<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 0);
//rename duplicates
function renameDuplicates($path, $file)
{   
    $fileName = pathinfo($path . $file, PATHINFO_FILENAME);
    $fileExtension = "." . pathinfo($path . $file, PATHINFO_EXTENSION);
    $returnValue = $fileName . $fileExtension;

    $copy = 1;
    while (file_exists($path . $returnValue)) {
        $returnValue = $fileName . '-arx-'. $copy . $fileExtension;
        $copy++;
    }
    return $returnValue;
}

//url base
$url = './api/uploads/api/uploads/'; 

//if exists
$uploaddir = "images/";
if (!file_exists($uploaddir))
    mkdir ($uploaddir, 0777, true);

//url
$file = basename($_FILES['filename']['name']);
if ($file == "")
    echo json_encode(array(
        "error" => true,
        "message" => "No se ha enviado imagen.",
    ));
$file1 = renameDuplicates($uploaddir, strtolower(basename($_FILES['filename']['name'])));
$uploadfile = $uploaddir . $file1;


$url1 = $url . $uploadfile;
if (move_uploaded_file($_FILES["filename"]["tmp_name"], $uploadfile)) {
	echo json_encode(array(
		'error' => false,
		'file' => $file1, 
	));
} else {
    echo json_encode(array(
        "error" => true,
        "message" => "Ha ocurrido un error inesperado.",
    ));
}
?>