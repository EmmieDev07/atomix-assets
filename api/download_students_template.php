<?php
/**
 * Download a protected student import template.
 * - Locks header row
 * - Freezes header row
 * - Adds validation rules
 * - Removes password column (default is changeme)
 */

require_once '../includes/auth_check.php';
checkAdminAuth();

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Students Template');

// Headers (no password/email column)
$headers = ['First Name', 'Last Name', 'Gender'];
$sheet->fromArray($headers, null, 'A1');

// Header styling
$sheet->getStyle('A1:C1')->getFont()->setBold(true);
$sheet->getStyle('A1:C1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
$sheet->freezePane('A2');

// Auto width
foreach (range('A', 'C') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Unlock data entry area so users can edit rows but not headers
$sheet->getStyle('A2:C1000')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

// Gender list validation: Male/Female/Others
for ($row = 2; $row <= 1000; $row++) {
    $genderValidation = $sheet->getCell('C' . $row)->getDataValidation();
    $genderValidation->setType(DataValidation::TYPE_LIST);
    $genderValidation->setErrorStyle(DataValidation::STYLE_STOP);
    $genderValidation->setAllowBlank(false);
    $genderValidation->setShowInputMessage(true);
    $genderValidation->setShowErrorMessage(true);
    $genderValidation->setShowDropDown(true);
    $genderValidation->setErrorTitle('Invalid Gender');
    $genderValidation->setError('Please choose Male, Female, or Others.');
    $genderValidation->setPromptTitle('Gender');
    $genderValidation->setPrompt('Select one value from the dropdown.');
    $genderValidation->setFormula1('"Male,Female,Others"');
}

// Protect sheet to prevent accidental header edits while allowing data entry
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setSort(true);
$sheet->getProtection()->setInsertRows(true);
$sheet->getProtection()->setFormatCells(true);
$sheet->getProtection()->setPassword('atomix-template');

// Output
$filename = 'template_students.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
