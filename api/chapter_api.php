<?php
/**
 * Chapter API Handler
 * Handles CRUD operations for chapters, lessons, and topics
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherOrAdminAuthAPI();

$db = Database::getInstance()->getConnection();

function ensureClassChapterAccessTable($db) {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS class_chapter_access (
            class_id INT NOT NULL,
            chapter_id INT NOT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, chapter_id),
            KEY idx_class_chapter_access_chapter (chapter_id),
            CONSTRAINT fk_class_chapter_access_class FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_class_chapter_access_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensureClassExamAccessTable($db) {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS class_exam_access (
            class_id INT NOT NULL,
            exam_key VARCHAR(64) NOT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, exam_key),
            KEY idx_class_exam_access_key (exam_key),
            CONSTRAINT fk_class_exam_access_class FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

ensureClassChapterAccessTable($db);
ensureClassExamAccessTable($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$is_teacher = (($_SESSION['role'] ?? '') === 'teacher');

if ($is_admin) {
    $admin_allowed_actions = [
        'create_chapter',
        'get_chapter',
        'update_chapter',
        'delete_chapter',
        'get_chapters',
        'create_lesson',
        'get_lesson',
        'update_lesson',
        'delete_lesson',
        'get_lessons'
    ];

    if (!in_array($action, $admin_allowed_actions, true)) {
        jsonResponse(false, 'Unauthorized action for admin role. Only chapter and lesson management is allowed.');
    }
}

if ($is_teacher) {
    $teacher_disallowed_actions = [
        'create_chapter',
        'update_chapter',
        'delete_chapter',
        'create_lesson',
        'update_lesson',
        'delete_lesson'
    ];

    if (in_array($action, $teacher_disallowed_actions, true)) {
        jsonResponse(false, 'Unauthorized action for teacher role. Chapter and lesson management are admin-only.');
    }
}

try {
    switch ($action) {
        // ============ CHAPTER OPERATIONS ============
        case 'create_chapter':
            createChapter($db);
            break;
        case 'get_chapter':
            getChapter($db);
            break;
        case 'update_chapter':
            updateChapter($db);
            break;
        case 'delete_chapter':
            deleteChapter($db);
            break;
        case 'get_chapters':
            getChapters($db);
            break;
        case 'set_chapter_lock':
            setChapterLock($db);
            break;
        case 'set_exam_lock':
            setExamLock($db);
            break;

        // ============ LESSON OPERATIONS ============
        case 'create_lesson':
            createLesson($db);
            break;
        case 'get_lesson':
            getLesson($db);
            break;
        case 'update_lesson':
            updateLesson($db);
            break;
        case 'delete_lesson':
            deleteLesson($db);
            break;
        case 'get_lessons':
            getLessons($db);
            break;

        // ============ TOPIC OPERATIONS ============
        case 'create_topic':
            createTopic($db);
            break;
        case 'get_topic':
            getTopic($db);
            break;
        case 'update_topic':
            updateTopic($db);
            break;
        case 'delete_topic':
            deleteTopic($db);
            break;
        case 'get_topics':
            getTopics($db);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}

// ============ CHAPTER FUNCTIONS ============

function createChapter($db) {
    $title = trim($_POST['chapter_title'] ?? '');
    $order = intval($_POST['chapter_order'] ?? 1);

    if (empty($title)) {
        jsonResponse(false, 'Chapter title is required');
        return;
    }

    // Check for duplicate chapter title
    $stmt = $db->prepare("SELECT COUNT(*) FROM chapters WHERE chapter_title = ?");
    $stmt->execute([$title]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Chapter title must be unique. "' . $title . '" already exists.');
        return;
    }

    // Check for duplicate chapter order
    $stmt = $db->prepare("SELECT COUNT(*) FROM chapters WHERE chapter_order = ?");
    $stmt->execute([$order]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Chapter order must be unique. Order ' . $order . ' is already in use.');
        return;
    }

    $stmt = $db->prepare("INSERT INTO chapters (chapter_title, chapter_order) VALUES (?, ?)");
    $stmt->execute([$title, $order]);

    jsonResponse(true, 'Chapter created successfully', ['chapter_id' => $db->lastInsertId()]);
}

function getChapter($db) {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM chapters WHERE chapter_id = ?");
    $stmt->execute([$id]);
    $chapter = $stmt->fetch();
    if ($chapter) {
        jsonResponse(true, 'Chapter found', $chapter);
    } else {
        jsonResponse(false, 'Chapter not found');
    }
}

function updateChapter($db) {
    $id = intval($_POST['chapter_id'] ?? 0);
    $title = trim($_POST['chapter_title'] ?? '');
    $order = intval($_POST['chapter_order'] ?? 1);

    if (!$id || empty($title)) {
        jsonResponse(false, 'All fields are required');
        return;
    }

    // Check for duplicate chapter title (excluding current chapter)
    $stmt = $db->prepare("SELECT COUNT(*) FROM chapters WHERE chapter_title = ? AND chapter_id != ?");
    $stmt->execute([$title, $id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Chapter title must be unique. "' . $title . '" already exists.');
        return;
    }

    // Check for duplicate chapter order (excluding current chapter)
    $stmt = $db->prepare("SELECT COUNT(*) FROM chapters WHERE chapter_order = ? AND chapter_id != ?");
    $stmt->execute([$order, $id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Chapter order must be unique. Order ' . $order . ' is already in use.');
        return;
    }

    $stmt = $db->prepare("UPDATE chapters SET chapter_title = ?, chapter_order = ? WHERE chapter_id = ?");
    $stmt->execute([$title, $order, $id]);

    jsonResponse(true, 'Chapter updated successfully');
}

function deleteChapter($db) {
    $id = intval($_POST['chapter_id'] ?? 0);

    if (!$id) {
        jsonResponse(false, 'Chapter ID is required');
        return;
    }

    $stmt = $db->prepare("DELETE FROM chapters WHERE chapter_id = ?");
    $stmt->execute([$id]);

    jsonResponse(true, 'Chapter deleted successfully');
}

function getChapters($db) {
    // Return chapters; include related stage/game info when available but do not require filtering by stage_id
    $sql = "SELECT c.*, s.stage_name, g.game_title
            FROM chapters c
            LEFT JOIN stages s ON c.stage_id = s.stage_id
            LEFT JOIN games g ON s.game_id = g.game_id
            ORDER BY g.game_title, s.stage_order, c.chapter_order";

    $stmt = $db->query($sql);
    jsonResponse(true, 'Chapters retrieved', $stmt->fetchAll());
}

function setChapterLock($db) {
    $teacher_id = intval($_SESSION['teacher_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $is_locked = intval($_POST['is_locked'] ?? 1) === 1 ? 1 : 0;

    if ($teacher_id <= 0 || $class_id <= 0 || $chapter_id <= 0) {
        jsonResponse(false, 'Section and chapter are required');
        return;
    }

    $class_stmt = $db->prepare(
        "SELECT c.class_id
         FROM classes c
         JOIN school_year sy ON sy.sy_id = c.sy_id
         WHERE c.class_id = ? AND c.teacher_id = ? AND sy.is_active = 1
         LIMIT 1"
    );
    $class_stmt->execute([$class_id, $teacher_id]);
    if (!$class_stmt->fetch()) {
        jsonResponse(false, 'Invalid section selected');
        return;
    }

    $check_stmt = $db->prepare("SELECT chapter_id FROM chapters WHERE chapter_id = ? LIMIT 1");
    $check_stmt->execute([$chapter_id]);
    if (!$check_stmt->fetch()) {
        jsonResponse(false, 'Chapter not found');
        return;
    }

    $stmt = $db->prepare(
        "INSERT INTO class_chapter_access (class_id, chapter_id, is_locked)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE is_locked = VALUES(is_locked), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$class_id, $chapter_id, $is_locked]);

    jsonResponse(true, $is_locked === 1 ? 'Chapter locked for this section.' : 'Chapter unlocked for this section.');
}

function setExamLock($db) {
    $teacher_id = intval($_SESSION['teacher_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $exam_key = trim($_POST['exam_key'] ?? 'post_chapter_4_exam');
    $is_locked = intval($_POST['is_locked'] ?? 1) === 1 ? 1 : 0;

    if ($teacher_id <= 0 || $class_id <= 0 || $exam_key === '') {
        jsonResponse(false, 'Section and exam key are required');
        return;
    }

    $class_stmt = $db->prepare(
        "SELECT c.class_id
         FROM classes c
         JOIN school_year sy ON sy.sy_id = c.sy_id
         WHERE c.class_id = ? AND c.teacher_id = ? AND sy.is_active = 1
         LIMIT 1"
    );
    $class_stmt->execute([$class_id, $teacher_id]);
    if (!$class_stmt->fetch()) {
        jsonResponse(false, 'Invalid section selected');
        return;
    }

    $stmt = $db->prepare(
        "INSERT INTO class_exam_access (class_id, exam_key, is_locked)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE is_locked = VALUES(is_locked), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$class_id, $exam_key, $is_locked]);

    jsonResponse(true, $is_locked === 1 ? 'Exam locked for this section.' : 'Exam unlocked for this section.');
}

// ============ LESSON FUNCTIONS ============

function createLesson($db) {
    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $title = trim($_POST['lesson_title'] ?? '');
    $order = intval($_POST['lesson_order'] ?? 1);

    if (!$chapter_id || empty($title)) {
        jsonResponse(false, 'Chapter and lesson title are required');
        return;
    }

    // Check if the order already exists for this chapter and find the next available order
    $stmt = $db->prepare("SELECT MAX(lesson_order) as max_order FROM lessons WHERE chapter_id = ?");
    $stmt->execute([$chapter_id]);
    $result = $stmt->fetch();
    $max_order = $result['max_order'] ?? 0;

    // If the requested order is already taken or less than/equal to existing orders, use the next available
    if ($order <= $max_order) {
        $order = $max_order + 1;
    }

    $stmt = $db->prepare("INSERT INTO lessons (chapter_id, lesson_title, lesson_order) VALUES (?, ?, ?)");
    $stmt->execute([$chapter_id, $title, $order]);

    jsonResponse(true, 'Lesson created successfully', ['lesson_id' => $db->lastInsertId()]);
}

function getLesson($db) {
    $id = intval($_GET['id'] ?? 0);
    
    $stmt = $db->prepare("
        SELECT l.*, c.chapter_title 
        FROM lessons l 
        JOIN chapters c ON l.chapter_id = c.chapter_id 
        WHERE l.lesson_id = ?
    ");
    $stmt->execute([$id]);
    $lesson = $stmt->fetch();

    if ($lesson) {
        jsonResponse(true, 'Lesson found', $lesson);
    } else {
        jsonResponse(false, 'Lesson not found');
    }
}

function updateLesson($db) {
    $id = intval($_POST['lesson_id'] ?? 0);
    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $title = trim($_POST['lesson_title'] ?? '');
    $order = intval($_POST['lesson_order'] ?? 1);

    if (!$id || !$chapter_id || empty($title)) {
        jsonResponse(false, 'All fields are required');
        return;
    }

    // Check if the order conflicts with other lessons in the same chapter (excluding this lesson)
    $stmt = $db->prepare("SELECT lesson_order FROM lessons WHERE chapter_id = ? AND lesson_id != ? ORDER BY lesson_order");
    $stmt->execute([$chapter_id, $id]);
    $existing_orders = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Find the next available order if the requested order is taken
    while (in_array($order, $existing_orders)) {
        $order++;
    }

    $stmt = $db->prepare("UPDATE lessons SET chapter_id = ?, lesson_title = ?, lesson_order = ? WHERE lesson_id = ?");
    $stmt->execute([$chapter_id, $title, $order, $id]);

    jsonResponse(true, 'Lesson updated successfully');
}

function deleteLesson($db) {
    $id = intval($_POST['lesson_id'] ?? 0);

    if (!$id) {
        jsonResponse(false, 'Lesson ID is required');
        return;
    }

    $stmt = $db->prepare("DELETE FROM lessons WHERE lesson_id = ?");
    $stmt->execute([$id]);

    jsonResponse(true, 'Lesson deleted successfully');
}

function getLessons($db) {
    $chapter_id = intval($_GET['chapter_id'] ?? 0);
    
    $sql = "SELECT l.*, c.chapter_title FROM lessons l JOIN chapters c ON l.chapter_id = c.chapter_id";
    
    if ($chapter_id) {
        $sql .= " WHERE l.chapter_id = ?";
        $stmt = $db->prepare($sql . " ORDER BY l.lesson_order");
        $stmt->execute([$chapter_id]);
    } else {
        $stmt = $db->query($sql . " ORDER BY l.lesson_order");
    }

    jsonResponse(true, 'Lessons retrieved', $stmt->fetchAll());
}

// ============ TOPIC FUNCTIONS ============

function createTopic($db) {
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $title = trim($_POST['topic_title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$lesson_id || empty($title)) {
        jsonResponse(false, 'Lesson and topic title are required');
        return;
    }

    $stmt = $db->prepare("INSERT INTO topics (lesson_id, topic_title, content) VALUES (?, ?, ?)");
    $stmt->execute([$lesson_id, $title, $content]);

    jsonResponse(true, 'Topic created successfully', ['topic_id' => $db->lastInsertId()]);
}

function getTopic($db) {
    $id = intval($_GET['id'] ?? 0);
    
    $stmt = $db->prepare("
        SELECT t.*, l.lesson_title 
        FROM topics t 
        JOIN lessons l ON t.lesson_id = l.lesson_id 
        WHERE t.topic_id = ?
    ");
    $stmt->execute([$id]);
    $topic = $stmt->fetch();

    if ($topic) {
        jsonResponse(true, 'Topic found', $topic);
    } else {
        jsonResponse(false, 'Topic not found');
    }
}

function updateTopic($db) {
    $id = intval($_POST['topic_id'] ?? 0);
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $title = trim($_POST['topic_title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$id || !$lesson_id || empty($title)) {
        jsonResponse(false, 'All fields are required');
        return;
    }

    $stmt = $db->prepare("UPDATE topics SET lesson_id = ?, topic_title = ?, content = ? WHERE topic_id = ?");
    $stmt->execute([$lesson_id, $title, $content, $id]);

    jsonResponse(true, 'Topic updated successfully');
}

function deleteTopic($db) {
    $id = intval($_POST['topic_id'] ?? 0);

    if (!$id) {
        jsonResponse(false, 'Topic ID is required');
        return;
    }

    $stmt = $db->prepare("DELETE FROM topics WHERE topic_id = ?");
    $stmt->execute([$id]);

    jsonResponse(true, 'Topic deleted successfully');
}

function getTopics($db) {
    $lesson_id = intval($_GET['lesson_id'] ?? 0);
    
    $sql = "SELECT t.*, l.lesson_title FROM topics t JOIN lessons l ON t.lesson_id = l.lesson_id";
    
    if ($lesson_id) {
        $sql .= " WHERE t.lesson_id = ?";
        $stmt = $db->prepare($sql . " ORDER BY t.topic_id");
        $stmt->execute([$lesson_id]);
    } else {
        $stmt = $db->query($sql . " ORDER BY t.topic_id");
    }

    jsonResponse(true, 'Topics retrieved', $stmt->fetchAll());
}

// ============ HELPER FUNCTIONS ============

function jsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}
?>
