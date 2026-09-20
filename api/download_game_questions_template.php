<?php
/**
 * Download game question import template for teachers.
 */

session_start();
require_once '../includes/auth_check.php';
require_once '../config/database.php';
checkTeacherAuth();
$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_questions');

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Game Questions');

$headers = ['Question Text', 'Answer A', 'Answer B', 'Answer C', 'Answer D', 'Correct Answer'];
$sheet->fromArray($headers, null, 'A1');

$sheet->getStyle('A1:F1')->getFont()->setBold(true);
$sheet->getStyle('A1:F1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
$sheet->freezePane('A2');

$sheet->getColumnDimension('A')->setWidth(56);
$sheet->getColumnDimension('B')->setWidth(26);
$sheet->getColumnDimension('C')->setWidth(26);
$sheet->getColumnDimension('D')->setWidth(26);
$sheet->getColumnDimension('E')->setWidth(26);
$sheet->getColumnDimension('F')->setWidth(22);

$sheet->setCellValue('A2', 'What is science?');
$sheet->setCellValue('B2', 'A magic trick');
$sheet->setCellValue('C2', 'A way of understanding the world');
$sheet->setCellValue('D2', 'A type of game');
$sheet->setCellValue('E2', 'A television show');
$sheet->setCellValue('F2', 'B');

$sheet->getStyle('A2:F1000')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

for ($row = 2; $row <= 1000; $row++) {
    $validation = $sheet->getCell('F' . $row)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setErrorTitle('Invalid Correct Answer');
    $validation->setError('Use A, B, C, or D only.');
    $validation->setPromptTitle('Correct Answer');
    $validation->setPrompt('Choose A, B, C, or D.');
    $validation->setFormula1('"A,B,C,D"');
}

$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setSort(true);
$sheet->getProtection()->setInsertRows(true);
$sheet->getProtection()->setFormatCells(true);
$sheet->getProtection()->setPassword('atomix-template');

$filename = 'template_game_questions.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
