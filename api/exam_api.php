<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear output buffers to prevent corrupt JSON responses
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

// Global Error Handler to catch any PHP errors/warnings and return JSON
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});

require_once '../config/database.php';

// Safe auth check: send JSON on failure instead of HTML redirects
if (file_exists('../includes/auth_check.php')) {
    require_once '../includes/auth_check.php';
}

$db = Database::getInstance()->getConnection();

function ensureExamTables(PDO $db): void {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS exams (
            exam_id INT AUTO_INCREMENT PRIMARY KEY, 
            teacher_id INT NOT NULL,
            exam_title VARCHAR(255) NOT NULL, 
            school_name VARCHAR(255) NOT NULL DEFAULT '',
            department_subtitle VARCHAR(255) NOT NULL DEFAULT '', 
            duration_minutes INT NOT NULL DEFAULT 60,
            is_deployed TINYINT(1) NOT NULL DEFAULT 0,
            start_time DATETIME NULL, 
            end_time DATETIME NULL, 
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (teacher_id), 
            CONSTRAINT fk_exams_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $checkCol = $db->query("SHOW COLUMNS FROM exams LIKE 'is_deployed'");
            if ($checkCol && $checkCol->rowCount() === 0) {
                $db->exec("ALTER TABLE exams ADD COLUMN is_deployed TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_minutes");
            }
        } catch (PDOException $e) {
            // Ignored if column already exists
        }

        $db->exec("CREATE TABLE IF NOT EXISTS exam_classes (
            exam_id INT NOT NULL, 
            class_id INT NOT NULL, 
            PRIMARY KEY (exam_id, class_id),
            CONSTRAINT fk_exam_classes_exam FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_classes_class FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS exam_sections (
            section_id INT AUTO_INCREMENT PRIMARY KEY, 
            exam_id INT NOT NULL, 
            section_type ENUM('multiple_choice','true_false','identification','matching') NOT NULL,
            section_label VARCHAR(120) NOT NULL, 
            instructions TEXT NULL, 
            sort_order INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_exam_sections_exam FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $checkSecSort = $db->query("SHOW COLUMNS FROM exam_sections LIKE 'sort_order'");
            if ($checkSecSort && $checkSecSort->rowCount() === 0) {
                $db->exec("ALTER TABLE exam_sections ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER instructions");
            }
        } catch (PDOException $e) {
            // Ignored if column exists
        }

        $db->exec("CREATE TABLE IF NOT EXISTS exam_questions (
            exam_question_id INT AUTO_INCREMENT PRIMARY KEY, 
            section_id INT NOT NULL, 
            question_text TEXT NOT NULL,
            choices_json TEXT NULL, 
            correct_answer TEXT NOT NULL, 
            sort_order INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_exam_questions_section FOREIGN KEY (section_id) REFERENCES exam_sections(section_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Schema sync: ensure legacy 'choices' column renames to 'choices_json'
        try {
            $checkChoices = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'choices'");
            $checkChoicesJson = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'choices_json'");
            
            if ($checkChoices && $checkChoices->rowCount() > 0 && ($checkChoicesJson && $checkChoicesJson->rowCount() === 0)) {
                $db->exec("ALTER TABLE exam_questions CHANGE COLUMN choices choices_json TEXT NULL");
            }
        } catch (PDOException $e) {
            // Ignored if column mapping is already set
        }

        try {
            $checkQuesSort = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'sort_order'");
            if ($checkQuesSort && $checkQuesSort->rowCount() === 0) {
                $db->exec("ALTER TABLE exam_questions ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER correct_answer");
            }
        } catch (PDOException $e) {
            // Ignored if column exists
        }

        $db->exec("CREATE TABLE IF NOT EXISTS exam_results (
            exam_result_id INT AUTO_INCREMENT PRIMARY KEY, 
            exam_id INT NOT NULL, 
            student_id INT NOT NULL,
            score INT NOT NULL, 
            total_questions INT NOT NULL, 
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (exam_id, student_id), 
            CONSTRAINT fk_exam_results_exam FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_results_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS active_exam_sessions (
            session_id INT AUTO_INCREMENT PRIMARY KEY,
            exam_id INT NOT NULL,
            student_id INT NOT NULL,
            last_heartbeat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unq_exam_student (exam_id, student_id),
            CONSTRAINT fk_active_sessions_exam FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
            CONSTRAINT fk_active_sessions_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        throw new Exception("Database Table Initialization Failed: " . $e->getMessage());
    }
}

function input(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $data = $_POST;
    if (empty($data) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
    }
    $cached = $data;
    return $cached;
}

function examPayload(PDO $db, int $examId): ?array {
    $exam = $db->prepare('SELECT exam_id, exam_title, school_name, department_subtitle, duration_minutes, is_deployed, start_time, end_time FROM exams WHERE exam_id = ?');
    $exam->execute([$examId]); 
    $data = $exam->fetch(PDO::FETCH_ASSOC);
    if (!$data) return null;

    $classStmt = $db->prepare('SELECT class_id FROM exam_classes WHERE exam_id = ?');
    $classStmt->execute([$examId]);
    $data['class_ids'] = array_map('intval', array_column($classStmt->fetchAll(PDO::FETCH_ASSOC), 'class_id'));

    // 1. Detect sorting column for exam_sections
    $hasSecSort = false;
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM exam_sections LIKE 'sort_order'");
        $hasSecSort = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasSecSort = false;
    }
    $secOrderSql = $hasSecSort ? 'ORDER BY sort_order ASC, section_id ASC' : 'ORDER BY section_id ASC';

    $sections = $db->prepare("SELECT section_id, section_type, section_label, instructions FROM exam_sections WHERE exam_id = ? {$secOrderSql}");
    $sections->execute([$examId]); 
    $data['sections'] = $sections->fetchAll(PDO::FETCH_ASSOC);

    // 2. Detect actual Primary Key column for exam_questions to prevent Error 1054
    $qPkCol = 'section_id'; // Safe fallback
    try {
        $cols = $db->query("SHOW COLUMNS FROM exam_questions")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('exam_question_id', $cols, true)) {
            $qPkCol = 'exam_question_id';
        } elseif (in_array('question_id', $cols, true)) {
            $qPkCol = 'question_id';
        } elseif (in_array('id', $cols, true)) {
            $qPkCol = 'id';
        }
    } catch (Exception $e) {
        // Fallback remains section_id
    }

    // 3. Detect sorting column for exam_questions
    $hasQuesSort = false;
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'sort_order'");
        $hasQuesSort = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasQuesSort = false;
    }
    $quesOrderSql = $hasQuesSort ? "ORDER BY sort_order ASC, {$qPkCol} ASC" : "ORDER BY {$qPkCol} ASC";

    // 4. Detect choices column name
    $choicesCol = 'choices_json';
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'choices_json'");
        if (!$colCheck || $colCheck->rowCount() === 0) {
            $choicesCol = 'choices';
        }
    } catch (Exception $e) {
        $choicesCol = 'choices';
    }

    $questions = $db->prepare("SELECT question_text, {$choicesCol} AS choices_json, correct_answer FROM exam_questions WHERE section_id = ? {$quesOrderSql}");
    
    foreach ($data['sections'] as &$section) {
        $questions->execute([$section['section_id']]);
        $section['questions'] = array_map(function ($q) {
            $rawChoices = $q['choices_json'] ?? '[]';
            $parsedChoices = is_string($rawChoices) ? (json_decode($rawChoices, true) ?: []) : [];
            return [
                'questionText' => $q['question_text'], 
                'choices' => $parsedChoices, 
                'correctAnswer' => $q['correct_answer']
            ];
        }, $questions->fetchAll(PDO::FETCH_ASSOC));
        unset($section['section_id']);
    }
    
    return $data;
}

ensureExamTables($db);
$action = $_GET['action'] ?? (input()['action'] ?? '');

/* UNITY CLIENT: GET DEPLOYED EXAM FOR STUDENT */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'load_for_student') {
    $studentId = (int)($_GET['student_id'] ?? 0);

    $stmt = $db->prepare("
        SELECT e.exam_id 
        FROM exams e 
        JOIN exam_classes ec ON ec.exam_id = e.exam_id 
        JOIN class_students cs ON cs.class_id = ec.class_id 
        WHERE cs.student_id = ? 
          AND e.is_deployed = 1
          AND (e.start_time IS NULL OR e.start_time <= NOW()) 
          AND (e.end_time IS NULL OR e.end_time >= NOW()) 
        ORDER BY e.start_time DESC, e.exam_id DESC LIMIT 1
    ");
    $stmt->execute([$studentId]); 
    $examId = (int)$stmt->fetchColumn();

    if (!$examId) {
        echo json_encode([
            'success' => false, 
            'exam' => null, 
            'error' => 'No active live exam assigned to this student.'
        ]);
        exit;
    }

    $checkSubmitted = $db->prepare("
        SELECT exam_result_id 
        FROM exam_results 
        WHERE exam_id = ? AND student_id = ?
        LIMIT 1
    ");
    $checkSubmitted->execute([$examId, $studentId]);

    if ($checkSubmitted->fetch()) {
        echo json_encode([
            'success' => false,
            'exam' => null,
            'error' => 'You have already completed and submitted this exam.'
        ]);
        exit;
    }

    $payload = examPayload($db, $examId);

    if ($payload && $studentId) {
        $sessStmt = $db->prepare("
            INSERT INTO active_exam_sessions (exam_id, student_id, last_heartbeat) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE last_heartbeat = NOW()
        ");
        $sessStmt->execute([$examId, $studentId]);
    }

    echo json_encode(['success' => (bool)$payload, 'exam' => $payload, 'error' => $payload ? null : 'Failed to parse exam payload.']); 
    exit;
}

/* UNITY CLIENT: SESSION HEARTBEAT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'heartbeat') {
    $data = input();
    $examId = (int)($data['exam_id'] ?? 0);
    $studentId = (int)($data['student_id'] ?? 0);

    if ($examId && $studentId) {
        $stmt = $db->prepare("
            INSERT INTO active_exam_sessions (exam_id, student_id, last_heartbeat) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE last_heartbeat = NOW()
        ");
        $stmt->execute([$examId, $studentId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
    exit;
}

/* UNITY CLIENT: SUBMIT EXAM RESULT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_result') {
    $data = input(); 
    $examId = (int)($data['exam_id'] ?? 0); 
    $studentId = (int)($data['student_id'] ?? 0);
    $score = (int)($data['score'] ?? 0); 
    $total = (int)($data['total_questions'] ?? 0);

    $allowed = $db->prepare('SELECT 1 FROM exam_classes ec JOIN class_students cs ON cs.class_id = ec.class_id WHERE ec.exam_id = ? AND cs.student_id = ?');
    $allowed->execute([$examId, $studentId]);

    if (!$allowed->fetch() || $total < 1) { 
        http_response_code(422); 
        echo json_encode(['success' => false, 'error' => 'Student not enrolled in target exam class.']); 
        exit; 
    }

    $stmt = $db->prepare('INSERT INTO exam_results (exam_id, student_id, score, total_questions) VALUES (?, ?, ?, ?)'); 
    $stmt->execute([$examId, $studentId, $score, $total]);

    $delSess = $db->prepare('DELETE FROM active_exam_sessions WHERE exam_id = ? AND student_id = ?');
    $delSess->execute([$examId, $studentId]);

    echo json_encode(['success' => true]); 
    exit;
}

/* TEACHER AUTHENTICATION CHECK */
if (!isset($_SESSION['teacher_id'])) {
    if (function_exists('checkTeacherAuth')) {
        checkTeacherAuth();
    }
    if (!isset($_SESSION['teacher_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please log in as a teacher.']);
        exit;
    }
}

$teacherId = (int)$_SESSION['teacher_id'];

/* LIST EXAMS */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $stmt = $db->prepare('
        SELECT e.*, 
               GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ", ") AS classes,
               GROUP_CONCAT(DISTINCT c.class_id) AS class_ids
        FROM exams e 
        LEFT JOIN exam_classes ec ON ec.exam_id = e.exam_id 
        LEFT JOIN classes c ON c.class_id = ec.class_id 
        WHERE e.teacher_id = ? 
        GROUP BY e.exam_id 
        ORDER BY e.exam_id DESC
    '); 
    $stmt->execute([$teacherId]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($exams as &$e) {
        $e['class_ids'] = $e['class_ids'] ? array_map('intval', explode(',', $e['class_ids'])) : [];
    }

    echo json_encode(['success' => true, 'exams' => $exams]); 
    exit;
}

/* LIVE MONITORING DATA FEED */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'live_monitoring') {
    $stmt = $db->prepare("
        SELECT 
            e.exam_id,
            e.exam_title,
            s.student_id,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            c.class_name,
            er.score,
            er.total_questions,
            er.submitted_at AS end_time,
            CASE 
                WHEN er.exam_result_id IS NOT NULL THEN 'completed'
                WHEN aes.session_id IS NOT NULL AND aes.last_heartbeat >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'taking'
                ELSE 'not_started'
            END AS status
        FROM exams e
        JOIN exam_classes ec ON e.exam_id = ec.exam_id
        JOIN classes c ON ec.class_id = c.class_id
        JOIN class_students cs ON c.class_id = cs.class_id
        JOIN students s ON cs.student_id = s.student_id
        LEFT JOIN exam_results er ON er.exam_id = e.exam_id AND er.student_id = s.student_id
        LEFT JOIN active_exam_sessions aes ON aes.exam_id = e.exam_id AND aes.student_id = s.student_id
        WHERE e.teacher_id = ? AND e.is_deployed = 1
        ORDER BY er.submitted_at DESC, s.last_name ASC
    ");
    $stmt->execute([$teacherId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'sessions' => $sessions]);
    exit;
}

/* QUESTION BANK */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'question_bank') {
    $stmt = $db->prepare("SELECT q.question_id, q.question_text, q.question_type, l.lesson_title, c.chapter_title,
        qc.choice_text, qc.is_correct
        FROM questions_master q
        JOIN lessons l ON l.lesson_id = q.lesson_id
        JOIN chapters c ON c.chapter_id = l.chapter_id
        LEFT JOIN quiz_choices qc ON qc.question_id = q.question_id
        WHERE q.visibility = 'public' OR q.created_by_teacher_id = ?
        ORDER BY c.chapter_order, l.lesson_order, q.question_id, qc.choice_id");
    $stmt->execute([$teacherId]);
    $questions = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['question_id'];
        if (!isset($questions[$id])) {
            $questions[$id] = [
                'question_id' => $id, 
                'question_text' => $row['question_text'], 
                'question_type' => $row['question_type'], 
                'lesson_title' => $row['lesson_title'], 
                'chapter_title' => $row['chapter_title'], 
                'choices' => [], 
                'correct_answer' => ''
            ];
        }
        if ($row['choice_text'] !== null) {
            $questions[$id]['choices'][] = $row['choice_text'];
            if ((int)$row['is_correct'] === 1) $questions[$id]['correct_answer'] = $row['choice_text'];
        }
    }
    echo json_encode(['success' => true, 'questions' => array_values($questions)]); 
    exit;
}

/* GET SINGLE EXAM */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    $id = (int)($_GET['exam_id'] ?? 0); 
    $own = $db->prepare('SELECT exam_id FROM exams WHERE exam_id = ? AND teacher_id = ?'); 
    $own->execute([$id, $teacherId]);
    $isOwner = (bool)$own->fetch();
    echo json_encode(['success' => $isOwner, 'exam' => $isOwner ? examPayload($db, $id) : null]); 
    exit;
}

/* SAVE / DELETE EXAM */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save', 'delete'], true)) {
    $data = input(); 
    $id = (int)($data['exam_id'] ?? 0);

    if ($action === 'delete') { 
        $stmt = $db->prepare('DELETE FROM exams WHERE exam_id = ? AND teacher_id = ?'); 
        $stmt->execute([$id, $teacherId]); 
        echo json_encode(['success' => $stmt->rowCount() > 0]); 
        exit; 
    }

    $title = trim($data['exam_title'] ?? ''); 
    $classes = array_values(array_unique(array_map('intval', $data['class_ids'] ?? []))); 
    $sections = $data['sections'] ?? [];
    $is_deployed = (int)($data['is_deployed'] ?? 0);
    $force_override = (bool)($data['force_override'] ?? false);

    if (!$title || !$classes || !is_array($sections) || !$sections) { 
        http_response_code(422); 
        echo json_encode(['success' => false, 'error' => 'Title, target class, and at least one section are required.']); 
        exit; 
    }

    $classCheck = $db->prepare('SELECT class_id FROM classes WHERE class_id = ? AND teacher_id = ?'); 
    foreach ($classes as $class) {
        $classCheck->execute([$class, $teacherId]);
        if (!$classCheck->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid or unauthorized class selection.']);
            exit;
        }
    }

    /* BACKEND DUPLICATE LIVE DEPLOYMENT VALIDATION */
    if ($is_deployed === 1) {
        $inClause = implode(',', array_map('intval', $classes));
        $stmt = $db->prepare("
            SELECT DISTINCT e.exam_id, e.exam_title 
            FROM exams e
            JOIN exam_classes ec ON e.exam_id = ec.exam_id
            WHERE e.teacher_id = ? 
              AND e.is_deployed = 1 
              AND e.exam_id != ? 
              AND ec.class_id IN ($inClause)
        ");
        $stmt->execute([$teacherId, $id]);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($conflicts) && !$force_override) {
            $conflictNames = array_column($conflicts, 'exam_title');
            echo json_encode([
                'success' => false,
                'conflict' => true,
                'error' => 'Active live exam(s): "' . implode(', ', $conflictNames) . '" are already deployed to this class.'
            ]);
            exit;
        }

        if (!empty($conflicts) && $force_override) {
            $conflictIds = implode(',', array_map('intval', array_column($conflicts, 'exam_id')));
            $db->exec("UPDATE exams SET is_deployed = 0 WHERE exam_id IN ($conflictIds)");
        }
    }

    $db->beginTransaction(); 
    try {
        if ($id) {
            $own = $db->prepare('SELECT exam_id FROM exams WHERE exam_id = ? AND teacher_id = ?');
            $own->execute([$id, $teacherId]);
            if (!$own->fetch()) throw new Exception('Exam not found.');

            $update = $db->prepare('UPDATE exams SET exam_title = ?, school_name = ?, department_subtitle = ?, duration_minutes = ?, is_deployed = ?, start_time = ?, end_time = ? WHERE exam_id = ?');
            $update->execute([
                $title, 
                trim($data['school_name'] ?? ''), 
                trim($data['department_subtitle'] ?? ''), 
                max(1, (int)($data['duration_minutes'] ?? 60)), 
                $is_deployed,
                !empty($data['start_time']) ? $data['start_time'] : null, 
                !empty($data['end_time']) ? $data['end_time'] : null, 
                $id
            ]);

            $db->prepare('DELETE FROM exam_sections WHERE exam_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM exam_classes WHERE exam_id = ?')->execute([$id]);
        } else {
            $insert = $db->prepare('INSERT INTO exams (teacher_id, exam_title, school_name, department_subtitle, duration_minutes, is_deployed, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $teacherId, 
                $title, 
                trim($data['school_name'] ?? ''), 
                trim($data['department_subtitle'] ?? ''), 
                max(1, (int)($data['duration_minutes'] ?? 60)), 
                $is_deployed,
                !empty($data['start_time']) ? $data['start_time'] : null, 
                !empty($data['end_time']) ? $data['end_time'] : null
            ]);
            $id = (int)$db->lastInsertId();
        }

        $ec = $db->prepare('INSERT INTO exam_classes (exam_id, class_id) VALUES (?, ?)');
        foreach ($classes as $class) $ec->execute([$id, $class]);

        // Check if sort_order columns exist before attempting inserts
        $hasSecSort = false;
        try {
            $colCheck = $db->query("SHOW COLUMNS FROM exam_sections LIKE 'sort_order'");
            $hasSecSort = $colCheck && $colCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasSecSort = false;
        }

        $hasQuesSort = false;
        try {
            $colCheck = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'sort_order'");
            $hasQuesSort = $colCheck && $colCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasQuesSort = false;
        }

        // Check target choices column name
        $choicesCol = 'choices_json';
        try {
            $colCheck = $db->query("SHOW COLUMNS FROM exam_questions LIKE 'choices_json'");
            if (!$colCheck || $colCheck->rowCount() === 0) {
                $choicesCol = 'choices';
            }
        } catch (Exception $e) {
            $choicesCol = 'choices';
        }

        if ($hasSecSort) {
            $si = $db->prepare('INSERT INTO exam_sections (exam_id, section_type, section_label, instructions, sort_order) VALUES (?, ?, ?, ?, ?)');
        } else {
            $si = $db->prepare('INSERT INTO exam_sections (exam_id, section_type, section_label, instructions) VALUES (?, ?, ?, ?)');
        }

        if ($hasQuesSort) {
            $qi = $db->prepare("INSERT INTO exam_questions (section_id, question_text, {$choicesCol}, correct_answer, sort_order) VALUES (?, ?, ?, ?, ?)");
        } else {
            $qi = $db->prepare("INSERT INTO exam_questions (section_id, question_text, {$choicesCol}, correct_answer) VALUES (?, ?, ?, ?)");
        }

        foreach ($sections as $sOrder => $s) {
            $type = $s['section_type'] ?? '';
            if (!in_array($type, ['multiple_choice', 'true_false', 'identification', 'matching'], true)) {
                throw new Exception('Invalid section type.');
            }

            if ($hasSecSort) {
                $si->execute([$id, $type, trim($s['section_label'] ?? 'Section'), trim($s['instructions'] ?? ''), $sOrder]);
            } else {
                $si->execute([$id, $type, trim($s['section_label'] ?? 'Section'), trim($s['instructions'] ?? '')]);
            }
            $sectionId = (int)$db->lastInsertId();

            foreach (($s['questions'] ?? []) as $qOrder => $q) {
                $text = trim($q['questionText'] ?? '');
                $answer = trim($q['correctAnswer'] ?? '');
                if (!$text || $answer === '') throw new Exception('Every question requires text and an answer key.');

                $choices = array_values(array_filter($q['choices'] ?? [], fn($v) => trim((string)$v) !== ''));
                
                if ($hasQuesSort) {
                    $qi->execute([$sectionId, $text, json_encode($choices), $answer, $qOrder]);
                } else {
                    $qi->execute([$sectionId, $text, json_encode($choices), $answer]);
                }
            }
        }

        $db->commit(); 
        echo json_encode(['success' => true, 'exam_id' => $id]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(422); 
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } 
    exit;
}

http_response_code(400); 
echo json_encode(['success' => false, 'error' => 'Unknown request handler.']);