<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr) {
    error_log("Export error: $errstr");
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth_check.php';

try {
    checkTeacherAuth();
    
    $db = Database::getInstance()->getConnection();
    $teacher_id = $_SESSION['teacher_id'] ?? 0;

    // 1. Get class_id parameter
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
    if (!$class_id) {
        throw new Exception('Class ID is required');
    }

    // 2. Fetch Students in Class
    $sql = "
        SELECT DISTINCT s.student_id, s.first_name, s.last_name, s.gender, u.email, u.status,
               c.class_name, sy.label as school_year
        FROM class_students cs
        INNER JOIN students s ON cs.student_id = s.student_id
        INNER JOIN users u ON s.user_id = u.user_id
        INNER JOIN classes c ON cs.class_id = c.class_id
        LEFT JOIN school_year sy ON c.sy_id = sy.sy_id
        WHERE cs.class_id = ? AND c.teacher_id = ?
        ORDER BY s.last_name ASC, s.first_name ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$class_id, $teacher_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        throw new Exception('No students found for this class');
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

    // 4. Fetch Pre-Test Scores per Chapter
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

    // 5. Fetch Stage Scores (Post-Tests)
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

    // 6. EXAM RESULTS FIX: Pull directly from `exam_results` + `student_assessment_results` fallback
    $summativeExamMap = [];
    
    // Priority 1: Query `exam_results` directly
    try {
        $examSql = "
            SELECT er.student_id, er.score
            FROM exam_results er
            WHERE er.student_id IN ($placeholders)
            ORDER BY er.student_id, er.score DESC
        ";
        $examStmt = $db->prepare($examSql);
        $examStmt->execute($studentIds);
        while ($ex = $examStmt->fetch(PDO::FETCH_ASSOC)) {
            $sid = $ex['student_id'];
            if (!isset($summativeExamMap[$sid]) || $ex['score'] > $summativeExamMap[$sid]['score']) {
                $summativeExamMap[$sid] = $ex;
            }
        }
    } catch (Throwable $e) {}

    // Priority 2: Fallback query from `student_assessment_results` if `exam_results` was empty for any student
    try {
        $missingStudents = array_diff($studentIds, array_keys($summativeExamMap));
        if (!empty($missingStudents)) {
            $mPlaceholders = implode(',', array_fill(0, count($missingStudents), '?'));
            $fallbackSql = "
                SELECT ar.student_id, ar.score
                FROM student_assessment_results ar
                WHERE ar.student_id IN ($mPlaceholders)
                  AND (ar.assessment_type LIKE '%exam%' OR ar.assessment_type LIKE '%summative%')
            ";
            $fbStmt = $db->prepare($fallbackSql);
            $fbStmt->execute(array_values($missingStudents));
            while ($fb = $fbStmt->fetch(PDO::FETCH_ASSOC)) {
                $summativeExamMap[$fb['student_id']] = $fb;
            }
        }
    } catch (Throwable $e) {}

    // 7. Dynamic Quizzes & Scores
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
        $quizListStmt->execute([$class_id]);
        while ($qRow = $quizListStmt->fetch(PDO::FETCH_ASSOC)) {
            $qid = $qRow['quiz_id'];
            $title = !empty($qRow['quiz_title']) ? $qRow['quiz_title'] : "Quiz #{$qid}";
            $uniqueQuizzes[$qid] = $title;
        }

        if (!empty($uniqueQuizzes)) {
            $params = array_merge($studentIds, [$class_id]);

            $sqSql = "
                SELECT sq.student_id, sq.quiz_id, sq.score
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
                SELECT qs.student_id, qs.quiz_id, qs.score
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
        error_log("Dynamic Quiz Score Error: " . $e->getMessage());
    }

    // 8. Fetch Stage Completion Stats
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

    // --- COLOR PALETTE DEFINITION ---
    $chapterColors = [
        1 => ['bg' => '#E3F2FD', 'text' => '#0D47A1'], // Light Blue
        2 => ['bg' => '#E8F5E9', 'text' => '#1B5E20'], // Light Green
        3 => ['bg' => '#FFF3E0', 'text' => '#E65100'], // Light Orange
        4 => ['bg' => '#F3E5F5', 'text' => '#4A148C'], // Light Purple
        5 => ['bg' => '#E0F7FA', 'text' => '#006064'], // Light Cyan
    ];
    $examColor    = ['bg' => '#FFEBEE', 'text' => '#B71C1C']; // Light Red / Pink
    $quizColor    = ['bg' => '#FFFDE7', 'text' => '#F57F17']; // Light Yellow
    $summaryColor = ['bg' => '#ECEFF1', 'text' => '#263238']; // Grey

    // Clean output buffers
    while (ob_get_level()) { ob_end_clean(); }

    // Send XLS / Excel-compatible HTML headers
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="class_progress_export_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Start HTML Excel markup
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo '  table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11pt; }';
    echo '  th, td { border: 1px solid #CCCCCC; padding: 6px 10px; text-align: center; }';
    echo '  .align-left { text-align: left; }';
    echo '  .profile-hdr { background-color: #37474F; color: #FFFFFF; font-weight: bold; }';
    echo '  .data-row:nth-child(even) { background-color: #FAFAFA; }';
    echo '</style>';
    echo '</head><body>';
    echo '<table>';

    // --- ROW 1: PRIMARY CATEGORY HEADERS ---
    echo '<tr>';
    
    // Profile
    $profileCols = ['First Name', 'Last Name', 'Email', 'Gender', 'Class', 'School Year', 'Status'];
    echo '<th colspan="' . count($profileCols) . '" class="profile-hdr">STUDENT INFORMATION</th>';

    // Chapters
    foreach ($chapters as $chId => $chInfo) {
        $chNum = $chInfo['num'];
        $chTitle = htmlspecialchars(strtoupper($chInfo['title']));
        $colCount = 1 + count($chInfo['stages']); // 1 Pre-test + N Stages
        $color = $chapterColors[$chNum] ?? $chapterColors[1];

        echo '<th colspan="' . $colCount . '" style="background-color:' . $color['bg'] . '; color:' . $color['text'] . '; font-weight:bold;">'
             . 'CH ' . $chNum . ': ' . $chTitle . '</th>';

        if ($chNum == 4) {
            echo '<th colspan="1" style="background-color:' . $examColor['bg'] . '; color:' . $examColor['text'] . '; font-weight:bold;">MAJOR EXAM</th>';
        }
    }

    // Dynamic Quizzes
    if (!empty($uniqueQuizzes)) {
        echo '<th colspan="' . count($uniqueQuizzes) . '" style="background-color:' . $quizColor['bg'] . '; color:' . $quizColor['text'] . '; font-weight:bold;">DEPLOYED QUIZZES</th>';
    }

    // Summary
    echo '<th colspan="2" style="background-color:' . $summaryColor['bg'] . '; color:' . $summaryColor['text'] . '; font-weight:bold;">SUMMARY</th>';
    echo '</tr>';

    // --- ROW 2: SUB-HEADERS ---
    echo '<tr>';
    foreach ($profileCols as $col) {
        echo '<th class="profile-hdr">' . $col . '</th>';
    }

    foreach ($chapters as $chId => $chInfo) {
        $chNum = $chInfo['num'];
        $color = $chapterColors[$chNum] ?? $chapterColors[1];
        $subStyle = 'style="background-color:' . $color['bg'] . '; color:' . $color['text'] . ';"';

        echo '<th ' . $subStyle . '>Pre-Test</th>';
        foreach ($chInfo['stages'] as $sIdx => $stg) {
            echo '<th ' . $subStyle . '>Stage ' . ($sIdx + 1) . ' Post-Test</th>';
        }

        if ($chNum == 4) {
            echo '<th style="background-color:' . $examColor['bg'] . '; color:' . $examColor['text'] . ';">Official Exam</th>';
        }
    }

    if (!empty($uniqueQuizzes)) {
        foreach ($uniqueQuizzes as $qId => $qTitle) {
            echo '<th style="background-color:' . $quizColor['bg'] . '; color:' . $quizColor['text'] . ';">' . htmlspecialchars($qTitle) . '</th>';
        }
    }

    echo '<th style="background-color:' . $summaryColor['bg'] . ';">Stages Completed</th>';
    echo '<th style="background-color:' . $summaryColor['bg'] . ';">Overall Progress</th>';
    echo '</tr>';

    // --- ROW DATA GENERATION ---
    foreach ($students as $student) {
        $sid = $student['student_id'];
        $completedStagesCount = (int)($gameStatsMap[$sid] ?? 0);

        echo '<tr class="data-row">';
        echo '<td class="align-left">' . htmlspecialchars($student['first_name'] ?? '') . '</td>';
        echo '<td class="align-left">' . htmlspecialchars($student['last_name'] ?? '') . '</td>';
        echo '<td class="align-left">' . htmlspecialchars($student['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['gender'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($student['class_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($student['school_year'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($student['status'] ?? '') . '</td>';

        // 1. Chapter Scores
        foreach ($chapters as $chId => $chInfo) {
            $chNum = $chInfo['num'];

            // Pre-test
            $preData = $pretestMap[$sid][$chId] ?? null;
            if ($preData && $preData['score'] !== null) {
                echo '<td>' . (int)$preData['score'] . '</td>';
            } else {
                echo '<td>-</td>';
            }

            // Stage Post-tests
            foreach ($chInfo['stages'] as $stg) {
                $stgId = $stg['stage_id'];
                $stgData = $stageAssessmentMap[$sid][$stgId] ?? null;

                if ($stgData && $stgData['score'] !== null) {
                    echo '<td>' . (int)$stgData['score'] . '</td>';
                } else {
                    echo '<td>-</td>';
                }
            }

            // Major Exam after Chapter 4
            if ($chNum == 4) {
                $examData = $summativeExamMap[$sid] ?? null;
                if ($examData && isset($examData['score']) && $examData['score'] !== null) {
                    echo '<td>' . (int)$examData['score'] . '</td>';
                } else {
                    echo '<td>-</td>';
                }
            }
        }

        // 2. Dynamic Quizzes
        foreach ($uniqueQuizzes as $qId => $qTitle) {
            $qData = $popQuizMap[$sid][$qId] ?? null;
            if ($qData && $qData['score'] !== null) {
                echo '<td>' . (int)$qData['score'] . '</td>';
            } else {
                echo '<td>-</td>';
            }
        }

        // 3. Summaries
        echo '<td>' . $completedStagesCount . '</td>';

        $overallProgressPct = $totalSystemStages > 0 ? ($completedStagesCount / $totalSystemStages) * 100 : 0;
        echo '<td>' . number_format($overallProgressPct, 1) . '%</td>';

        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;

} catch (Exception $e) {
    error_log('Export error: ' . $e->getMessage());
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
?>