<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkTeacherAuth();
$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_view_reports');
$teacher_id = $_SESSION['teacher_id'];

$dbError = null;

try {
// Get active school year
$activeSchoolYear = $db->query("SELECT sy_id, label FROM school_year WHERE is_active = 1 LIMIT 1")->fetch();
$active_sy_id = $activeSchoolYear['sy_id'] ?? null;

// Get analytics data
// --- Score Distribution Data for Chart ---
$scoreDistribution = [
    'excellent' => 0,
    'good' => 0,
    'fair' => 0,
    'needs_improvement' => 0
];
$scoreDistQuery = $db->prepare("
    SELECT (sq.score / q.total_score) * 100 as percent
    FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN classes c ON q.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ? AND q.total_score > 0 AND sq.status = 'completed'
");
$scoreDistQuery->execute([$teacher_id, $active_sy_id]);
foreach ($scoreDistQuery->fetchAll() as $row) {
    $percent = $row['percent'];
    if ($percent >= 90) {
        $scoreDistribution['excellent']++;
    } elseif ($percent >= 75) {
        $scoreDistribution['good']++;
    } elseif ($percent >= 60) {
        $scoreDistribution['fair']++;
    } else {
        $scoreDistribution['needs_improvement']++;
    }
}


// --- Class/Section performance (active school year only, percent-based) ---
$classPerformance = $db->prepare("
    SELECT c.class_name, COUNT(DISTINCT s.student_id) as students, 
           COUNT(sq.student_quiz_id) as total_attempts,
           ROUND((SUM(sq.score) / NULLIF(SUM(q.total_score),0)) * 100, 2) as avg_percentage,
           SUM(CASE WHEN (sq.score / q.total_score) * 100 >= 75 THEN 1 ELSE 0 END) as passed
    FROM classes c
    JOIN class_students cs ON c.class_id = cs.class_id
    JOIN students s ON cs.student_id = s.student_id
    JOIN student_quizzes sq ON s.student_id = sq.student_id
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    WHERE q.teacher_id = ? AND c.teacher_id = ? AND c.sy_id = ? AND q.total_score > 0
    GROUP BY c.class_id, c.class_name
    ORDER BY avg_percentage DESC
");
$classPerformance->execute([$teacher_id, $teacher_id, $active_sy_id]);
$classPerformance = $classPerformance->fetchAll();
$classPerfChartData = [];
foreach ($classPerformance as $class) {
    $classPerfChartData[] = [
        'class_name' => $class['class_name'],
        'avg_score' => isset($class['avg_percentage']) ? round($class['avg_percentage'], 2) : 0,
        'students' => $class['students']
    ];
}

// --- Pass/Fail Pie Chart Data (all quizzes) ---
$passFail = ['passed' => 0, 'failed' => 0];
$passFailQuery = $db->prepare("
    SELECT sq.score, q.total_score
    FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN classes c ON q.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ? AND q.total_score > 0 AND sq.status = 'completed'
");
$passFailQuery->execute([$teacher_id, $active_sy_id]);
foreach ($passFailQuery->fetchAll() as $row) {
    $percent = ($row['score'] / $row['total_score']) * 100;
    if ($percent >= 75) {
        $passFail['passed']++;
    } else {
        $passFail['failed']++;
    }
}

// Total quizzes created (only for classes in the active school year)
$totalQuizzes = $db->prepare("
    SELECT COUNT(DISTINCT q.quiz_id)
    FROM quizzes q
    JOIN classes c ON q.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ?
");
$totalQuizzes->execute([$teacher_id, $active_sy_id]);
$totalQuizzes = $totalQuizzes->fetchColumn();

// Total quiz attempts (only from students in active school year)
$totalAttempts = $db->prepare("
    SELECT COUNT(*) FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN students s ON sq.student_id = s.student_id
    JOIN class_students cs ON s.student_id = cs.student_id
    JOIN classes c ON cs.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ? AND c.teacher_id = ?
");
$totalAttempts->execute([$teacher_id, $active_sy_id, $teacher_id]);
$totalAttempts = $totalAttempts->fetchColumn();

// Average score across all quizzes (SUM(score)/SUM(total_score)*100, only from students in active school year)
$avgScore = $db->prepare("
    SELECT ROUND((SUM(sq.score) / NULLIF(SUM(q.total_score),0)) * 100, 2) as avg_percent
    FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN students s ON sq.student_id = s.student_id
    JOIN class_students cs ON s.student_id = cs.student_id
    JOIN classes c ON cs.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ? AND c.teacher_id = ? AND q.total_score > 0
");
$avgScore->execute([$teacher_id, $active_sy_id, $teacher_id]);
$avgScore = round($avgScore->fetchColumn(), 2);

// Completion rate (only from students in active school year)
$completedQuizzes = $db->prepare("
    SELECT COUNT(*) FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN students s ON sq.student_id = s.student_id
    JOIN class_students cs ON s.student_id = cs.student_id
    JOIN classes c ON cs.class_id = c.class_id
    WHERE q.teacher_id = ? AND sq.status = 'completed' AND c.sy_id = ? AND c.teacher_id = ?
");
$completedQuizzes->execute([$teacher_id, $active_sy_id, $teacher_id]);
$completedQuizzes = $completedQuizzes->fetchColumn();
$completionRate = $totalAttempts > 0 ? round(($completedQuizzes / $totalAttempts) * 100, 2) : 0;

// Top performing students

// Top performing students (only those enrolled in classes for the active school year)
$topStudents = $db->prepare("
    SELECT s.first_name, s.last_name, c.class_name, 
           AVG(sq.score / q.total_score) * 100 as avg_percent, 
           COUNT(sq.student_quiz_id) as quizzes_taken,
           SUM(CASE WHEN sq.status = 'completed' THEN 1 ELSE 0 END) as completed_quizzes,
           MAX(sq.score) as highest_score,
           MAX(q.total_score) as max_total_score
    FROM students s
    JOIN student_quizzes sq ON s.student_id = sq.student_id
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN class_students cs ON s.student_id = cs.student_id
    JOIN classes c ON cs.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ? AND c.teacher_id = ? AND q.total_score > 0
    GROUP BY s.student_id, s.first_name, s.last_name, c.class_name
    ORDER BY avg_percent DESC
    LIMIT 10
");
$topStudents->execute([$teacher_id, $active_sy_id, $teacher_id]);
$topStudents = $topStudents->fetchAll();


// --- Quiz Performance Report (active school year only, using SUM(score)/SUM(total_score)*100) ---
$quizPerf = $db->prepare("
    SELECT q.quiz_id, q.quiz_title,
        COUNT(DISTINCT sq.student_id) as students_attempted,
        SUM(sq.score) as total_score,
        SUM(q.total_score) as total_items,
        ROUND((SUM(sq.score) / NULLIF(SUM(q.total_score),0)) * 100, 2) as avg_percentage,
        SUM(CASE WHEN (sq.score / q.total_score) * 100 >= 75 THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN (sq.score / q.total_score) * 100 < 75 THEN 1 ELSE 0 END) as failed
    FROM quizzes q
    JOIN classes c ON q.class_id = c.class_id
    LEFT JOIN (
        SELECT sq1.* FROM student_quizzes sq1
        INNER JOIN (
            SELECT student_id, quiz_id, MAX(score) as best_score
            FROM student_quizzes
            WHERE status = 'completed'
            GROUP BY student_id, quiz_id
        ) sq2 ON sq1.student_id = sq2.student_id AND sq1.quiz_id = sq2.quiz_id AND sq1.score = sq2.best_score
        WHERE sq1.status = 'completed'
    ) sq ON q.quiz_id = sq.quiz_id
    WHERE q.teacher_id = ? AND c.sy_id = ?
    GROUP BY q.quiz_id, q.quiz_title
    ORDER BY q.quiz_id DESC
");
$quizPerf->execute([$teacher_id, $active_sy_id]);
$quizPerf = $quizPerf->fetchAll();
$quizPerformanceData = [];
foreach ($quizPerf as $quiz) {
    $quizPerformanceData[] = [
        'quiz_id' => $quiz['quiz_id'],
        'science_concept' => $quiz['quiz_title'],
        'avg_score' => isset($quiz['avg_percentage']) && $quiz['avg_percentage'] !== null ? round($quiz['avg_percentage'], 2) : 0,
        'attempts' => isset($quiz['students_attempted']) ? $quiz['students_attempted'] : 0
    ];
}

// --- Daily Activity Data (Last 7 Days) ---
$activityData = [];
$activityStmt = $db->prepare("
    SELECT DATE(sq.submitted_at) as date, COUNT(*) as count
    FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN classes c ON q.class_id = c.class_id
    WHERE q.teacher_id = ? AND c.sy_id = ? AND sq.status = 'completed' AND sq.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(sq.submitted_at)
    ORDER BY date ASC
");
$activityStmt->execute([$teacher_id, $active_sy_id]);
$activityRows = $activityStmt->fetchAll();
// Fill in all 7 days (even if 0)
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach ($activityRows as $row) {
    $days[$row['date']] = (int)$row['count'];
}
foreach ($days as $date => $count) {
    $activityData[] = [
        'date' => $date,
        'count' => $count
    ];
}

// Quiz performance summary (only from students in active school year, using SUM(score)/SUM(total_score)*100)
$quizSummary = $db->prepare("
    SELECT q.quiz_id, q.quiz_title, st.science_concept, q.quiz_type, q.total_score, q.start_time, q.end_time,
           COUNT(sq.student_quiz_id) as attempts, 
           SUM(sq.score) as total_score,
           SUM(q.total_score) as total_items,
           ROUND((SUM(sq.score) / NULLIF(SUM(q.total_score),0)) * 100, 2) as avg_percentage,
           MIN(sq.score) as min_score,
           MAX(sq.score) as max_score,
           SUM(CASE WHEN sq.status = 'completed' THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN sq.score >= 75 THEN 1 ELSE 0 END) as passed,
           COUNT(DISTINCT sq.student_id) as unique_students
    FROM quizzes q
    LEFT JOIN stages st ON q.stage_id = st.stage_id
    LEFT JOIN student_quizzes sq ON q.quiz_id = sq.quiz_id
    LEFT JOIN students s ON sq.student_id = s.student_id
    LEFT JOIN class_students cs ON s.student_id = cs.student_id
    LEFT JOIN classes c ON cs.class_id = c.class_id
    WHERE q.teacher_id = ? AND (c.sy_id = ? OR sq.student_quiz_id IS NULL)
    GROUP BY q.quiz_id, q.quiz_title, st.science_concept, q.quiz_type, q.total_score, q.start_time, q.end_time
    ORDER BY q.quiz_id DESC
");
$quizSummary->execute([$teacher_id, $active_sy_id]);
$quizSummary = $quizSummary->fetchAll();

// --- Student Progress Report ---
$progress = $db->prepare("
    SELECT s.student_id, s.first_name, s.last_name,
        MAX(CASE WHEN sq.status = 'completed' THEN sq.score ELSE NULL END) as best_score,
        MAX(CASE WHEN sq.status = 'completed' THEN sq.submitted_at ELSE NULL END) as last_activity,
        SUM(CASE WHEN sq.status = 'completed' AND sq.score >= 75 THEN 1 ELSE 0 END) as quizzes_passed,
        COUNT(DISTINCT CASE WHEN sq.status = 'completed' THEN sq.quiz_id ELSE NULL END) as quizzes_taken,
        (
            SELECT COUNT(DISTINCT ch.chapter_id)
            FROM chapters ch
            JOIN lessons l ON l.chapter_id = ch.chapter_id
            JOIN questions_master qm ON qm.lesson_id = l.lesson_id
            JOIN student_answers sa ON sa.question_id = qm.question_id
            JOIN student_quizzes sq2 ON sa.student_quiz_id = sq2.student_quiz_id
            WHERE sq2.student_id = s.student_id
        ) as chapters_completed
    FROM students s
    LEFT JOIN student_quizzes sq ON s.student_id = sq.student_id
    LEFT JOIN quizzes q ON sq.quiz_id = q.quiz_id AND q.teacher_id = ?
    LEFT JOIN classes c ON q.class_id = c.class_id
    WHERE c.sy_id = ? OR c.sy_id IS NULL
    GROUP BY s.student_id, s.first_name, s.last_name
");
$progress->execute([$teacher_id, $active_sy_id]);
$progress = $progress->fetchAll();

// --- Engagement Report ---
$engage = $db->prepare("
    SELECT s.student_id, s.first_name, s.last_name, u.last_login,
        COUNT(DISTINCT CASE WHEN sq.status = 'completed' THEN sq.quiz_id ELSE NULL END) as quizzes_taken,
        (
            SELECT COUNT(DISTINCT l.lesson_id)
            FROM lessons l
            JOIN questions_master qm ON qm.lesson_id = l.lesson_id
            JOIN student_answers sa ON sa.question_id = qm.question_id
            JOIN student_quizzes sq2 ON sa.student_quiz_id = sq2.student_quiz_id
            WHERE sq2.student_id = s.student_id
        ) as lessons_completed
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN student_quizzes sq ON s.student_id = sq.student_id
    LEFT JOIN quizzes q ON sq.quiz_id = q.quiz_id AND q.teacher_id = ?
    LEFT JOIN classes c ON q.class_id = c.class_id
    WHERE c.sy_id = ? OR c.sy_id IS NULL
    GROUP BY s.student_id, s.first_name, s.last_name, u.last_login
");
$engage->execute([$teacher_id, $active_sy_id]);
$engage = $engage->fetchAll();

// --- Most Missed Question ---
$missed = $db->prepare("
    SELECT qm.question_text, COUNT(sa.answer_id) as misses
    FROM questions_master qm
    JOIN lessons l ON qm.lesson_id = l.lesson_id
    JOIN chapters ch ON l.chapter_id = ch.chapter_id
    JOIN quizzes q ON q.stage_id = ch.chapter_id AND q.teacher_id = ? AND q.quiz_id IS NOT NULL
    JOIN student_answers sa ON qm.question_id = sa.question_id
    JOIN quiz_choices qc ON sa.selected_choice_id = qc.choice_id
    WHERE qc.is_correct = 0
    GROUP BY qm.question_id, qm.question_text
    ORDER BY misses DESC
    LIMIT 1
");
$missed->execute([$teacher_id]);
$missedQ = $missed->fetch();

// --- Recent Quiz Activities ---
$recentActivities = $db->prepare("
    SELECT s.first_name, s.last_name, c.class_name, q.quiz_id, q.quiz_title, st.science_concept, 
           sq.score, q.total_score, sq.submitted_at, sq.started_at, sq.status, TIMESTAMPDIFF(SECOND, sq.started_at, sq.submitted_at) as time_spent_seconds
    FROM student_quizzes sq
    JOIN students s ON sq.student_id = s.student_id
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    LEFT JOIN stages st ON q.stage_id = st.stage_id
    LEFT JOIN class_students cs ON s.student_id = cs.student_id
    LEFT JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ? AND c.sy_id = ?
    WHERE q.teacher_id = ?
    ORDER BY sq.submitted_at DESC
    LIMIT 20
");
$recentActivities->execute([$teacher_id, $active_sy_id, $teacher_id]);
$recentActivities = $recentActivities->fetchAll() ?: [];

} catch (PDOException $e) {
    $dbError = 'A database error occurred while loading reports. Please try again later.';
    error_log('reports.php DB error: ' . $e->getMessage());
    // Init defaults so the page renders without undefined variable errors
    $activeSchoolYear = null; $active_sy_id = null;
    $scoreDistribution = ['excellent'=>0,'good'=>0,'fair'=>0,'needs_improvement'=>0];
    $classPerformance = []; $classPerfChartData = [];
    $passFail = ['passed'=>0,'failed'=>0];
    $totalQuizzes = 0; $totalAttempts = 0; $avgScore = 0;
    $completedQuizzes = 0; $completionRate = 0;
    $topStudents = []; $quizPerf = []; $quizPerformanceData = [];
    $activityData = []; $quizSummary = []; $progress = [];
    $engage = []; $missedQ = null; $recentActivities = [];
}

function pdfEscapeText($text) {
    $text = (string) $text;
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return preg_replace('/[^\x20-\x7E]/', '?', $text);
}

function buildSimplePdf(array $lines) {
    $content = "BT\n/F1 11 Tf\n50 792 Td\n14 TL\n";
    foreach ($lines as $idx => $line) {
        if ($idx > 0) {
            $content .= "T*\n";
        }
        $content .= '(' . pdfEscapeText($line) . ") Tj\n";
    }
    $content .= "ET\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    $generatedAt = date('Y-m-d H:i:s');
    $schoolYearLabel = $activeSchoolYear['label'] ?? 'N/A';

    $lines = [
        'Atomix - Reports & Analytics',
        'Generated: ' . $generatedAt,
        'School Year: ' . $schoolYearLabel,
        str_repeat('-', 72),
        'Overview',
        'Total Quizzes: ' . (int) $totalQuizzes,
        'Quiz Attempts: ' . (int) $totalAttempts,
        'Average Score: ' . round((float) $avgScore, 2) . '%',
        'Completion Rate: ' . round((float) $completionRate, 2) . '%',
        '',
        'Performance Distribution',
        'Excellent (90-100%): ' . (int) ($scoreDistribution['excellent'] ?? 0),
        'Good (75-89%): ' . (int) ($scoreDistribution['good'] ?? 0),
        'Fair (60-74%): ' . (int) ($scoreDistribution['fair'] ?? 0),
        'Needs Work (<60%): ' . (int) ($scoreDistribution['needs_improvement'] ?? 0),
        '',
        'Top Students (Top 10)'
    ];

    if (!empty($topStudents)) {
        $rank = 1;
        foreach ($topStudents as $student) {
            $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
            $className = $student['class_name'] ?? 'N/A';
            $avgPercent = isset($student['avg_percent']) ? round((float) $student['avg_percent'], 1) : 0;
            $lines[] = $rank . '. ' . $fullName . ' | ' . $className . ' | Avg: ' . $avgPercent . '% | Quizzes: ' . (int) ($student['quizzes_taken'] ?? 0);
            $rank++;
        }
    } else {
        $lines[] = 'No student records available.';
    }

    $lines[] = '';
    $lines[] = 'Class Performance';
    if (!empty($classPerformance)) {
        foreach ($classPerformance as $class) {
            $className = $class['class_name'] ?? 'N/A';
            $students = (int) ($class['students'] ?? 0);
            $attempts = (int) ($class['total_attempts'] ?? 0);
            $avgPercent = isset($class['avg_percentage']) ? round((float) $class['avg_percentage'], 1) : 0;
            $passRate = $attempts > 0 ? round(((float) ($class['passed'] ?? 0) / $attempts) * 100, 1) : 0;
            $lines[] = $className . ' | Students: ' . $students . ' | Attempts: ' . $attempts . ' | Avg: ' . $avgPercent . '% | Pass: ' . $passRate . '%';
        }
    } else {
        $lines[] = 'No class performance records available.';
    }

    $filename = 'reports_' . date('Ymd_His') . '.pdf';
    $pdf = buildSimplePdf($lines);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit;
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            color: #1e293b;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem 2.5rem;
        }

        .top-header {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .top-header-left {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-pdf-export {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            color: #ffffff;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            border: 1px solid rgba(127, 29, 29, 0.25);
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(185, 28, 28, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-pdf-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(185, 28, 28, 0.28);
            color: #ffffff;
        }

        .top-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .school-year-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(30, 64, 175, 0.1);
            color: #1e40af;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .stat-content { flex: 1; }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e40af;
            line-height: 1.2;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 0.25rem;
        }

        /* Charts Section */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 14px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
            padding: 1.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 250px;
        }

        .dashboard-card:hover {
            border-color: #1e40af;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        .dashboard-card h2 {
            color: #1e40af;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .dashboard-card .chart-canvas-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .chart-canvas-wrap {
            padding: 0.75rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            width: 100%;
            min-height: 200px;
            position: relative;
        }
        
        .chart-canvas-wrap canvas {
            max-width: 100%;
            height: auto !important;
        }

        /* Tables */
        .content-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
        }

        .content-section h2 {
            color: #1e40af;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table th,
        .data-table td {
            padding: 0.875rem 1rem;
            text-align: left;
        }

        .data-table th {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #1e40af;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table th:first-child { border-radius: 10px 0 0 0; }
        .data-table th:last-child { border-radius: 0 10px 0 0; }

        .data-table td {
            color: #1e293b;
            font-size: 0.9rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table tbody tr:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }

        .data-table tbody tr:last-child td { border-bottom: none; }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.8rem;
        }

        .rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
        .rank-2 { background: linear-gradient(135deg, #9ca3af, #6b7280); color: white; }
        .rank-3 { background: linear-gradient(135deg, #f97316, #ea580c); color: white; }
        .rank-default { background: #e2e8f0; color: #64748b; }

        .score-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .score-excellent { background: #d1fae5; color: #065f46; }
        .score-good { background: #dbeafe; color: #1e40af; }
        .score-fair { background: #fef3c7; color: #92400e; }
        .score-poor { background: #fee2e2; color: #991b1b; }

        /* Performance Cards */
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .performance-card {
            padding: 1.25rem;
            border-radius: 14px;
            color: white;
            text-align: center;
        }

        .performance-card.excellent { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .performance-card.good { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .performance-card.fair { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .performance-card.poor { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .performance-card .perf-number {
            font-size: 2rem;
            font-weight: 700;
        }

        .performance-card .perf-label {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .performance-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 1.5rem; }
            .charts-row { grid-template-columns: 1fr; }
            .top-header { align-items: flex-start; }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .performance-grid { grid-template-columns: 1fr; }
        }

        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card, .dashboard-card, .content-section, .performance-card {
            animation: fadeInUp 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
        </aside>
        
        <main class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
                    <?php if ($activeSchoolYear): ?>
                    <span class="school-year-badge">
                        <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($activeSchoolYear['label']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <a href="reports.php?download=pdf" class="btn-pdf-export">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Reports &amp; Analytics</span>
            </nav>

            <?php if ($dbError): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $totalQuizzes; ?></div>
                        <div class="stat-label">Total Quizzes</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $totalAttempts; ?></div>
                        <div class="stat-label">Quiz Attempts</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $avgScore; ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $completionRate; ?>%</div>
                        <div class="stat-label">Completion Rate</div>
                    </div>
                </div>
            </div>

            <!-- Performance Distribution -->
            <div class="performance-grid">
                <div class="performance-card excellent">
                    <div class="perf-number"><?php echo $scoreDistribution['excellent']; ?></div>
                    <div class="perf-label">Excellent (90-100%)</div>
                </div>
                <div class="performance-card good">
                    <div class="perf-number"><?php echo $scoreDistribution['good']; ?></div>
                    <div class="perf-label">Good (75-89%)</div>
                </div>
                <div class="performance-card fair">
                    <div class="perf-number"><?php echo $scoreDistribution['fair']; ?></div>
                    <div class="perf-label">Fair (60-74%)</div>
                </div>
                <div class="performance-card poor">
                    <div class="perf-number"><?php echo $scoreDistribution['needs_improvement']; ?></div>
                    <div class="perf-label">Needs Work (<60%)</div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="charts-row">
                <div class="dashboard-card">
                    <h2><i class="fas fa-chart-pie"></i> Score Distribution</h2>
                    <div class="chart-canvas-wrap">
                        <canvas id="scoreDistributionChart" height="150"></canvas>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h2><i class="fas fa-graduation-cap"></i> Class Performance</h2>
                    <div class="chart-canvas-wrap">
                        <canvas id="classPerformanceChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="charts-row">
                <div class="dashboard-card" style="grid-column: span 2;">
                    <h2><i class="fas fa-chart-line"></i> Daily Activity (Last 7 Days)</h2>
                    <div class="chart-canvas-wrap" style="max-width: 100%;">
                        <canvas id="activityChart" height="130"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <?php if (!empty($quizPerformanceData)): ?>
            <div class="charts-row">
                <div class="dashboard-card" style="grid-column: span 2;">
                    <h2><i class="fas fa-tasks"></i> Quiz Performance Overview</h2>
                    <div class="chart-canvas-wrap" style="max-width: 100%; height: 180px;">
                        <canvas id="quizPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Students -->
            <div class="content-section">
                <h2><i class="fas fa-trophy"></i> Top Performing Students</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Average</th>
                            <th>Quizzes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($topStudents as $student): 
                            $avgPercent = isset($student['avg_percent']) ? round($student['avg_percent'], 1) : 0;
                            $scoreClass = $avgPercent >= 90 ? 'excellent' : ($avgPercent >= 75 ? 'good' : ($avgPercent >= 60 ? 'fair' : 'poor'));
                        ?>
                        <tr>
                            <td>
                                <span class="rank-badge <?php echo $rank <= 3 ? 'rank-' . $rank : 'rank-default'; ?>">
                                    <?php echo $rank++; ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                            <td><span class="score-badge score-<?php echo $scoreClass; ?>"><?php echo $avgPercent; ?>%</span></td>
                            <td><?php echo $student['quizzes_taken']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topStudents)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 2rem;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Class Performance Table -->
            <?php if (count($classPerformance) > 0): ?>
            <div class="content-section">
                <h2><i class="fas fa-users-class"></i> Class Performance Summary</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Students</th>
                            <th>Attempts</th>
                            <th>Average</th>
                            <th>Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classPerformance as $class): 
                            $passRate = $class['total_attempts'] > 0 ? round(($class['passed'] / $class['total_attempts']) * 100, 1) : 0;
                            $avgPercent = isset($class['avg_percentage']) ? round($class['avg_percentage'], 1) : 0;
                            $scoreClass = $avgPercent >= 90 ? 'excellent' : ($avgPercent >= 75 ? 'good' : ($avgPercent >= 60 ? 'fair' : 'poor'));
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                            <td><?php echo $class['students']; ?></td>
                            <td><?php echo $class['total_attempts']; ?></td>
                            <td><span class="score-badge score-<?php echo $scoreClass; ?>"><?php echo $avgPercent; ?>%</span></td>
                            <td><?php echo $passRate; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </main>
    </div>
    
    <script>
        // Score Distribution Chart
        const scoreDistCtx = document.getElementById('scoreDistributionChart').getContext('2d');
        new Chart(scoreDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Fair', 'Needs Work'],
                datasets: [{
                    data: [
                        <?php echo $scoreDistribution['excellent']; ?>,
                        <?php echo $scoreDistribution['good']; ?>,
                        <?php echo $scoreDistribution['fair']; ?>,
                        <?php echo $scoreDistribution['needs_improvement']; ?>
                    ],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { family: "'Inter', sans-serif", size: 11, weight: 500 },
                            color: '#1e293b'
                        }
                    }
                }
            }
        });

        // Class Performance Chart
        const classPerfCtx = document.getElementById('classPerformanceChart').getContext('2d');
        new Chart(classPerfCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($classPerfChartData as $c) echo "'" . addslashes($c['class_name']) . "',"; ?>],
                datasets: [{
                    label: 'Avg Score %',
                    data: [<?php foreach ($classPerfChartData as $c) echo $c['avg_score'] . ','; ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#2563eb',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });

        // Daily Activity Chart (Line)
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($activityData as $a) echo "'" . date('M j', strtotime($a['date'])) . "',"; ?>],
                datasets: [{
                    label: 'Quiz Submissions',
                    data: [<?php foreach ($activityData as $a) echo $a['count'] . ','; ?>],
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 10 }, stepSize: 1 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });



        // Quiz Performance Chart (Horizontal Bar)
        <?php if (!empty($quizPerformanceData)): ?>
        const quizPerfCtx = document.getElementById('quizPerformanceChart').getContext('2d');
        new Chart(quizPerfCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach (array_slice($quizPerformanceData, 0, 8) as $q) echo "'" . addslashes(substr($q['science_concept'], 0, 25)) . "',"; ?>],
                datasets: [{
                    label: 'Average Score %',
                    data: [<?php foreach (array_slice($quizPerformanceData, 0, 8) as $q) echo $q['avg_score'] . ','; ?>],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(20, 184, 166, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(99, 102, 241, 0.8)'
                    ],
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
                                <td>
                                    <?php
                                        if (isset($student['highest_score']) && isset($student['max_total_score']) && $student['max_total_score'] > 0) {
                                            $percent = round(($student['highest_score'] / $student['max_total_score']) * 100, 1);
                                            echo $student['highest_score'] . '/' . $student['max_total_score'] . ' (' . $percent . '%)';
                                        } elseif (isset($student['highest_score'])) {
                                            echo $student['highest_score'];
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
            </div>
        </main>
    </div>
    
    <script>
                <h2>Quiz Performance Summary</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Quiz Title</th>
                                <th>Students</th>
                                <th>Attempts</th>
                                <th>Avg Score</th>
                                <th>Min/Max</th>
                                <th>Pass Rate</th>
                                <th>Completion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizSummary as $quiz): ?>
                            <?php 
                                $passRate = $quiz['attempts'] > 0 ? round(($quiz['passed'] / $quiz['attempts']) * 100, 1) : 0;
                                $completionRate = $quiz['attempts'] > 0 ? round(($quiz['completed'] / $quiz['attempts']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($quiz['quiz_title']); ?></td>
                                <td><?php echo $quiz['unique_students']; ?></td>
                                <td><?php echo $quiz['attempts']; ?></td>
                                <td>
                                    <?php
                                        // Show average as percent and as raw if possible (using avg_percentage)
                                        if (isset($quiz['avg_percentage']) && isset($quiz['total_score']) && $quiz['total_score'] > 0) {
                                            $avgRaw = round(($quiz['avg_percentage'] / 100) * $quiz['total_score'], 1);
                                            echo $avgRaw . '/' . $quiz['total_score'] . ' (' . round($quiz['avg_percentage'], 1) . '%)';
                                        } elseif (isset($quiz['avg_percentage'])) {
                                            echo round($quiz['avg_percentage'], 1) . '%';
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td><?php echo $quiz['min_score'] !== null ? round($quiz['min_score'], 0) . '% - ' . round($quiz['max_score'], 0) . '%' : 'N/A'; ?></td>
                                <td><?php echo $passRate . '%'; ?></td>
                                <td><?php echo $completionRate . '%'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="content-section">
                <h2>Recent Quiz Activities</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Quiz Title</th>
                                <th>Score</th>
                                <th>Time Spent</th>
                                <th>Status</th>
                                <th>Taken At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['class_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($activity['quiz_title']); ?></td>
                                <td>
                                    <?php
                                        $score = isset($activity['score']) ? $activity['score'] : null;
                                        $total = isset($activity['total_score']) ? $activity['total_score'] : null;
                                        if ($score !== null && $total) {
                                            $percent = $total > 0 ? round(($score / $total) * 100, 1) : 0;
                                            echo $score . '/' . $total . ' (' . $percent . '%)';
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        if (isset($activity['time_spent_seconds']) && $activity['time_spent_seconds'] !== null) {
                                            if ($activity['time_spent_seconds'] < 60) {
                                                echo $activity['time_spent_seconds'] . ' sec';
                                            } else {
                                                echo round($activity['time_spent_seconds'] / 60, 1) . ' min';
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; 
                                        <?php 
                                        if($activity['status'] == 'completed') echo 'background: #d1fae5; color: #065f46;';
                                        elseif($activity['status'] == 'in_progress') echo 'background: #fef3c7; color: #92400e;';
                                        else echo 'background: #e5e7eb; color: #374151;';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $activity['submitted_at'] ? date('M d, Y H:i', strtotime($activity['submitted_at'])) : 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Score Distribution Section -->
            <div class="content-section">
                <h2>Performance Distribution</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700;"><?php echo $scoreDistribution['excellent'] ?? 0; ?></div>
                        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Excellent (90-100%)</div>
                    </div>
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700;"><?php echo $scoreDistribution['good'] ?? 0; ?></div>
                        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Good (75-89%)</div>
                    </div>
                    <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700;"><?php echo $scoreDistribution['fair'] ?? 0; ?></div>
                        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Fair (60-74%)</div>
                    </div>
                    <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700;"><?php echo $scoreDistribution['needs_improvement'] ?? 0; ?></div>
                        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Needs Improvement (<60%)</div>
                    </div>
                </div>
            </div>
            
            <!-- Section Performance -->
            <?php if (count($classPerformance) > 0): ?>
            <div class="content-section">
                <h2>Class Performance Comparison</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Students</th>
                                <th>Total Attempts</th>
                                <th>Average Score</th>
                                <th>Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classPerformance as $class): ?>
                            <?php 
                                $passRate = $class['total_attempts'] > 0 ? round(($class['passed'] / $class['total_attempts']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                <td><?php echo $class['students']; ?></td>
                                <td><?php echo $class['total_attempts']; ?></td>
                                <td><?php echo isset($class['avg_percentage']) ? round($class['avg_percentage'], 1) . '%' : 'N/A'; ?></td>
                                <td><?php echo $passRate; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Quiz Performance Chart
        const quizPerformanceCtx = document.getElementById('quizPerformanceChart').getContext('2d');
        const quizPerformanceChart = new Chart(quizPerformanceCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($quizPerformanceData as $quiz) {
                        $quizTitle = $quiz['science_concept'] ? $quiz['science_concept'] : 'Quiz #' . $quiz['quiz_id'];
                        echo "'" . addslashes($quizTitle) . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Average Score (%)',
                    data: [
                        <?php 
                        foreach ($quizPerformanceData as $quiz) {
                            echo ($quiz['avg_score'] ? round($quiz['avg_score'], 2) : 0) . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }, {
                    label: 'Attempts',
                    data: [
                        <?php 
                        foreach ($quizPerformanceData as $quiz) {
                            echo $quiz['attempts'] . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($activityData as $activity) {
                        echo "'" . date('M d', strtotime($activity['date'])) . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Quiz Attempts',
                    data: [
                        <?php 
                        foreach ($activityData as $activity) {
                            echo $activity['count'] . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

            // Score Distribution Pie Chart
            const scoreDistCtx = document.getElementById('scoreDistributionChart').getContext('2d');
            const scoreDistChart = new Chart(scoreDistCtx, {
                type: 'pie',
                data: {
                    labels: ['Excellent (90-100%)', 'Good (75-89%)', 'Fair (60-74%)', 'Needs Improvement (<60%)'],
                    datasets: [{
                        data: [
                            <?php echo $scoreDistribution['excellent']; ?>,
                            <?php echo $scoreDistribution['good']; ?>,
                            <?php echo $scoreDistribution['fair']; ?>,
                            <?php echo $scoreDistribution['needs_improvement']; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(16, 185, 129, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });

            // Class Performance Bar Chart
            const classPerfCtx = document.getElementById('classPerformanceChart').getContext('2d');
            const classPerfLabels = [
                <?php foreach ($classPerfChartData as $c) echo "'" . addslashes($c['class_name']) . "',"; ?>
            ];
            const classPerfScores = [
                <?php foreach ($classPerfChartData as $c) echo $c['avg_score'] . ','; ?>
            ];
            const classPerfStudents = [
                <?php foreach ($classPerfChartData as $c) echo $c['students'] . ','; ?>
            ];
            const classPerfChart = new Chart(classPerfCtx, {
                type: 'bar',
                data: {
                    labels: classPerfLabels,
                    datasets: [
                        {
                            label: 'Average Score (%)',
                            data: classPerfScores,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2,
                            borderRadius: 8
                        },
                        {
                            label: 'Students',
                            data: classPerfStudents,
                            backgroundColor: 'rgba(16, 185, 129, 0.5)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2,
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // Pass/Fail Pie Chart
            const passFailCtx = document.getElementById('passFailChart').getContext('2d');
            const passFailChart = new Chart(passFailCtx, {
                type: 'pie',
                data: {
                    labels: ['Passed (>=75%)', 'Failed (<75%)'],
                    datasets: [{
                        data: [
                            <?php echo $passFail['passed']; ?>,
                            <?php echo $passFail['failed']; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(16, 185, 129, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
    </script>
</body>
</html>