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
$file1 = renameDuplicates($uploaddir, strtolower(basename($_FILES['filename1']['name'])));
$uploadfile1 = $uploaddir . $file1;
$file2 = renameDuplicates($uploaddir, strtolower(basename($_FILES['filename2']['name'])));
$uploadfile2 = $uploaddir . $file2;
$file3 = renameDuplicates($uploaddir, strtolower(basename($_FILES['filename3']['name'])));
$uploadfile3 = $uploaddir . $file3;

$url1 = $url . $uploadfile1;
$url2 = $url . $uploadfile2;
$url3 = $url . $uploadfile3;
if (move_uploaded_file($_FILES["filename1"]["tmp_name"], $uploadfile1) && move_uploaded_file($_FILES["filename2"]["tmp_name"], $uploadfile2) && move_uploaded_file($_FILES["filename3"]["tmp_name"], $uploadfile3)) {
	echo json_encode(array(
		'error' => false,
		'file1' => $file1, 
		'file2' => $file2, 
		'file3' => $file3, 
	));
} else {
    echo json_encode(array("error" => "true", "image" => $file1));
}
?>