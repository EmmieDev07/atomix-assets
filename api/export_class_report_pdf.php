<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth_check.php';

/**
 * Escapes special characters for PDF text strings and strips non-ASCII symbols
 */
function pdfEscapeText($text) {
    $text = (string) $text;
    if (function_exists('mb_convert_encoding')) {
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return preg_replace('/[^\x20-\x7E]/', '?', $text);
}

/**
 * Pure-PHP Professional Single-Page-Per-Student PDF Engine
 */
function generateStructuredPdf($classInfo, $students, $chapters, $pretestMap, $stageAssessmentMap, $studentExamsMap, $popQuizMap, $uniqueQuizzes, $gameStatsMap, $totalSystemStages) {
    $pageWidth = 595;
    $pageHeight = 842;
    $margin = 40;
    $usableWidth = $pageWidth - ($margin * 2);

    $pageContents = [];

    foreach ($students as $sIdx => $student) {
        $content = "";
        $sid = (int)$student['student_id'];
        $sName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $sEmail = $student['email'] ?? 'N/A';
        $sGender = $student['gender'] ?? 'N/A';
        $completedStagesCount = (int)($gameStatsMap[$sid] ?? 0);
        $overallProgressPct = $totalSystemStages > 0 ? round(($completedStagesCount / $totalSystemStages) * 100, 1) : 0;

        // --- 1. HEADER BANNER ---
        $content .= "0.12 0.16 0.23 rg\n";
        $content .= "0 " . ($pageHeight - 55) . " {$pageWidth} 55 re f\n";

        $content .= "BT\n/F2 14 Tf\n1 1 1 rg\n{$margin} " . ($pageHeight - 26) . " Td\n";
        $content .= "(" . pdfEscapeText("ATOMIX — STUDENT PROGRESS REPORT") . ") Tj\nET\n";

        $subtitle = "Class: " . ($classInfo['class_name'] ?? 'N/A') . "  |  School Year: " . ($classInfo['school_year'] ?? 'N/A') . "  |  Generated: " . date('M d, Y');
        $content .= "BT\n/F1 8.5 Tf\n0.80 0.85 0.90 rg\n{$margin} " . ($pageHeight - 42) . " Td\n";
        $content .= "(" . pdfEscapeText($subtitle) . ") Tj\nET\n";

        // --- 2. STUDENT PROFILE & OVERALL SUMMARY BOX ---
        $y = $pageHeight - 75;

        $content .= "0.96 0.97 0.98 rg\n";
        $content .= "{$margin} " . ($y - 42) . " {$usableWidth} 42 re f\n";
        $content .= "0.80 0.83 0.88 RG\n0.5 w\n";
        $content .= "{$margin} " . ($y - 42) . " {$usableWidth} 42 re S\n";

        // Profile Line
        $content .= "BT\n/F2 10 Tf\n0.12 0.16 0.23 rg\n";
        $content .= "1 0 0 1 " . ($margin + 12) . " " . ($y - 16) . " Tm\n";
        $content .= "(" . pdfEscapeText("Student: " . strtoupper($sName)) . ") Tj\nET\n";

        $content .= "BT\n/F1 8.5 Tf\n0.30 0.35 0.40 rg\n";
        $content .= "1 0 0 1 " . ($margin + 300) . " " . ($y - 16) . " Tm\n";
        $content .= "(" . pdfEscapeText("Email: " . $sEmail . "  |  Gender: " . $sGender) . ") Tj\nET\n";

        // Metric Line
        $metrics = "Stages Completed: {$completedStagesCount} / {$totalSystemStages}    |    Overall Progress: {$overallProgressPct}%";
        $content .= "BT\n/F2 9 Tf\n0.18 0.45 0.71 rg\n";
        $content .= "1 0 0 1 " . ($margin + 12) . " " . ($y - 32) . " Tm\n";
        $content .= "(" . pdfEscapeText($metrics) . ") Tj\nET\n";

        $y -= 54;

        // Table Helper Function
        $drawTableRow = function(&$content, &$y, $col1, $col2, $col3, $isHeader = false, $bgColor = null) use ($margin, $usableWidth) {
            $rowHeight = $isHeader ? 17 : 14;

            if ($bgColor) {
                $content .= "{$bgColor} rg\n";
                $content .= "{$margin} " . ($y - $rowHeight) . " {$usableWidth} {$rowHeight} re f\n";
            }

            // Outline
            $content .= "0.85 0.88 0.90 RG\n0.4 w\n";
            $content .= "{$margin} " . ($y - $rowHeight) . " {$usableWidth} {$rowHeight} re S\n";

            // Column Dividers
            $c1Width = 300;
            $c2Width = 110;

            $content .= "1 0 0 1 " . ($margin + $c1Width) . " " . ($y - $rowHeight) . " m " . ($margin + $c1Width) . " {$y} l S\n";
            $content .= "1 0 0 1 " . ($margin + $c1Width + $c2Width) . " " . ($y - $rowHeight) . " m " . ($margin + $c1Width + $c2Width) . " {$y} l S\n";

            // Text Rendering
            $font = $isHeader ? "/F2 8.5" : "/F1 7.5";
            $textColor = $isHeader ? "1 1 1" : "0.15 0.18 0.22";

            $cleanCol1 = strlen($col1) > 52 ? substr($col1, 0, 49) . '...' : $col1;

            $content .= "BT\n{$font} Tf\n{$textColor} rg\n";
            $content .= "1 0 0 1 " . ($margin + 8) . " " . ($y - ($isHeader ? 12 : 10)) . " Tm\n";
            $content .= "(" . pdfEscapeText($cleanCol1) . ") Tj\nET\n";

            $content .= "BT\n{$font} Tf\n{$textColor} rg\n";
            $content .= "1 0 0 1 " . ($margin + $c1Width + 10) . " " . ($y - ($isHeader ? 12 : 10)) . " Tm\n";
            $content .= "(" . pdfEscapeText($col2) . ") Tj\nET\n";

            $content .= "BT\n{$font} Tf\n{$textColor} rg\n";
            $content .= "1 0 0 1 " . ($margin + $c1Width + $c2Width + 10) . " " . ($y - ($isHeader ? 12 : 10)) . " Tm\n";
            $content .= "(" . pdfEscapeText($col3) . ") Tj\nET\n";

            $y -= $rowHeight;
        };

        // --- 3. CURRICULUM ASSESSMENTS TABLE ---
        $content .= "BT\n/F2 9.5 Tf\n0.12 0.16 0.23 rg\n";
        $content .= "1 0 0 1 {$margin} " . ($y - 2) . " Tm\n";
        $content .= "(" . pdfEscapeText("CURRICULUM & STAGE ASSESSMENTS") . ") Tj\nET\n";
        $y -= 10;

        $drawTableRow($content, $y, "ASSESSMENT NAME / LESSON TITLE", "TYPE", "SCORE", true, "0.12 0.16 0.23");

        $toggle = false;
        foreach ($chapters as $chId => $chInfo) {
            $chNum = $chInfo['num'];
            $chTitle = "Chapter {$chNum}: " . $chInfo['title'];

            // Pre-test
            $preData = $pretestMap[$sid][$chId] ?? null;
            $preText = "-";
            if ($preData && $preData['score'] !== null) {
                $preText = (int)$preData['score'] . " / " . (int)($preData['total_questions'] ?? 10);
            }
            $bg = $toggle ? "0.96 0.97 0.98" : "1.0 1.0 1.0";
            $drawTableRow($content, $y, $chTitle, "Pre-Test", $preText, false, $bg);
            $toggle = !$toggle;

            // Stage Lessons
            foreach ($chInfo['stages'] as $stgIdx => $stg) {
                $stgId = $stg['stage_id'];
                $stgData = $stageAssessmentMap[$sid][$stgId] ?? null;
                $stgText = "-";
                if ($stgData && $stgData['score'] !== null) {
                    $stgText = (int)$stgData['score'] . " / " . (int)($stgData['total_questions'] ?? 10);
                }

                $lessonName = !empty($stg['stage_name']) ? $stg['stage_name'] : ("Lesson " . ($stgIdx + 1));
                $stgLabel = "   - " . $lessonName;

                $bg = $toggle ? "0.96 0.97 0.98" : "1.0 1.0 1.0";
                $drawTableRow($content, $y, $stgLabel, "Stage Post-Test", $stgText, false, $bg);
                $toggle = !$toggle;
            }
        }

        $y -= 10;

        // --- 4. EXAM RESULTS TABLE ---
        $exams = $studentExamsMap[$sid] ?? [];
        $content .= "BT\n/F2 9.5 Tf\n0.12 0.16 0.23 rg\n";
        $content .= "1 0 0 1 {$margin} " . ($y - 2) . " Tm\n";
        $content .= "(" . pdfEscapeText("EXAM RESULTS") . ") Tj\nET\n";
        $y -= 10;

        $drawTableRow($content, $y, "EXAM TITLE", "SUBMITTED", "SCORE", true, "0.12 0.16 0.23");

        if (!empty($exams)) {
            $toggle = false;
            foreach ($exams as $ex) {
                $exTitle = !empty($ex['exam_title']) ? $ex['exam_title'] : 'Untitled Exam';
                $exText = "-";
                if (isset($ex['score']) && $ex['score'] !== null) {
                    $scoreVal = (int)$ex['score'];
                    $totalVal = (int)($ex['total_questions'] ?? 0);
                    $exText = $totalVal > 0 ? "{$scoreVal} / {$totalVal}" : "{$scoreVal}";
                }
                $subDate = !empty($ex['submitted_at']) ? date('m/d/Y', strtotime($ex['submitted_at'])) : 'Submitted';
                $bg = $toggle ? "0.96 0.97 0.98" : "1.0 1.0 1.0";
                $drawTableRow($content, $y, $exTitle, $subDate, $exText, false, $bg);
                $toggle = !$toggle;
            }
        } else {
            $drawTableRow($content, $y, "No Exam Results Recorded", "-", "-", false, "1.0 1.0 1.0");
        }

        $y -= 10;

        // --- 5. DYNAMIC DEPLOYED QUIZZES TABLE ---
        if (!empty($uniqueQuizzes)) {
            $content .= "BT\n/F2 9.5 Tf\n0.12 0.16 0.23 rg\n";
            $content .= "1 0 0 1 {$margin} " . ($y - 2) . " Tm\n";
            $content .= "(" . pdfEscapeText("DEPLOYED CLASS QUIZZES") . ") Tj\nET\n";
            $y -= 10;

            $drawTableRow($content, $y, "QUIZ TITLE", "CATEGORY", "SCORE", true, "0.12 0.16 0.23");

            $toggle = false;
            foreach ($uniqueQuizzes as $qId => $qTitle) {
                $qData = $popQuizMap[$sid][$qId] ?? null;
                $qText = "-";
                if ($qData && $qData['score'] !== null) {
                    $qText = (int)$qData['score'] . " / " . (int)($qData['total_questions'] ?? 10);
                }
                $bg = $toggle ? "0.96 0.97 0.98" : "1.0 1.0 1.0";
                $drawTableRow($content, $y, $qTitle, "Class Quiz", $qText, false, $bg);
                $toggle = !$toggle;
            }
        }

        $pageContents[] = $content;
    }

    // --- 6. ASSEMBLE MULTI-PAGE BINARY PDF ---
    $numPages = count($pageContents);
    $objects = [];
    
    $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

    $kidsRef = [];
    for ($i = 0; $i < $numPages; $i++) {
        $kidsRef[] = (3 + ($i * 2)) . " 0 R";
    }
    $objects[2] = "2 0 obj\n<< /Type /Pages /Count {$numPages} /Kids [" . implode(' ', $kidsRef) . "] >>\nendobj\n";

    $objIndex = 3;
    foreach ($pageContents as $pContent) {
        $pageObjId = $objIndex;
        $streamObjId = $objIndex + 1;

        $objects[$pageObjId] = "{$pageObjId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 " . (3 + ($numPages * 2)) . " 0 R /F2 " . (4 + ($numPages * 2)) . " 0 R >> >> /Contents {$streamObjId} 0 R >>\nendobj\n";
        $objects[$streamObjId] = "{$streamObjId} 0 obj\n<< /Length " . strlen($pContent) . " >>\nstream\n" . $pContent . "endstream\nendobj\n";

        $objIndex += 2;
    }

    $f1Id = $objIndex;
    $f2Id = $objIndex + 1;
    $objects[$f1Id] = "{$f1Id} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[$f2Id] = "{$f2Id} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    $totalObjs = count($objects);

    ksort($objects);
    foreach ($objects as $id => $obj) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . ($totalObjs + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $totalObjs; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

try {
    checkTeacherAuth();

    $db = Database::getInstance()->getConnection();
    $teacherId = (int) ($_SESSION['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        throw new Exception('Unauthorized teacher session.');
    }

    $classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
    if ($classId <= 0) {
        throw new Exception('Class ID is required.');
    }

    // 1. Class Information
    $classStmt = $db->prepare(
        "SELECT c.class_id, c.class_name, sy.label as school_year
         FROM classes c
         LEFT JOIN school_year sy ON c.sy_id = sy.sy_id
         WHERE c.class_id = ? AND c.teacher_id = ?
         LIMIT 1"
    );
    $classStmt->execute([$classId, $teacherId]);
    $classInfo = $classStmt->fetch(PDO::FETCH_ASSOC);
    if (!$classInfo) {
        throw new Exception('Class not found or access denied.');
    }

    // 2. Fetch Students
    $studentsStmt = $db->prepare(
        "SELECT DISTINCT s.student_id, s.first_name, s.last_name, s.gender, u.email, u.status
         FROM class_students cs
         JOIN students s ON cs.student_id = s.student_id
         JOIN users u ON s.user_id = u.user_id
         WHERE cs.class_id = ?
         ORDER BY s.last_name ASC, s.first_name ASC"
    );
    $studentsStmt->execute([$classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        throw new Exception('No students found in this class.');
    }

    $studentIds = array_column($students, 'student_id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    // 3. Fetch Chapters & Stages Hierarchy
    $stagesSql = "
        SELECT s.stage_id, s.chapter_id, c.chapter_title, s.stage_name
        FROM stages s
        LEFT JOIN chapters c ON s.chapter_id = c.chapter_id
        ORDER BY c.chapter_order ASC, s.stage_order ASC
    ";
    $stagesStmt = $db->prepare($stagesSql);
    $stagesStmt->execute();
    $allStagesList = $stagesStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalSystemStages = count($allStagesList);

    $chapters = [];
    $chapterCounter = 1;
    foreach ($allStagesList as $stg) {
        $chId = $stg['chapter_id'] ?? 0;
        if (!isset($chapters[$chId])) {
            $chapters[$chId] = [
                'chapter_id' => $chId,
                'num'        => $chapterCounter++,
                'title'      => trim((string)($stg['chapter_title'] ?? '')) ?: ('Chapter ' . $chapterCounter),
                'stages'     => []
            ];
        }
        $chapters[$chId]['stages'][] = $stg;
    }

    // 4. Pre-Tests
    $pretestMap = [];
    try {
        $preSql = "
            SELECT spr.student_id, cp.chapter_id, spr.score, spr.total_questions
            FROM student_pretest_results spr
            JOIN chapter_pretests cp ON spr.pretest_id = cp.pretest_id
            WHERE spr.student_id IN ($placeholders)
            AND spr.attempted_at = (
                SELECT MAX(spr2.attempted_at)
                FROM student_pretest_results spr2
                WHERE spr2.pretest_id = spr.pretest_id AND spr2.student_id = spr.student_id
            )
        ";
        $preStmt = $db->prepare($preSql);
        $preStmt->execute($studentIds);
        while ($row = $preStmt->fetch(PDO::FETCH_ASSOC)) {
            $pretestMap[$row['student_id']][$row['chapter_id']] = $row;
        }
    } catch (Throwable $e) {}

    // 5. Stage Post-Tests
    $stageAssessmentMap = [];
    try {
        $stageAssessmentSql = "
            SELECT ar.student_id, ar.stage_id, ar.score, ar.total_questions
            FROM student_assessment_results ar
            WHERE ar.student_id IN ($placeholders) AND ar.assessment_type = 'stage'
            AND ar.attempted_at = (
                SELECT MAX(ar2.attempted_at)
                FROM student_assessment_results ar2
                WHERE ar2.student_id = ar.student_id 
                  AND ar2.stage_id = ar.stage_id 
                  AND ar2.assessment_type = 'stage'
            )
        ";
        $stgStmt = $db->prepare($stageAssessmentSql);
        $stgStmt->execute($studentIds);
        while ($row = $stgStmt->fetch(PDO::FETCH_ASSOC)) {
            $stageAssessmentMap[$row['student_id']][$row['stage_id']] = $row;
        }
    } catch (Throwable $e) {}

    // 6. Direct Exam Query from `exam_results` and `exams` tables
    $studentExamsMap = [];
    try {
        $examSql = "
            SELECT er.student_id,
                   er.score,
                   COALESCE(er.total_questions, e.total_questions, 0) AS total_questions,
                   COALESCE(e.exam_title, e.title, 'Exam') AS exam_title,
                   COALESCE(er.submitted_at, er.created_at, er.attempted_at) AS submitted_at
            FROM exam_results er
            LEFT JOIN exams e ON er.exam_id = e.exam_id
            WHERE er.student_id IN ($placeholders)
            ORDER BY er.result_id DESC
        ";
        $examStmt = $db->prepare($examSql);
        $examStmt->execute($studentIds);
        while ($ex = $examStmt->fetch(PDO::FETCH_ASSOC)) {
            $studentExamsMap[$ex['student_id']][] = $ex;
        }
    } catch (Throwable $e) {
        // Fallback if exam_id column naming differs slightly
        try {
            $examFallbackSql = "
                SELECT er.student_id,
                       er.score,
                       COALESCE(er.total_questions, 0) AS total_questions,
                       'Summative Exam' AS exam_title,
                       NOW() AS submitted_at
                FROM exam_results er
                WHERE er.student_id IN ($placeholders)
            ";
            $examStmt = $db->prepare($examFallbackSql);
            $examStmt->execute($studentIds);
            while ($ex = $examStmt->fetch(PDO::FETCH_ASSOC)) {
                $studentExamsMap[$ex['student_id']][] = $ex;
            }
        } catch (Throwable $e2) {}
    }

    // 7. Dynamic Quizzes
    $popQuizMap = [];
    $uniqueQuizzes = [];
    try {
        $quizListSql = "
            SELECT DISTINCT q.quiz_id, q.quiz_title
            FROM quizzes q
            WHERE q.class_id = ?
            ORDER BY q.quiz_id ASC
        ";
        $quizListStmt = $db->prepare($quizListSql);
        $quizListStmt->execute([$classId]);
        while ($qRow = $quizListStmt->fetch(PDO::FETCH_ASSOC)) {
            $qid = $qRow['quiz_id'];
            $uniqueQuizzes[$qid] = !empty($qRow['quiz_title']) ? $qRow['quiz_title'] : "Quiz #{$qid}";
        }

        if (!empty($uniqueQuizzes)) {
            $params = array_merge($studentIds, [$classId]);

            $sqSql = "
                SELECT sq.student_id, sq.quiz_id, sq.score, 
                       COALESCE(q.total_score, 10) as total_questions
                FROM student_quizzes sq
                JOIN quizzes q ON sq.quiz_id = q.quiz_id
                WHERE sq.student_id IN ($placeholders) AND q.class_id = ?
            ";
            $sqStmt = $db->prepare($sqSql);
            $sqStmt->execute($params);
            while ($q = $sqStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($q['score'] !== null) {
                    $popQuizMap[$q['student_id']][$q['quiz_id']] = $q;
                }
            }

            $qsSql = "
                SELECT qs.student_id, qs.quiz_id, qs.score, 
                       COALESCE(q.total_score, 10) as total_questions
                FROM quiz_sessions qs
                JOIN quizzes q ON qs.quiz_id = q.quiz_id
                WHERE qs.student_id IN ($placeholders) AND q.class_id = ?
                  AND qs.score IS NOT NULL
                ORDER BY qs.session_id DESC
            ";
            $qsStmt = $db->prepare($qsSql);
            $qsStmt->execute($params);
            while ($q = $qsStmt->fetch(PDO::FETCH_ASSOC)) {
                $sid = $q['student_id'];
                $qid = $q['quiz_id'];
                if (!isset($popQuizMap[$sid][$qid])) {
                    $popQuizMap[$sid][$qid] = $q;
                }
            }
        }
    } catch (Throwable $e) {
        error_log("Dynamic Quiz Score Error (PDF): " . $e->getMessage());
    }

    // 8. Stage Completion Progress
    $gameStatsMap = [];
    try {
        $gameSql = "
            SELECT student_id, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_stages
            FROM game_progress
            WHERE student_id IN ($placeholders)
            GROUP BY student_id
        ";
        $gameStmt = $db->prepare($gameSql);
        $gameStmt->execute($studentIds);
        while ($stat = $gameStmt->fetch(PDO::FETCH_ASSOC)) {
            $gameStatsMap[$stat['student_id']] = $stat['completed_stages'];
        }
    } catch (Throwable $e) {}

    // Output dynamic multi-page PDF
    $pdfData = generateStructuredPdf($classInfo, $students, $chapters, $pretestMap, $stageAssessmentMap, $studentExamsMap, $popQuizMap, $uniqueQuizzes, $gameStatsMap, $totalSystemStages);

    $safeClass = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($classInfo['class_name'] ?? 'class'));
    $fileName = 'class_report_' . $safeClass . '_' . date('Ymd_His') . '.pdf';

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdfData));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $pdfData;
    exit;

} catch (Exception $e) {
    error_log('Class report PDF export error: ' . $e->getMessage());
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: ' . $e->getMessage();
    exit;
}
?>