<?php
/**
 * PHPExcel
 *
 * Copyright (c) 2006 - 2015 PHPExcel
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category   PHPExcel
 * @package    PHPExcel
 * @copyright  Copyright (c) 2006 - 2015 PHPExcel (http://www.codeplex.com/PHPExcel)
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt	LGPL
 * @version    ##VERSION##, ##DATE##
 */

/** Error reporting */
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
date_default_timezone_set('America/Guayaquil');
require_once '../include/Administracion.php';
$db = new DbHandler();
$admin = (isset($_GET['admin'])) ? $_GET['admin'] : false;
$acc = (isset($_GET['acc'])) ? $_GET['acc'] : false;
$tipo = (isset($_GET['tipo'])) ? $_GET['tipo'] : false;

$response = $db->getDownloadHistorialByDate($_GET['start'], $_GET['end'], $admin, $acc, $tipo);

if (PHP_SAPI == 'cli')
	die('This example should only be run from a Web Browser');

/** Include PHPExcel */
require_once  'Classes/PHPExcel.php';


// Create new PHPExcel object
$objPHPExcel = new PHPExcel();

// Add a drawing to the header
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddHeader('&L&G& Reporte de Usuarios');
// Set document properties
$objPHPExcel->getProperties()->setCreator("ANDEC")
							 ->setLastModifiedBy("ANDEC")
							 ->setTitle("Reporte de Usuarios")
							 ->setSubject("Office 2007 XLSX Test Document")
							 ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
							 ->setKeywords("office 2007 openxml php")
							 ->setCategory("Reporte de Usuarios");

$style = array(
        'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
        )
    );

$objPHPExcel->getDefaultStyle()->applyFromArray($style);
// Add some data
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Identificación')
            ->setCellValue('B1', 'Usuario')
            ->setCellValue('C1', 'Tipo')
            ->setCellValue('D1', 'Autorización')
            ->setCellValue('E1', 'Descripción')
            ->setCellValue('F1', 'Fecha Entrada')
            ->setCellValue('G1', 'Fecha Salida');
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(11.67);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(32.33);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(13.33);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(23.67);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(17.83);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(19.83);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(19.50);
$objPHPExcel->getActiveSheet()
    ->getStyle('A1:G1')
    ->getFill()
    ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
    ->getStartColor()
    ->setARGB('c8dcff');
$styleArray = array(
    'font'  => array(
        //'bold' => true,
        //'color' => array('rgb' => 'FFFFFF'),
        'size'  => 11,
    ),
    'borders' => array(
            'allborders' => array(
            'style' => PHPExcel_Style_Border::BORDER_THIN
        )
    ),
);
$objPHPExcel->getActiveSheet()->getStyle('A1:G1')->applyFromArray($styleArray);
$valorprin = 2;
foreach($response as $row){
    $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A'.$valorprin, $row["usuario"]["dni"])
            ->setCellValue('B'.$valorprin, $row["usuario"]["nombres"])
            ->setCellValue('C'.$valorprin, $row["usuario"]["tipo"]["nombres"])
            ->setCellValue('D'.$valorprin, ($row["autorizacion"]) ? $row["autorizacion"]["nombres"] : '')
            ->setCellValue('E'.$valorprin, $row["descripcion"])
            ->setCellValue('F'.$valorprin, $row["fecha_entrada"])
            ->setCellValue('G'.$valorprin, $row["fecha_salida"]);
    $objPHPExcel->getActiveSheet()->getStyle('A'.$valorprin.':G'.$valorprin)->applyFromArray($styleArray);
    $valorprin++;
}

// Rename worksheet
$objPHPExcel->getActiveSheet()->setTitle('Reporte de Usuarios');


// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$objPHPExcel->setActiveSheetIndex(0);


// Redirect output to a client’s web browser (Excel2007)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte Historial.xlsx"');
header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
header('Cache-Control: max-age=1');

// If you're serving to IE over SSL, then the following may be needed
header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header ('Pragma: public'); // HTTP/1.0

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save('php://output');
exit;
