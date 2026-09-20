<?php
/**
 * Question API Handler
 * Handles CRUD operations for questions and choices
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Restore session from JWT if needed (supports both browser sessions and Bearer tokens)
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromJWT();
}

// Enforce authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['teacher', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$teacher_id = $_SESSION['teacher_id'] ?? null;
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Enforce RBAC for teachers on write actions
$writeActions = ['create_question', 'update_question', 'delete_question', 'toggle_archive_question', 'add_choice', 'update_choice', 'delete_choice', 'bulk_create_questions'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if (!$isAdmin && in_array($action, $writeActions, true)) {
    if (!hasTeacherPermission($db, 'can_manage_questions')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: missing can_manage_questions permission']);
        exit;
    }
}

try {
    switch ($action) {
        // QUESTION OPERATIONS
        case 'create_question':
            createQuestion($db, $teacher_id, $isAdmin);
            break;
        case 'get_question':
            getQuestion($db);
            break;
        case 'update_question':
            updateQuestion($db, $teacher_id, $isAdmin);
            break;
        case 'delete_question':
            toggleArchiveQuestion($db, $teacher_id, $isAdmin);
            break;
        case 'toggle_archive_question':
            toggleArchiveQuestion($db, $teacher_id, $isAdmin);
            break;
        case 'get_questions':
            getQuestions($db, $teacher_id, $isAdmin);
            break;

        // CHOICE OPERATIONS
        case 'add_choice':
            addChoice($db, $teacher_id, $isAdmin);
            break;
        case 'update_choice':
            updateChoice($db, $teacher_id, $isAdmin);
            break;
        case 'delete_choice':
            deleteChoice($db, $teacher_id, $isAdmin);
            break;
        case 'get_choices':
            getChoices($db);
            break;

        // BULK
        case 'bulk_create_questions':
            bulkCreateQuestions($db, $teacher_id, $isAdmin);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}

// ---------- FUNCTIONS ----------

function createQuestion($db, $teacher_id, $isAdmin = false) {
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $question_type = trim($_POST['question_type'] ?? 'mcq');
    $visibility = trim($_POST['visibility'] ?? 'private');
    $is_ai_generated = intval($_POST['is_ai_generated'] ?? 0);
    $choices = $_POST['choices'] ?? [];
    $correct_choice = $_POST['correct_choice'] ?? null;
    $tf_answer = $_POST['tf_answer'] ?? 'true';

    if (!$lesson_id || empty($question_text)) {
        jsonResponse(false, 'Lesson and question text are required');
    }

    // Determine author
    if ($isAdmin && isset($_POST['author_teacher_id']) && intval($_POST['author_teacher_id']) > 0) {
        $author_teacher_id = intval($_POST['author_teacher_id']);
    } else {
        $author_teacher_id = $teacher_id;
    }

    // Ensure author_teacher_id is set
    if (empty($author_teacher_id)) {
        $stmt = $db->query("SELECT t.teacher_id FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active' LIMIT 1");
        $row = $stmt->fetch();
        if ($row && !empty($row['teacher_id'])) {
            $author_teacher_id = $row['teacher_id'];
        } else {
            jsonResponse(false, 'Author teacher id is required when creating questions as admin');
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO questions_master (lesson_id, question_text, question_type, created_by_teacher_id, visibility, is_ai_generated) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$lesson_id, $question_text, $question_type, $author_teacher_id, $visibility, $is_ai_generated]);
        $question_id = $db->lastInsertId();

        if ($question_type === 'mcq') {
            $validChoices = array_filter($choices, function($c) { return trim($c) !== ''; });
            if (count($validChoices) < 4) {
                $db->rollBack();
                jsonResponse(false, 'Multiple choice questions must have at least 4 answer choices');
            }

            foreach ($choices as $idx => $choice_text) {
                if (trim($choice_text) === '') continue;
                $is_correct = ($idx == intval($correct_choice)) ? 1 : 0;
                $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, trim($choice_text), $is_correct]);
            }
        } elseif ($question_type === 'true_false') {
            $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$question_id, 'True', $tf_answer === 'true' ? 1 : 0]);
            $stmt->execute([$question_id, 'False', $tf_answer === 'false' ? 1 : 0]);
        } elseif ($question_type === 'short_answer') {
            $short_answer = trim($_POST['short_answer'] ?? '');
            if (!empty($short_answer)) {
                $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, 1)");
                $stmt->execute([$question_id, $short_answer]);
            }
        }

        $db->commit();
        jsonResponse(true, 'Question created successfully', ['question_id' => $question_id]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Create failed: ' . $e->getMessage());
    }
}

function getQuestion($db) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Question ID required');

    $stmt = $db->prepare("SELECT q.*, l.lesson_title, c.chapter_title FROM questions_master q JOIN lessons l ON q.lesson_id = l.lesson_id JOIN chapters c ON l.chapter_id = c.chapter_id WHERE q.question_id = ?");
    $stmt->execute([$id]);
    $question = $stmt->fetch();

    if ($question) {
        $choicesStmt = $db->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_id");
        $choicesStmt->execute([$id]);
        $question['choices'] = $choicesStmt->fetchAll();
        jsonResponse(true, 'Question found', $question);
    } else {
        jsonResponse(false, 'Question not found');
    }
}

function updateQuestion($db, $teacher_id, $isAdmin = false) {
    $id = intval($_POST['question_id'] ?? 0);
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $question_type = trim($_POST['question_type'] ?? 'mcq');
    $visibility = trim($_POST['visibility'] ?? 'private');
    $choices = $_POST['choices'] ?? [];
    $correct_choice = $_POST['correct_choice'] ?? null;

    if (!$id || !$lesson_id || empty($question_text)) jsonResponse(false, 'All fields are required');

    $db->beginTransaction();
    try {
        if ($isAdmin) {
            $stmt = $db->prepare("UPDATE questions_master SET lesson_id = ?, question_text = ?, question_type = ?, visibility = ? WHERE question_id = ?");
            $stmt->execute([$lesson_id, $question_text, $question_type, $visibility, $id]);
        } else {
            $stmt = $db->prepare("UPDATE questions_master SET lesson_id = ?, question_text = ?, question_type = ?, visibility = ? WHERE question_id = ? AND created_by_teacher_id = ?");
            $stmt->execute([$lesson_id, $question_text, $question_type, $visibility, $id, $teacher_id]);
        }

        if ($stmt->rowCount() === 0 && !$isAdmin) {
            $checkStmt = $db->prepare("SELECT question_id FROM questions_master WHERE question_id = ? AND created_by_teacher_id = ?");
            $checkStmt->execute([$id, $teacher_id]);
            if (!$checkStmt->fetch()) {
                $db->rollBack();
                jsonResponse(false, 'Question not found or permission denied');
            }
        }

        $stmt = $db->prepare("DELETE FROM quiz_choices WHERE question_id = ?");
        $stmt->execute([$id]);

        if ($question_type === 'mcq') {
            $validChoices = array_filter($choices, function($c) { return trim($c) !== ''; });
            if (count($validChoices) < 4) {
                $db->rollBack();
                jsonResponse(false, 'Multiple choice questions must have at least 4 answer choices');
            }
            foreach ($choices as $idx => $choice_text) {
                if (trim($choice_text) === '') continue;
                $is_correct = ($idx == intval($correct_choice)) ? 1 : 0;
                $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$id, trim($choice_text), $is_correct]);
            }
        } elseif ($question_type === 'true_false') {
            $tf_answer = $_POST['tf_answer'] ?? 'true';
            $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$id, 'True', $tf_answer === 'true' ? 1 : 0]);
            $stmt->execute([$id, 'False', $tf_answer === 'false' ? 1 : 0]);
        } elseif ($question_type === 'short_answer') {
            $short_answer = trim($_POST['short_answer'] ?? '');
            if (!empty($short_answer)) {
                $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, 1)");
                $stmt->execute([$id, $short_answer]);
            }
        }

        $db->commit();
        jsonResponse(true, 'Question updated successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Update failed: ' . $e->getMessage());
    }
}

function toggleArchiveQuestion($db, $teacher_id, $isAdmin = false) {
    $id = intval($_POST['question_id'] ?? 0);
    if (!$id) jsonResponse(false, 'Question ID is required');

    $query = "SELECT is_archived FROM questions_master WHERE question_id = ?";
    $params = [$id];
    if (!$isAdmin) {
        $query .= " AND created_by_teacher_id = ?";
        $params[] = $teacher_id;
    } else {
        $query .= " LIMIT 1";
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$question) {
        jsonResponse(false, 'Question not found or you do not have permission to change it');
    } else {
        $newStatus = (int)$question['is_archived'] === 1 ? 0 : 1;
        $update = $db->prepare("UPDATE questions_master SET is_archived = ? WHERE question_id = ?");
        $update->execute([$newStatus, $id]);
        jsonResponse(true, $newStatus ? 'Question archived successfully' : 'Question unarchived successfully', ['is_archived' => $newStatus]);
    }
}

function getQuestions($db, $teacher_id, $isAdmin = false) {
    $lesson_id = intval($_GET['lesson_id'] ?? 0);
    $include_public = isset($_GET['include_public']);

    $params = [];
    $sql = "SELECT q.*, l.lesson_title, c.chapter_title, (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count FROM questions_master q JOIN lessons l ON q.lesson_id = l.lesson_id JOIN chapters c ON l.chapter_id = c.chapter_id WHERE ";

    if ($isAdmin) {
        $sql .= "(1=1)";
    } else {
        $sql .= "(q.created_by_teacher_id = ?";
        $params[] = $teacher_id;
        if ($include_public) {
            $sql .= " OR q.visibility = 'public'";
        }
        $sql .= ")";
    }

    if ($lesson_id) {
        $sql .= " AND q.lesson_id = ?";
        $params[] = $lesson_id;
    }

    $sql .= " ORDER BY q.question_id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll();

    if (empty($questions)) {
        jsonResponse(true, 'Questions retrieved', []);
    }

    $questionIds = array_column($questions, 'question_id');
    $inClause = implode(',', array_fill(0, count($questionIds), '?'));
    
    $choicesStmt = $db->prepare("SELECT * FROM quiz_choices WHERE question_id IN ($inClause) ORDER BY choice_id");
    $choicesStmt->execute($questionIds);
    $allChoices = $choicesStmt->fetchAll();

    $groupedChoices = [];
    foreach ($allChoices as $choice) {
        $groupedChoices[$choice['question_id']][] = $choice;
    }

    foreach ($questions as &$question) {
        $question['choices'] = $groupedChoices[$question['question_id']] ?? [];
    }

    jsonResponse(true, 'Questions retrieved', $questions);
}

// CHOICE HELPERS
function checkChoiceQuestionOwner($db, $question_id, $teacher_id, $isAdmin) {
    if ($isAdmin) return true;
    $stmt = $db->prepare("SELECT question_id FROM questions_master WHERE question_id = ? AND created_by_teacher_id = ?");
    $stmt->execute([$question_id, $teacher_id]);
    return (bool) $stmt->fetch();
}

function addChoice($db, $teacher_id, $isAdmin = false) {
    $question_id = intval($_POST['question_id'] ?? 0);
    $choice_text = trim($_POST['choice_text'] ?? '');
    $is_correct = intval($_POST['is_correct'] ?? 0);
    if (!$question_id || empty($choice_text)) jsonResponse(false, 'Question ID and choice text are required');
    
    if (!checkChoiceQuestionOwner($db, $question_id, $teacher_id, $isAdmin)) {
        jsonResponse(false, 'Forbidden: You do not own this question');
    }

    $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
    $stmt->execute([$question_id, $choice_text, $is_correct]);
    jsonResponse(true, 'Choice added successfully', ['choice_id' => $db->lastInsertId()]);
}

function updateChoice($db, $teacher_id, $isAdmin = false) {
    $id = intval($_POST['choice_id'] ?? 0);
    $choice_text = trim($_POST['choice_text'] ?? '');
    $is_correct = intval($_POST['is_correct'] ?? 0);
    if (!$id || empty($choice_text)) jsonResponse(false, 'Choice ID and text are required');

    $stmt = $db->prepare("SELECT question_id FROM quiz_choices WHERE choice_id = ?");
    $stmt->execute([$id]);
    $choice = $stmt->fetch();
    if (!$choice || !checkChoiceQuestionOwner($db, $choice['question_id'], $teacher_id, $isAdmin)) {
        jsonResponse(false, 'Forbidden: Choice not found or access denied');
    }

    $stmt = $db->prepare("UPDATE quiz_choices SET choice_text = ?, is_correct = ? WHERE choice_id = ?");
    $stmt->execute([$choice_text, $is_correct, $id]);
    jsonResponse(true, 'Choice updated successfully');
}

function deleteChoice($db, $teacher_id, $isAdmin = false) {
    $id = intval($_POST['choice_id'] ?? 0);
    if (!$id) jsonResponse(false, 'Choice ID is required');

    $stmt = $db->prepare("SELECT question_id FROM quiz_choices WHERE choice_id = ?");
    $stmt->execute([$id]);
    $choice = $stmt->fetch();
    if (!$choice || !checkChoiceQuestionOwner($db, $choice['question_id'], $teacher_id, $isAdmin)) {
        jsonResponse(false, 'Forbidden: Choice not found or access denied');
    }

    $stmt = $db->prepare("DELETE FROM quiz_choices WHERE choice_id = ?");
    $stmt->execute([$id]);
    jsonResponse(true, 'Choice deleted successfully');
}

function getChoices($db) {
    $question_id = intval($_GET['question_id'] ?? 0);
    if (!$question_id) jsonResponse(false, 'Question ID is required');
    $stmt = $db->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_id");
    $stmt->execute([$question_id]);
    jsonResponse(true, 'Choices retrieved', $stmt->fetchAll());
}

// BULK
function bulkCreateQuestions($db, $teacher_id, $isAdmin = false) {
    $questionsJson = $_POST['questions'] ?? '';
    $questions = json_decode($questionsJson, true);
    if (!$questions || !is_array($questions)) jsonResponse(false, 'Invalid questions data');

    $imported = 0;
    $errors = [];

    if ($isAdmin && isset($_POST['author_teacher_id']) && intval($_POST['author_teacher_id']) > 0) {
        $importAuthor = intval($_POST['author_teacher_id']);
    } else {
        $importAuthor = $teacher_id;
    }

    if (empty($importAuthor)) {
        $stmt = $db->query("SELECT t.teacher_id FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active' LIMIT 1");
        $row = $stmt->fetch();
        if ($row && !empty($row['teacher_id'])) {
            $importAuthor = $row['teacher_id'];
        } else {
            jsonResponse(false, 'Author teacher id is required for bulk import when run by admin');
        }
    }

    $db->beginTransaction();
    try {
        foreach ($questions as $index => $q) {
            try {
                $lesson_id = intval($q['lesson_id'] ?? 0);
                $question_type = $q['question_type'] ?? '';
                $question_text = trim($q['question_text'] ?? '');
                $visibility = $q['visibility'] ?? 'private';
                $is_ai_generated = intval($q['is_ai_generated'] ?? 0);

                if (empty($question_text)) {
                    throw new Exception('Question text is required');
                }
                if (!in_array($question_type, ['mcq', 'true_false', 'short_answer'], true)) {
                    throw new Exception('Invalid question type');
                }
                if (!in_array($visibility, ['private', 'public'], true)) {
                    throw new Exception('Invalid visibility');
                }

                if ($question_type === 'mcq') {
                    if (!isset($q['choices']) || !is_array($q['choices']) || count($q['choices']) < 2) {
                        throw new Exception('MCQ questions must have at least 2 choices');
                    }
                    if (!isset($q['correct_choice']) || !is_numeric($q['correct_choice'])) {
                        throw new Exception('MCQ questions must have a valid correct choice index');
                    }
                    $correct_index = intval($q['correct_choice']);
                    if ($correct_index < 0 || $correct_index >= count($q['choices'])) {
                        throw new Exception('Correct choice index is out of range');
                    }
                    $correct_answer_text = $q['choices'][$correct_index] ?? '';
                    if (empty(trim($correct_answer_text))) {
                        throw new Exception('Correct choice cannot be empty');
                    }
                } elseif ($question_type === 'true_false') {
                    if (!isset($q['correct_choice']) || !in_array($q['correct_choice'], ['true', 'false'], true)) {
                        throw new Exception('True/False questions must have correct_choice as "true" or "false"');
                    }
                } elseif ($question_type === 'short_answer') {
                    if (!isset($q['correct_choice']) || empty(trim($q['correct_choice']))) {
                        throw new Exception('Short answer questions must have a correct answer');
                    }
                }

                $stmt = $db->prepare("INSERT INTO questions_master (lesson_id, question_text, question_type, visibility, created_by_teacher_id, is_ai_generated) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$lesson_id, $question_text, $question_type, $visibility, $importAuthor, $is_ai_generated]);
                $question_id = $db->lastInsertId();

                if ($question_type === 'mcq') {
                    foreach ($q['choices'] as $idx => $choice_text) {
                        $is_correct = ($idx === intval($q['correct_choice'])) ? 1 : 0;
                        $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$question_id, trim($choice_text), $is_correct]);
                    }
                } elseif ($question_type === 'true_false') {
                    $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, 'True', $q['correct_choice'] === 'true' ? 1 : 0]);
                    $stmt->execute([$question_id, 'False', $q['correct_choice'] === 'false' ? 1 : 0]);
                } elseif ($question_type === 'short_answer') {
                    $stmt = $db->prepare("INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, 1)");
                    $stmt->execute([$question_id, trim($q['correct_choice'])]);
                }

                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Transaction failed: ' . $e->getMessage());
    }

    if ($imported > 0) {
        jsonResponse(true, "Imported $imported questions" . (count($errors) > 0 ? ". Some errors occurred." : ""), [
            'imported_count' => intval($imported),
            'errors' => $errors
        ]);
    } else {
        jsonResponse(false, 'No questions were imported. Errors: ' . implode('; ', $errors), [
            'imported_count' => 0,
            'errors' => $errors
        ]);
    }
}

// UTIL
function jsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit;
}