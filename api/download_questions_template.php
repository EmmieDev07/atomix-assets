<?php
/**
 * Download the protected question import template for teachers.
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
$sheet->setTitle('Questions Template');

$headers = ['Question Type', 'Question Text', 'Choice1', 'Choice2', 'Choice3', 'Choice4', 'Correct Choice'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:G1')->getFont()->setBold(true);
$sheet->getStyle('A1:G1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
$sheet->freezePane('A2');

$widths = ['A' => 18, 'B' => 56, 'C' => 24, 'D' => 24, 'E' => 24, 'F' => 24, 'G' => 20];
foreach ($widths as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

$sheet->setCellValue('A2', 'mcq');
$sheet->setCellValue('B2', 'What is 2+2?');
$sheet->setCellValue('C2', '3');
$sheet->setCellValue('D2', '4');
$sheet->setCellValue('E2', '5');
$sheet->setCellValue('F2', '6');
$sheet->setCellValue('G2', '4');
$sheet->setCellValue('A3', 'true_false');
$sheet->setCellValue('B3', 'Is the sky blue?');
$sheet->setCellValue('G3', 'true');
$sheet->getStyle('A2:G1000')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
$sheet->setCellValue('A4', 'short_answer');
$sheet->setCellValue('B4', 'What is the capital of France?');
$sheet->setCellValue('G4', 'Paris');

for ($row = 2; $row <= 1000; $row++) {
    $validation = $sheet->getCell('A' . $row)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setErrorTitle('Invalid Question Type');
    $validation->setError('Choose mcq, true_false, or short_answer.');
    $validation->setPromptTitle('Question Type');
    $validation->setPrompt('Select a question type from the dropdown.');
    $validation->setFormula1('"mcq,true_false,short_answer"');
}

$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setSort(true);
$sheet->getProtection()->setInsertRows(true);
$sheet->getProtection()->setFormatCells(true);
$sheet->getProtection()->setPassword('atomix-template');

$filename = 'questions_template.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
