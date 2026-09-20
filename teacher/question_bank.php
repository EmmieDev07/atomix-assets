<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();

$db         = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_questions');
$teacher_id = getTeacherId();

$dbError = null;
try {
// Game questions
$qStmt = $db->prepare("
    SELECT gq.game_question_id, gq.lesson_id, gq.question_text,
           gq.answer_0, gq.answer_1, gq.answer_2, gq.answer_3,
           gq.correct_answer_index,
           l.lesson_title, c.chapter_title, c.chapter_order, l.lesson_order
    FROM game_questions gq
    JOIN lessons l ON gq.lesson_id = l.lesson_id
    JOIN chapters c ON l.chapter_id = c.chapter_id
    WHERE gq.created_by_teacher_id = ?
    ORDER BY c.chapter_order, l.lesson_order, gq.game_question_id
");
$qStmt->execute([$teacher_id]);
$rawQuestions = $qStmt->fetchAll();

// All lessons
$lessons = $db->query("
    SELECT l.lesson_id, l.lesson_title, c.chapter_title
    FROM lessons l
    JOIN chapters c ON l.chapter_id = c.chapter_id
    ORDER BY c.chapter_order, l.lesson_order
")->fetchAll();

$totalQ = count($rawQuestions);

// Build lesson tree
$lessonTree = [];
foreach ($lessons as $ls) {
    $lessonTree[$ls['chapter_title']][$ls['lesson_id']] = [
        'lesson_title'  => $ls['lesson_title'],
        'chapter_title' => $ls['chapter_title'],
        'lesson_id'     => $ls['lesson_id'],
        'questions'     => [],
    ];
}
foreach ($rawQuestions as $q) {
    $lessonTree[$q['chapter_title']][$q['lesson_id']]['questions'][] = $q;
}

// MCQ quiz questions for import modal
$importStmt = $db->prepare("
    SELECT q.question_id, q.question_text, q.lesson_id,
           l.lesson_title, c.chapter_title, c.chapter_order, l.lesson_order
    FROM questions_master q
    JOIN lessons l ON q.lesson_id = l.lesson_id
    JOIN chapters c ON l.chapter_id = c.chapter_id
    WHERE q.created_by_teacher_id = ? AND q.question_type = 'mcq'
    ORDER BY c.chapter_order, l.lesson_order, q.question_id
");
$importStmt->execute([$teacher_id]);
$quizQuestions = [];
foreach ($importStmt->fetchAll() as $iq) {
    $cStmt = $db->prepare("SELECT choice_text, is_correct FROM quiz_choices WHERE question_id = ? ORDER BY choice_id LIMIT 4");
    $cStmt->execute([$iq['question_id']]);
    $choices = $cStmt->fetchAll();
    if (count($choices) === 4) {
        $iq['choices'] = $choices;
        $quizQuestions[] = $iq;
    }
}

// Pretest questions grouped by chapter_title for the inline reveal panel
$pretestByChapter = [];
try {
    $ptStmt = $db->prepare("
        SELECT c.chapter_title, cpq.pq_id, cpq.question_text,
               cpq.answer_0, cpq.answer_1, cpq.answer_2, cpq.answer_3,
               cpq.correct_answer_index, cpq.question_order
        FROM chapter_pretests cp
        JOIN chapters c ON cp.chapter_id = c.chapter_id
        JOIN chapter_pretest_questions cpq ON cp.pretest_id = cpq.pretest_id
        WHERE cp.created_by_teacher_id = ?
        ORDER BY c.chapter_order, cpq.question_order
    ");
    $ptStmt->execute([$teacher_id]);
    foreach ($ptStmt->fetchAll() as $pq) {
        $pretestByChapter[$pq['chapter_title']][] = $pq;
    }
} catch (PDOException $e) { /* pretest table may not exist on older installs */ }
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('question_bank.php DB error: ' . $e->getMessage());
    $rawQuestions = []; $lessons = []; $totalQ = 0; $lessonTree = []; $quizQuestions = []; $pretestByChapter = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Question Bank - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* -- Design Tokens -- */
        :root {
            --pri: #4f46e5; --pri-50: #eef2ff; --pri-100: #e0e7ff;
            --pri-dark: #4338ca; --pri-darker: #3730a3;
            --ok: #16a34a; --ok-50: #f0fdf4; --ok-100: #dcfce7; --ok-200: #bbf7d0;
            --danger: #dc2626; --danger-50: #fef2f2;
            --gray-50: #f8fafc; --gray-100: #f1f5f9; --gray-200: #e2e8f0;
            --gray-300: #cbd5e1; --gray-400: #94a3b8; --gray-500: #64748b;
            --gray-700: #334155; --gray-900: #0f172a;
            --radius: 10px; --radius-lg: 14px;
            --shadow: 0 1px 4px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 14px rgba(0,0,0,.08);
        }

        /* -- page -- */
        .qb-wrap { padding: 28px 32px; max-width: 940px; }

        /* -- stats row -- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(3,1fr);
            gap: 14px; margin-bottom: 24px;
        }
        .stat-card {
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-lg); padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: var(--shadow);
            transition: transform .15s, box-shadow .15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-icon {
            width: 44px; height: 44px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1rem;
        }
        .stat-icon--blue   { background: #dbeafe; color: #1d4ed8; }
        .stat-icon--green  { background: var(--ok-100); color: var(--ok); }
        .stat-icon--purple { background: #ede9fe; color: #7c3aed; }
        .stat-num  { font-size: 1.6rem; font-weight: 900; color: var(--gray-900); line-height: 1; }
        .stat-label { font-size: .72rem; font-weight: 700; color: var(--gray-400);
            text-transform: uppercase; letter-spacing: .06em; margin-top: 3px; }

        /* -- top bar -- */
        .qb-topbar {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; margin-bottom: 22px;
        }
        .qb-title {
            font-size: 1.1rem; font-weight: 800; color: var(--gray-900);
            display: flex; align-items: center; gap: 10px;
        }
        .qb-count {
            background: var(--pri-100); color: var(--pri-darker);
            font-size: .71rem; font-weight: 800;
            border-radius: 20px; padding: 4px 12px;
        }
        .qb-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .qb-chapter-filter {
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: .82rem;
            color: var(--gray-700);
            background: #fff;
            min-width: 180px;
        }
        .qb-chapter-filter:focus {
            outline: none;
            border-color: var(--pri);
            box-shadow: 0 0 0 3px rgba(79,70,229,.1);
        }
        .qb-search {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: var(--radius); padding: 8px 14px;
            transition: border-color .15s, box-shadow .15s;
        }
        .qb-search:focus-within { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }
        .qb-search input {
            border: none; outline: none; background: transparent;
            font-size: .85rem; color: var(--gray-900); width: 190px;
        }
        .qb-search i { color: var(--gray-400); font-size: .82rem; }
        


        .btn-import-open {
            padding: 9px 18px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff; border: none; border-radius: var(--radius);
            font-size: .83rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 7px;
            box-shadow: 0 2px 8px rgba(79,70,229,.3);
            transition: opacity .15s, transform .15s, box-shadow .15s;
        }
        .btn-import-open:hover { opacity: .92; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(79,70,229,.4); }
        .btn-secondary-open {
            padding: 9px 18px;
            background: #fff; color: var(--pri);
            border: 1.5px solid var(--pri-100); border-radius: var(--radius);
            font-size: .83rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 7px;
            transition: background .15s, border-color .15s, transform .15s;
        }
        .btn-secondary-open:hover { background: var(--pri-50); border-color: var(--pri); transform: translateY(-1px); }

        /* -- chapter section -- */
        .chapter-section { margin-bottom: 32px; }
        .chapter-heading {
            display: flex; align-items: center; gap: 10px;
            margin: 0 0 12px; padding: 12px 16px;
            background: linear-gradient(135deg, #eef2ff, #faf5ff);
            border: 1px solid var(--pri-100); border-radius: var(--radius);
            border-left: 4px solid var(--pri);
        }
        .chapter-heading-icon {
            width: 30px; height: 30px; background: var(--pri); color: #fff;
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: .78rem; flex-shrink: 0;
        }
        .chapter-heading-name {
            font-size: .82rem; font-weight: 900; color: var(--pri-darker);
            text-transform: uppercase; letter-spacing: .08em; flex: 1;
        }
        .chapter-heading-count {
            font-size: .73rem; color: #6d28d9; font-weight: 700;
            background: #ede9fe; border-radius: 20px; padding: 3px 11px;
        }

        /* -- lesson card -- */
        .lesson-card {
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-lg); margin-bottom: 10px;
            box-shadow: var(--shadow); overflow: hidden;
            transition: border-color .15s, box-shadow .15s;
        }
        .lesson-card:hover { border-color: #c7d2fe; box-shadow: 0 2px 10px rgba(79,70,229,.08); }
        .lesson-header {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 16px; cursor: pointer; user-select: none;
            transition: background .15s;
        }
        .lesson-header:hover { background: #fafafe; }
        .lesson-icon {
            width: 34px; height: 34px; background: var(--ok-50); color: var(--ok);
            border-radius: 9px; display: flex; align-items: center;
            justify-content: center; font-size: .82rem; flex-shrink: 0;
            border: 1.5px solid var(--ok-200);
        }
        .lesson-name { flex: 1; font-size: .9rem; font-weight: 700; color: #1e293b; }
        .lesson-count {
            background: var(--gray-100); color: var(--gray-500);
            font-size: .71rem; font-weight: 700; border-radius: 20px;
            padding: 4px 10px; flex-shrink: 0;
        }
        .btn-add-q {
            display: flex; align-items: center; gap: 5px;
            background: var(--ok-50); color: var(--ok);
            border: 1.5px solid var(--ok-200); border-radius: 8px;
            padding: 6px 12px; font-size: .75rem; font-weight: 700;
            cursor: pointer; flex-shrink: 0;
            transition: background .15s, transform .1s;
        }
        .btn-add-q:hover { background: var(--ok-100); transform: scale(1.03); }
        .lesson-caret { color: var(--gray-300); font-size: .78rem; transition: transform .25s; flex-shrink: 0; }
        .lesson-header.closed .lesson-caret { transform: rotate(-90deg); }
        .lesson-children { border-top: 1px solid var(--gray-100); }
        .lesson-children.hidden { display: none; }

        /* -- question rows -- */
        .q-row { border-bottom: 1px solid #f9fbfc; transition: opacity .3s; }
        .q-row:last-child { border-bottom: none; }
        .q-row-head {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; cursor: pointer; user-select: none;
            transition: background .15s;
        }
        .q-row-head:hover { background: #fafbfe; }
        .q-row.open .q-row-head { background: #f0f9ff; border-bottom: 1px solid #e0f2fe; }
        .q-num {
            width: 24px; height: 24px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: .69rem; font-weight: 800; flex-shrink: 0;
        }
        .q-text-preview {
            flex: 1; font-size: .86rem; color: #1e293b; font-weight: 500;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .saved-badge {
            font-size: .71rem; font-weight: 700; padding: 3px 9px;
            border-radius: 20px; display: none; flex-shrink: 0;
        }
        .saved-badge.ok  { display: inline-block; background: #d1fae5; color: #065f46; }
        .saved-badge.err { display: inline-block; background: #fee2e2; color: #991b1b; }
        .q-row-caret { color: var(--gray-300); font-size: .78rem; transition: transform .2s; }
        .q-row.open .q-row-caret { transform: rotate(90deg); }

        /* -- inspector panel -- */
        .q-inspector { display: none; padding: 18px 20px 20px; background: #fafbff; border-top: 1px solid var(--gray-200); }
        .q-row.open .q-inspector { display: block; }
        .insp-label {
            font-size: .7rem; font-weight: 800; color: #6d7280;
            text-transform: uppercase; letter-spacing: .08em; margin-bottom: 7px;
            display: flex; align-items: center; gap: 6px;
        }
        .insp-label::before {
            content: ''; display: inline-block;
            width: 3px; height: 12px; background: var(--pri); border-radius: 2px;
        }
        .insp-textarea {
            width: 100%; box-sizing: border-box;
            padding: 10px 14px; border: 1.5px solid var(--gray-200); border-radius: 9px;
            font-size: .85rem; color: #1e293b; background: #fff;
            font-family: inherit; resize: vertical; line-height: 1.5;
            transition: border-color .15s, box-shadow .15s;
        }
        .insp-textarea:focus { outline: none; border-color: var(--pri); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

        /* -- answer grid -- */
        .answer-grid { display: flex; flex-direction: column; gap: 7px; margin-top: 7px; }
        .answer-row {
            display: flex; align-items: center; gap: 8px;
            padding: 4px; border-radius: 8px; transition: background .15s;
        }
        .answer-letter {
            width: 28px; height: 28px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .74rem; font-weight: 900; flex-shrink: 0;
            background: var(--gray-100); color: var(--gray-500); border: 1.5px solid var(--gray-200);
            transition: all .2s;
        }
        .answer-input {
            flex: 1; padding: 8px 12px; border: 1.5px solid var(--gray-200);
            border-radius: 8px; font-size: .84rem; color: #1e293b;
            background: #fff; font-family: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        .answer-input:focus { outline: none; border-color: var(--pri); box-shadow: 0 0 0 3px rgba(79,70,229,.08); }
        .correct-radio { width: 17px; height: 17px; accent-color: var(--ok); cursor: pointer; flex-shrink: 0; }
        .answer-row:has(.correct-radio:checked) { background: var(--ok-50); }
        .answer-row:has(.correct-radio:checked) .answer-letter { background: var(--ok); color: #fff; border-color: var(--ok); }
        .answer-row:has(.correct-radio:checked) .answer-input { border-color: #4ade80; background: var(--ok-50); }

        /* -- action bar -- */
        .insp-actions {
            display: flex; justify-content: flex-end; align-items: center;
            gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--gray-100);
        }
        .btn-save {
            padding: 8px 20px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff; border: none; border-radius: 9px;
            font-size: .83rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 6px;
            box-shadow: 0 2px 6px rgba(79,70,229,.3);
            transition: opacity .15s, transform .1s, box-shadow .15s;
        }
        .btn-save:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,.4); }
        .btn-save:disabled { background: #a5b4fc; cursor: not-allowed; box-shadow: none; transform: none; }
        .btn-del {
            padding: 8px 14px; background: transparent; color: #ef4444;
            border: 1.5px solid #fecaca; border-radius: 9px;
            font-size: .83rem; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 6px;
            transition: background .15s, border-color .15s;
        }
        .btn-del:hover { background: var(--danger-50); border-color: #f87171; }

        /* -- add question form -- */
        .add-q-form {
            display: none; padding: 18px 20px;
            background: linear-gradient(to bottom, var(--ok-50), #fafffe);
            border-top: 2px dashed #86efac;
        }
        .add-q-form.open { display: block; }
        .add-q-title {
            font-size: .86rem; font-weight: 800; color: #15803d;
            margin: 0 0 16px; display: flex; align-items: center; gap: 8px;
        }
        .add-insp-label {
            font-size: .7rem; font-weight: 800; color: #15803d;
            text-transform: uppercase; letter-spacing: .08em; margin-bottom: 7px;
            display: flex; align-items: center; gap: 6px;
        }
        .add-insp-label::before {
            content: ''; display: inline-block;
            width: 3px; height: 12px; background: var(--ok); border-radius: 2px;
        }
        .add-textarea {
            width: 100%; box-sizing: border-box;
            padding: 10px 14px; border: 1.5px solid var(--ok-200); border-radius: 9px;
            font-size: .85rem; color: #1e293b; background: #fff;
            font-family: inherit; resize: vertical; line-height: 1.5;
            transition: border-color .15s, box-shadow .15s;
        }
        .add-textarea:focus { outline: none; border-color: var(--ok); box-shadow: 0 0 0 3px rgba(22,163,74,.1); }
        .add-answer-grid { display: flex; flex-direction: column; gap: 7px; margin-top: 7px; }
        .add-answer-row {
            display: flex; align-items: center; gap: 8px;
            padding: 4px; border-radius: 8px; transition: background .15s;
        }
        .add-answer-letter {
            width: 28px; height: 28px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .74rem; font-weight: 900; flex-shrink: 0;
            background: var(--ok-100); color: #15803d; border: 1.5px solid var(--ok-200);
            transition: all .2s;
        }
        .add-answer-input {
            flex: 1; padding: 8px 12px; border: 1.5px solid var(--ok-200);
            border-radius: 8px; font-size: .84rem; color: #1e293b;
            background: #fff; font-family: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        .add-answer-input:focus { outline: none; border-color: var(--ok); box-shadow: 0 0 0 3px rgba(22,163,74,.1); }
        .add-correct-radio { width: 17px; height: 17px; accent-color: var(--ok); cursor: pointer; flex-shrink: 0; }
        .add-answer-row:has(.add-correct-radio:checked) { background: var(--ok-50); }
        .add-answer-row:has(.add-correct-radio:checked) .add-answer-letter { background: var(--ok); color: #fff; border-color: var(--ok); }
        .add-answer-row:has(.add-correct-radio:checked) .add-answer-input { border-color: #4ade80; background: var(--ok-50); }
        .add-form-actions {
            display: flex; justify-content: flex-end; gap: 8px;
            margin-top: 16px; padding-top: 14px; border-top: 1px dashed #86efac;
        }
        .btn-add-submit {
            padding: 8px 20px;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            color: #fff; border: none; border-radius: 9px;
            font-size: .83rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 6px;
            box-shadow: 0 2px 6px rgba(22,163,74,.3);
            transition: opacity .15s, transform .1s;
        }
        .btn-add-submit:hover { opacity: .9; transform: translateY(-1px); }
        .btn-add-submit:disabled { background: #86efac; cursor: not-allowed; box-shadow: none; transform: none; }
        .btn-cancel {
            padding: 8px 14px; background: transparent; color: var(--gray-500);
            border: 1.5px solid var(--gray-200); border-radius: 9px;
            font-size: .83rem; font-weight: 600; cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .btn-cancel:hover { background: var(--gray-100); border-color: var(--gray-300); }

        /* -- empty state -- */
        .qb-empty {
            text-align: center; padding: 72px 20px; color: var(--gray-400);
            background: #fff; border-radius: var(--radius-lg); border: 1.5px dashed var(--gray-200);
        }
        .qb-empty i { font-size: 3rem; display: block; margin-bottom: 14px; color: #c7d2fe; }
        .qb-empty p { font-size: .9rem; max-width: 300px; margin: 0 auto; }

        /* -- import modal -- */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.55);
            backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff; border-radius: 16px;
            width: 700px; max-width: 95vw; max-height: 85vh;
            display: flex; flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,.2), 0 0 0 1px rgba(0,0,0,.05);
            animation: modalIn .2s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes modalIn {
            from { transform: scale(.95) translateY(-8px); opacity: 0; }
            to   { transform: scale(1)   translateY(0);    opacity: 1; }
        }
        .modal-header {
            display: flex; align-items: center; gap: 12px;
            padding: 18px 22px; border-bottom: 1px solid var(--gray-100);
            background: linear-gradient(135deg, #faf5ff, #eef2ff);
            border-radius: 16px 16px 0 0;
        }
        .modal-header-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: #fff; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: .88rem; flex-shrink: 0;
        }
        .modal-header h3 { margin: 0; font-size: .96rem; color: var(--gray-900); flex: 1; font-weight: 800; }
        .modal-close {
            background: var(--gray-100); border: none; color: var(--gray-500);
            cursor: pointer; font-size: .9rem; padding: 6px 9px;
            border-radius: 8px; line-height: 1; transition: background .15s, color .15s;
        }
        .modal-close:hover { background: #fee2e2; color: var(--danger); }
        .modal-toolbar {
            padding: 12px 22px; border-bottom: 1px solid #f8fafc;
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            background: #fafbfc;
        }
        .modal-search {
            flex: 1; min-width: 160px;
            display: flex; align-items: center; gap: 7px;
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: 9px; padding: 8px 13px;
            transition: border-color .15s, box-shadow .15s;
        }
        .modal-search:focus-within { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(79,70,229,.08); }
        .modal-search input {
            border: none; outline: none; background: transparent;
            font-size: .84rem; color: #1e293b; width: 100%;
        }
        .modal-lesson-filter {
            padding: 8px 12px; border: 1.5px solid var(--gray-200); border-radius: 9px;
            font-size: .83rem; background: #fff; color: #1e293b; cursor: pointer;
        }
        .modal-lesson-filter:focus { outline: none; border-color: var(--pri); }
        .modal-select-all {
            display: flex; align-items: center; gap: 7px;
            font-size: .83rem; color: #475569; cursor: pointer; font-weight: 600;
        }
        .modal-body { flex: 1; overflow-y: auto; padding: 8px 22px; }
        .import-q-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 8px; border-bottom: 1px solid #f8fafc;
            font-size: .84rem; border-radius: 9px; transition: background .12s;
        }
        .import-q-item:last-child { border-bottom: none; }
        .import-q-item:hover { background: #fafafe; }
        .import-q-item input[type=checkbox] {
            margin-top: 3px; flex-shrink: 0;
            width: 16px; height: 16px; accent-color: var(--pri); cursor: pointer;
        }
        .import-q-item.checked { background: var(--pri-50); border-radius: 9px; }
        .import-q-text { font-weight: 600; color: #1e293b; line-height: 1.5; }
        .import-q-meta { font-size: .73rem; color: var(--gray-400); margin-top: 3px; }
        .import-q-answers { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 7px; }
        .import-q-ans {
            padding: 3px 10px; border-radius: 6px; font-size: .74rem;
            background: var(--gray-100); color: var(--gray-500); border: 1px solid var(--gray-200);
        }
        .import-q-ans.correct { background: #d1fae5; color: #065f46; border-color: #6ee7b7; font-weight: 700; }
        .modal-empty { text-align: center; padding: 54px; color: var(--gray-400); }
        .modal-footer {
            padding: 14px 22px; border-top: 1px solid var(--gray-100);
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; flex-wrap: wrap;
            background: #fafbfc; border-radius: 0 0 16px 16px;
        }
        .modal-selected-count {
            font-size: .83rem; font-weight: 700;
            background: var(--pri-100); color: var(--pri-darker);
            padding: 4px 12px; border-radius: 20px;
        }
        .modal-target-lesson {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 8px;
            font-size: .83rem;
            color: #475569;
            font-weight: 600;
            min-width: 0;
            width: 100%;
            max-width: 420px;
            flex: 1;
        }
        .modal-target-lesson span { white-space: nowrap; }
        .modal-target-lesson select {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            padding: 7px 12px; border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-size: .83rem; background: #fff; color: #1e293b; cursor: pointer;
        }
        .btn-import {
            padding: 9px 20px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff; border: none; border-radius: 9px;
            font-size: .83rem; font-weight: 700;
            cursor: pointer; box-shadow: 0 2px 8px rgba(79,70,229,.3);
            transition: opacity .15s, transform .1s;
        }
        .btn-import:hover { opacity: .92; transform: translateY(-1px); }
        .btn-import:disabled { background: #a5b4fc; cursor: not-allowed; box-shadow: none; transform: none; }

        /* -- upload modal -- */
        .upload-instructions {
            padding: 14px 22px; background: #fafbfc; border-bottom: 1px solid #f8fafc;
            font-size: .82rem; color: #475569; display: flex; align-items: center;
            justify-content: space-between; gap: 12px; flex-wrap: wrap;
        }
        .upload-instructions code {
            background: var(--pri-50); color: var(--pri-darker); padding: 2px 6px;
            border-radius: 5px; font-size: .78rem;
        }
        .upload-dropzone {
            margin: 16px 22px 0; padding: 16px; border: 1.5px dashed var(--gray-300);
            border-radius: var(--radius); background: #fafbfc;
            overflow: hidden;
        }
        .upload-dropzone input[type=file] {
            width: 100%; font-size: .83rem; color: #475569; cursor: pointer;
        }
        .upload-lesson-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 8px;
            font-size: .83rem;
            color: #475569;
            font-weight: 600;
            margin-top: 12px;
            min-width: 0;
            width: 100%;
        }
        .upload-lesson-row span { white-space: nowrap; }
        .upload-lesson-row select {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            padding: 7px 12px; border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-size: .83rem; background: #fff; color: #1e293b; cursor: pointer;
        }

        /* Accuracy Badge UI */
        .btn-accuracy-proof {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-accuracy-proof:hover {
            background: #0284c7;
            color: #ffffff;
        }
        .accuracy-proof-box {
            display: none;
            margin-top: 8px;
            padding: 10px 12px;
            background: #f0fdf4;
            border-left: 3px solid #16a34a;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #166534;
        }
        .accuracy-proof-box.open {
            display: block;
        }

        @media (max-width: 768px) {
            .upload-lesson-row,
            .modal-target-lesson {
                grid-template-columns: 1fr;
                align-items: stretch;
            }

            .upload-lesson-row span,
            .modal-target-lesson span {
                white-space: normal;
            }
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
            <h1><i class="fas fa-gamepad" style="color:#4f46e5;margin-right:8px;"></i>Game Question Bank</h1>
            <a href="profile.php" class="user-info" title="My Profile">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <i class="fas fa-user-circle"></i>
            </a>
        </header>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Game Question Bank</span>
        </nav>
        <?php if (!empty($dbError)): ?>
        <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
        <?php endif; ?>

        <div class="qb-wrap">

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon--blue"><i class="fas fa-question-circle"></i></div>
                    <div>
                        <div class="stat-num"><?php echo $totalQ; ?></div>
                        <div class="stat-label">Game Questions</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon--green"><i class="fas fa-book-open"></i></div>
                    <div>
                        <div class="stat-num"><?php echo count($lessons); ?></div>
                        <div class="stat-label">Lessons</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon--purple"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="stat-num"><?php echo count($lessonTree); ?></div>
                        <div class="stat-label">Chapters</div>
                    </div>
                </div>
            </div>

            <!-- Top bar -->
            <div class="qb-topbar">
                <div class="qb-title">
                    Game Questions
                    <span class="qb-count"><?php echo $totalQ; ?> question<?php echo $totalQ !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="qb-actions">
                    <button class="btn-import-open" onclick="openImportModal()">
                        <i class="fas fa-file-import"></i> Import
                    </button>
                    <button class="btn-secondary-open" onclick="downloadTemplate()">
                        <i class="fas fa-file-download"></i> Template
                    </button>
                    <button class="btn-secondary-open" onclick="openUploadModal()">
                        <i class="fas fa-file-upload"></i> Upload
                    </button>
                    <select id="qbChapterFilter" class="qb-chapter-filter" onchange="doSearch()">
                        <option value="">All Chapters</option>
                        <?php foreach ($lessonTree as $chTitle => $lessonMap): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($chTitle)); ?>">
                            <?php echo htmlspecialchars($chTitle); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="qb-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="qbSearch" placeholder="Search questions..." oninput="doSearch()">
                    </div>
                </div>
            </div>

            <?php if (empty($lessons)): ?>
            <div class="qb-empty">
                <i class="fas fa-sitemap"></i>
                <p>No chapters or lessons found. Create chapters and lessons first.</p>
            </div>
            <?php else: ?>

            <?php foreach ($lessonTree as $chTitle => $lessonMap): ?>
            <?php
                $chTotal = 0;
                foreach ($lessonMap as $ldata) $chTotal += count($ldata['questions']);
            ?>
            <div class="chapter-section">

                <!-- Chapter heading -->
                <div class="chapter-heading">
                    <div class="chapter-heading-icon"><i class="fas fa-layer-group"></i></div>
                    <span class="chapter-heading-name"><?php echo htmlspecialchars($chTitle); ?></span>
                    <span class="chapter-heading-count"><?php echo $chTotal; ?> question<?php echo $chTotal !== 1 ? 's' : ''; ?></span>
                </div>

                <?php foreach ($lessonMap as $lessonId => $ldata): ?>
                <?php $lessonQs = $ldata['questions']; $lessonTitle = $ldata['lesson_title']; ?>
                <div class="lesson-card" data-chapter="<?php echo htmlspecialchars(strtolower($chTitle)); ?>">

                    <!-- Lesson header -->
                    <div class="lesson-header" onclick="toggleLesson(this)">
                        <div class="lesson-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="lesson-name"><?php echo htmlspecialchars($lessonTitle); ?></div>
                        <span class="lesson-count" id="lcount-<?php echo $lessonId; ?>"><?php echo count($lessonQs); ?> Q</span>
                        <button class="btn-add-q" onclick="event.stopPropagation();toggleAddForm(<?php echo $lessonId; ?>)">
                            <i class="fas fa-plus"></i> Add
                        </button>
                        <i class="fas fa-chevron-down lesson-caret"></i>
                    </div>

                    <div class="lesson-children" id="lchildren-<?php echo $lessonId; ?>">

                        <!-- Question list -->
                        <div class="q-list" id="qlist-<?php echo $lessonId; ?>">
                        <?php foreach ($lessonQs as $qi => $q):
                            $gqid = $q['game_question_id'];
                        ?>
                        <div class="q-row" id="qcard-<?php echo $gqid; ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($q['question_text'])); ?>">

                            <div class="q-row-head" onclick="toggleCard(<?php echo $gqid; ?>)">
                                <span class="q-num"><?php echo $qi + 1; ?></span>
                                <span class="q-text-preview"><?php echo htmlspecialchars($q['question_text']); ?></span>
                                <span class="saved-badge" id="badge-<?php echo $gqid; ?>"></span>
                                <i class="fas fa-chevron-right q-row-caret"></i>
                            </div>

                            <div class="q-inspector">
                                <div class="insp-label">Question</div>
                                <textarea class="insp-textarea" id="qt-<?php echo $gqid; ?>" rows="2"><?php echo htmlspecialchars($q['question_text']); ?></textarea>

                                <div class="insp-label" style="margin-top:14px;">Answers -- click to mark correct</div>
                                <div class="answer-grid">
                                    <?php
                                    $answerLabels = ['A','B','C','D'];
                                    $answerKeys   = ['answer_0','answer_1','answer_2','answer_3'];
                                    foreach ($answerLabels as $ai => $letter):
                                    ?>
                                    <div class="answer-row">
                                        <span class="answer-letter"><?php echo $letter; ?></span>
                                        <input type="text" class="answer-input"
                                               id="a<?php echo $ai; ?>-<?php echo $gqid; ?>"
                                               value="<?php echo htmlspecialchars($q[$answerKeys[$ai]]); ?>">
                                        <input type="radio" class="correct-radio"
                                               name="cidx-<?php echo $gqid; ?>"
                                               value="<?php echo $ai; ?>"
                                               title="Mark as correct"
                                               <?php echo $q['correct_answer_index'] == $ai ? 'checked' : ''; ?>>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="insp-actions">
                                    <button class="btn-del" onclick="deleteQ(<?php echo $gqid; ?>, <?php echo $lessonId; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <button class="btn-save" id="savebtn-<?php echo $gqid; ?>" onclick="saveQ(<?php echo $gqid; ?>)">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div><!-- /.q-list -->

                        <!-- Add question inline form -->
                        <div class="add-q-form" id="addform-<?php echo $lessonId; ?>">
                            <div class="add-q-title">
                                <i class="fas fa-plus-circle"></i>
                                New Question -- <?php echo htmlspecialchars($lessonTitle); ?>
                            </div>

                            <div class="add-insp-label">Question</div>
                            <textarea class="add-textarea" id="new-qt-<?php echo $lessonId; ?>" rows="2" placeholder="Enter question..."></textarea>

                            <div class="add-insp-label" style="margin-top:12px;">Answers -- click to mark correct</div>
                            <div class="add-answer-grid">
                                <?php foreach (['A','B','C','D'] as $ni => $letter): ?>
                                <div class="add-answer-row">
                                    <span class="add-answer-letter"><?php echo $letter; ?></span>
                                    <input type="text" class="add-answer-input"
                                           id="new-a<?php echo $ni; ?>-<?php echo $lessonId; ?>"
                                           placeholder="Answer <?php echo $letter; ?>...">
                                    <input type="radio" class="add-correct-radio"
                                           name="new-cidx-<?php echo $lessonId; ?>"
                                           value="<?php echo $ni; ?>"
                                           title="Mark as correct"
                                           <?php echo $ni === 0 ? 'checked' : ''; ?>>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="add-form-actions">
                                <button class="btn-cancel" onclick="toggleAddForm(<?php echo $lessonId; ?>)">Cancel</button>
                                <button class="btn-add-submit" id="addbtn-<?php echo $lessonId; ?>"
                                        onclick="submitAdd(<?php echo $lessonId; ?>)">
                                    <i class="fas fa-plus"></i> Add Question
                                </button>
                            </div>
                        </div><!-- /.add-q-form -->

                    </div><!-- /.lesson-children -->
                </div><!-- /.lesson-card -->
                <?php endforeach; ?>

            </div><!-- /.chapter-section -->
            <?php endforeach; ?>

            <?php endif; ?>
        </div><!-- /.qb-wrap -->
    </main>
</div>

<!-- Toast -->
<div class="toast" id="toast"><span class="toast-message"></span></div>

<!-- Import Modal -->
<div class="modal-overlay" id="importModal" onclick="closeImportOnOverlay(event)">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-icon"><i class="fas fa-file-import"></i></div>
            <h3>Import from Question Bank</h3>
            <button class="modal-close" onclick="closeImportModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-toolbar">
            <div class="modal-search">
                <i class="fas fa-search" style="color:#94a3b8;"></i>
                <input type="text" id="importSearch" placeholder="Filter questions..." oninput="filterImport()">
            </div>
            <select class="modal-lesson-filter" id="importLessonFilter" onchange="filterImport()">
                <option value="">All Lessons</option>
                <?php
                $seenLessons = [];
                foreach ($quizQuestions as $iq):
                    $key = $iq['lesson_id'];
                    if (!isset($seenLessons[$key])):
                        $seenLessons[$key] = true;
                ?>
                <option value="<?php echo $iq['lesson_id']; ?>">
                    <?php echo htmlspecialchars($iq['chapter_title'] . ' > ' . $iq['lesson_title']); ?>
                </option>
                <?php endif; endforeach; ?>
            </select>
            <label class="modal-select-all">
                <input type="checkbox" id="selectAllImport" onchange="toggleSelectAll(this.checked)">
                Select all
            </label>
        </div>
        <div class="modal-body" id="importList">
            <?php if (empty($quizQuestions)): ?>
            <div class="modal-empty">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                No MCQ questions with 4 choices found in your question bank.
            </div>
            <?php else: ?>
            <?php foreach ($quizQuestions as $iq):
                $correctIdx = 0;
                foreach ($iq['choices'] as $ci => $ch) {
                    if ($ch['is_correct']) { $correctIdx = $ci; break; }
                }
            ?>
            <div class="import-q-item"
                 data-qid="<?php echo $iq['question_id']; ?>"
                 data-lesson="<?php echo $iq['lesson_id']; ?>"
                 data-search="<?php echo htmlspecialchars(strtolower($iq['question_text'])); ?>"
                 data-text="<?php echo htmlspecialchars($iq['question_text']); ?>"
                 data-a0="<?php echo htmlspecialchars($iq['choices'][0]['choice_text']); ?>"
                 data-a1="<?php echo htmlspecialchars($iq['choices'][1]['choice_text']); ?>"
                 data-a2="<?php echo htmlspecialchars($iq['choices'][2]['choice_text']); ?>"
                 data-a3="<?php echo htmlspecialchars($iq['choices'][3]['choice_text']); ?>"
                 data-correct="<?php echo $correctIdx; ?>">
                <input type="checkbox" class="import-chk"
                       onchange="updateImportCount();this.closest('.import-q-item').classList.toggle('checked',this.checked)">
                <div style="flex:1;">
                    <div class="import-q-text">
                        <?php echo htmlspecialchars($iq['question_text']); ?>
                        <div class="import-q-meta">
                            <?php echo htmlspecialchars($iq['chapter_title'] . ' > ' . $iq['lesson_title']); ?>
                        </div>
                    </div>
                    <div class="import-q-answers">
                        <?php foreach ($iq['choices'] as $ci => $ch): ?>
                        <span class="import-q-ans<?php echo $ci === $correctIdx ? ' correct' : ''; ?>">
                            <?php echo ['A','B','C','D'][$ci]; ?>: <?php echo htmlspecialchars($ch['choice_text']); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <span class="modal-selected-count" id="importCount">0 selected</span>
            <div class="modal-target-lesson">
                <span>Import into:</span>
                <select id="importTargetLesson" style="width:100%;max-width:100%;min-width:0;box-sizing:border-box;">
                    <?php foreach ($lessons as $ls): ?>
                    <option value="<?php echo $ls['lesson_id']; ?>">
                        <?php echo htmlspecialchars($ls['chapter_title'] . ' > ' . $ls['lesson_title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-import" id="btnImport" onclick="doImport()" disabled>
                <i class="fas fa-download"></i> Import Selected
            </button>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal" onclick="closeUploadOnOverlay(event)">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-icon"><i class="fas fa-file-upload"></i></div>
            <h3>Upload Questions from CSV/XLSX</h3>
            <button class="modal-close" onclick="closeUploadModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="upload-instructions">
            <span>
                CSV/XLSX columns: <code>Question Text, Answer A, Answer B, Answer C, Answer D, Correct Answer</code>
                (<code>Correct Answer</code> can be A, B, C, or D).
            </span>
            <button class="btn-secondary-open" type="button" onclick="downloadTemplate()">
                <i class="fas fa-file-download"></i> Download Template
            </button>
        </div>
        <div class="upload-dropzone">
            <input type="file" id="uploadFile" accept=".csv,.xlsx" onchange="handleUploadFile(this.files[0])">
            <div class="upload-lesson-row">
                <span>Upload into:</span>
                <select id="uploadTargetLesson" style="width:100%;max-width:100%;min-width:0;box-sizing:border-box;">
                    <?php foreach ($lessons as $ls): ?>
                    <option value="<?php echo $ls['lesson_id']; ?>">
                        <?php echo htmlspecialchars($ls['chapter_title'] . ' > ' . $ls['lesson_title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-body" id="uploadPreview"></div>
        <div class="modal-footer">
            <span class="modal-selected-count" id="uploadCount">0 questions parsed</span>
            <button class="btn-import" id="btnUpload" onclick="doUpload()" disabled>
                <i class="fas fa-upload"></i> Upload Questions
            </button>
        </div>
    </div>
</div>

<script src="../assets/js/xlsx.full.min.js"></script>
<script>
/* -- collapse / expand -- */
function toggleLesson(header) {
    header.classList.toggle('closed');
    header.nextElementSibling.classList.toggle('hidden');
}
function toggleCard(gqid) {
    document.getElementById('qcard-' + gqid).classList.toggle('open');
}
function toggleAddForm(lessonId) {
    const form = document.getElementById('addform-' + lessonId);
    form.classList.toggle('open');
    if (form.classList.contains('open')) {
        document.getElementById('new-qt-' + lessonId).focus();
    }
}

/* -- search -- */
function doSearch() {
    const q = document.getElementById('qbSearch').value.toLowerCase().trim();
    const chapter = document.getElementById('qbChapterFilter')?.value || '';
    const allRows = Array.from(document.querySelectorAll('.q-row'));
    allRows.forEach((row) => {
        const rowChapter = row.closest('.lesson-card')?.dataset.chapter || '';
        const matchesText = !q || (row.dataset.search || '').includes(q);
        const matchesChapter = !chapter || rowChapter === chapter;
        row.style.display = (matchesText && matchesChapter) ? '' : 'none';
    });

    document.querySelectorAll('.lesson-card').forEach((lessonCard) => {
        const rows = Array.from(lessonCard.querySelectorAll('.q-row'));
        if (rows.length === 0) {
            lessonCard.style.display = '';
            return;
        }

        const hasVisibleQuestion = rows.some((row) => row.style.display !== 'none');
        lessonCard.style.display = hasVisibleQuestion ? '' : 'none';
    });

    document.querySelectorAll('.chapter-section').forEach((chapter) => {
        const hasVisibleLesson = Array.from(chapter.querySelectorAll('.lesson-card')).some((lesson) => lesson.style.display !== 'none');
        chapter.style.display = hasVisibleLesson ? '' : 'none';
    });
}

/* -- toast -- */
function toast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.querySelector('.toast-message').textContent = msg;
    t.style.background = ok ? '#16a34a' : '#dc2626';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}

/* -- saved badge -- */
function badge(gqid, cls, msg) {
    const b = document.getElementById('badge-' + gqid);
    b.className = 'saved-badge ' + cls;
    b.textContent = msg;
    setTimeout(() => { b.className = 'saved-badge'; }, 3000);
}

/* -- save -- */
function saveQ(gqid) {
    const btn   = document.getElementById('savebtn-' + gqid);
    const qText = document.getElementById('qt-' + gqid).value.trim();
    if (!qText) { toast('Question text cannot be empty.', false); return; }

    const answers = [];
    for (let i = 0; i < 4; i++) {
        answers.push(document.getElementById('a' + i + '-' + gqid).value.trim());
    }
    if (answers.some(a => !a)) { toast('All 4 answers must be filled in.', false); return; }

    const radio      = document.querySelector(`input[name="cidx-${gqid}"]:checked`);
    const correctIdx = radio ? parseInt(radio.value) : 0;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const fd = new FormData();
    fd.append('action',               'update');
    fd.append('game_question_id',     gqid);
    fd.append('question_text',        qText);
    fd.append('answer_0',             answers[0]);
    fd.append('answer_1',             answers[1]);
    fd.append('answer_2',             answers[2]);
    fd.append('answer_3',             answers[3]);
    fd.append('correct_answer_index', correctIdx);

    fetch('../api/game_questions_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            if (data.success) {
                badge(gqid, 'ok', 'OK Saved');
                toast('Question saved!');
                document.querySelector('#qcard-' + gqid + ' .q-text-preview').textContent = qText;
            } else {
                badge(gqid, 'err', 'X Error');
                toast(data.message || 'Save failed.', false);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            badge(gqid, 'err', 'X Error');
            toast('Network error.', false);
        });
}

/* -- delete -- */
function deleteQ(gqid, lessonId) {
    if (!confirm('Delete this question? This cannot be undone.')) return;

    const fd = new FormData();
    fd.append('action',           'delete');
    fd.append('game_question_id', gqid);

    fetch('../api/game_questions_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('qcard-' + gqid);
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    updateLessonCount(lessonId, -1);
                    doSearch();
                }, 300);
                toast('Question deleted.');
            } else {
                toast(data.message || 'Delete failed.', false);
            }
        })
        .catch(() => toast('Network error.', false));
}

/* -- add question -- */
function submitAdd(lessonId) {
    const btn   = document.getElementById('addbtn-' + lessonId);
    const qText = document.getElementById('new-qt-' + lessonId).value.trim();
    if (!qText) { toast('Question text cannot be empty.', false); return; }

    const answers = [];
    for (let i = 0; i < 4; i++) {
        answers.push(document.getElementById('new-a' + i + '-' + lessonId).value.trim());
    }
    if (answers.some(a => !a)) { toast('All 4 answers must be filled in.', false); return; }

    const radio      = document.querySelector(`input[name="new-cidx-${lessonId}"]:checked`);
    const correctIdx = radio ? parseInt(radio.value) : 0;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

    const fd = new FormData();
    fd.append('action',               'add');
    fd.append('lesson_id',            lessonId);
    fd.append('question_text',        qText);
    fd.append('answer_0',             answers[0]);
    fd.append('answer_1',             answers[1]);
    fd.append('answer_2',             answers[2]);
    fd.append('answer_3',             answers[3]);
    fd.append('correct_answer_index', correctIdx);

    fetch('../api/game_questions_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Question';
            if (data.success) {
                toast('Question added!');
                appendNewCard(lessonId, data.game_question_id, qText, answers, correctIdx);
                resetAddForm(lessonId);
                toggleAddForm(lessonId);
                updateLessonCount(lessonId, 1);
            } else {
                toast(data.message || 'Add failed.', false);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Question';
            toast('Network error.', false);
        });
}

function resetAddForm(lessonId) {
    document.getElementById('new-qt-' + lessonId).value = '';
    for (let i = 0; i < 4; i++) {
        document.getElementById('new-a' + i + '-' + lessonId).value = '';
    }
    const first = document.querySelector(`input[name="new-cidx-${lessonId}"][value="0"]`);
    if (first) first.checked = true;
}

function appendNewCard(lessonId, gqid, qText, answers, correctIdx) {
    const list   = document.getElementById('qlist-' + lessonId);
    const count  = list.querySelectorAll('.q-row').length + 1;
    const labels = ['A','B','C','D'];

    const answerRows = answers.map((a, i) =>
        `<div class="answer-row">
            <span class="answer-letter">${labels[i]}</span>
            <input type="text" class="answer-input" id="a${i}-${gqid}" value="${escHtml(a)}">
            <input type="radio" class="correct-radio" name="cidx-${gqid}" value="${i}"
                   title="Mark as correct" ${i === correctIdx ? 'checked' : ''}>
         </div>`
    ).join('');

    const html = `
    <div class="q-row" id="qcard-${gqid}" data-search="${escHtml(qText.toLowerCase())}">
        <div class="q-row-head" onclick="toggleCard(${gqid})">
            <span class="q-num">${count}</span>
            <span class="q-text-preview">${escHtml(qText)}</span>
            <span class="saved-badge" id="badge-${gqid}"></span>
            <i class="fas fa-chevron-right q-row-caret"></i>
        </div>
        <div class="q-inspector">
            <div class="insp-label">Question</div>
            <textarea class="insp-textarea" id="qt-${gqid}" rows="2">${escHtml(qText)}</textarea>
            <div class="insp-label" style="margin-top:14px;">Answers -- click to mark correct</div>
            <div class="answer-grid">${answerRows}</div>
            <div class="insp-actions">
                <button class="btn-del" onclick="deleteQ(${gqid},${lessonId})"><i class="fas fa-trash"></i> Delete</button>
                <button class="btn-save" id="savebtn-${gqid}" onclick="saveQ(${gqid})"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>`;

    list.insertAdjacentHTML('beforeend', html);
    doSearch();
    setTimeout(() => document.getElementById('qcard-' + gqid)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
}

function updateLessonCount(lessonId, delta) {
    const badge = document.getElementById('lcount-' + lessonId);
    if (badge) {
        const n = Math.max(0, (parseInt(badge.textContent) || 0) + delta);
        badge.textContent = n + ' Q';
    }
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* -- import modal -- */
function openImportModal() {
    document.getElementById('importModal').classList.add('open');
    document.getElementById('importSearch').focus();
}
function closeImportModal() { document.getElementById('importModal').classList.remove('open'); }
function closeImportOnOverlay(e) { if (e.target === document.getElementById('importModal')) closeImportModal(); }

function filterImport() {
    const term   = document.getElementById('importSearch').value.toLowerCase().trim();
    const lesson = document.getElementById('importLessonFilter').value;
    document.querySelectorAll('.import-q-item').forEach(item => {
        const matchText   = !term   || item.dataset.search.includes(term);
        const matchLesson = !lesson || item.dataset.lesson === lesson;
        item.style.display = (matchText && matchLesson) ? '' : 'none';
    });
    updateImportCount();
}

function toggleSelectAll(checked) {
    document.querySelectorAll('.import-q-item').forEach(item => {
        if (item.style.display === 'none') return;
        const chk = item.querySelector('.import-chk');
        chk.checked = checked;
        item.classList.toggle('checked', checked);
    });
    updateImportCount();
}

function updateImportCount() {
    const n             = document.querySelectorAll('.import-chk:checked').length;
    const visible       = document.querySelectorAll('.import-q-item:not([style*="none"]) .import-chk').length;
    const chkVisible    = document.querySelectorAll('.import-q-item:not([style*="none"]) .import-chk:checked').length;
    document.getElementById('importCount').textContent  = n + ' selected';
    document.getElementById('btnImport').disabled       = n === 0;
    document.getElementById('selectAllImport').indeterminate = (chkVisible > 0 && chkVisible < visible);
    document.getElementById('selectAllImport').checked  = visible > 0 && chkVisible === visible;
}

function doImport() {
    const targetLesson = document.getElementById('importTargetLesson').value;
    const btn          = document.getElementById('btnImport');

    const selected = [...document.querySelectorAll('.import-chk:checked')].map(chk => {
        const item = chk.closest('.import-q-item');
        return {
            question_text:        item.dataset.text,
            answer_0:             item.dataset.a0,
            answer_1:             item.dataset.a1,
            answer_2:             item.dataset.a2,
            answer_3:             item.dataset.a3,
            correct_answer_index: item.dataset.correct,
        };
    });
    if (!selected.length) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

    const fd = new FormData();
    fd.append('action',    'import');
    fd.append('lesson_id', targetLesson);
    fd.append('questions', JSON.stringify(selected));

    fetch('../api/game_questions_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> Import Selected';
            if (data.success) {
                toast(`${data.imported} question${data.imported !== 1 ? 's' : ''} imported! Refreshing...`);
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.message || 'Import failed.', false);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> Import Selected';
            toast('Network error.', false);
        });
}

/* -- template download / CSV/XLSX upload -- */
let parsedUploadQuestions = [];

function downloadTemplate() {
    window.location.href = '../api/download_game_questions_template.php';
}

function openUploadModal() {
    document.getElementById('uploadModal').classList.add('open');
    const uploadSel = document.getElementById('uploadTargetLesson');
    if (uploadSel) {
        uploadSel.style.width = '100%';
        uploadSel.style.maxWidth = '100%';
        uploadSel.style.minWidth = '0';
        uploadSel.style.boxSizing = 'border-box';
    }
}
function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('open');
    document.getElementById('uploadFile').value = '';
    document.getElementById('uploadPreview').innerHTML = '';
    parsedUploadQuestions = [];
    updateUploadCount();
}
function closeUploadOnOverlay(e) { if (e.target === document.getElementById('uploadModal')) closeUploadModal(); }

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
}

function parseCsvLine(line) {
    const result = [];
    let cur = '', inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (inQuotes) {
            if (ch === '"') {
                if (line[i + 1] === '"') { cur += '"'; i++; }
                else inQuotes = false;
            } else cur += ch;
        } else {
            if (ch === '"') inQuotes = true;
            else if (ch === ',') { result.push(cur); cur = ''; }
            else cur += ch;
        }
    }
    result.push(cur);
    return result.map(s => s.trim());
}

function buildQuestionsFromRows(rows, sourceLabel) {
    const normalizedRows = rows
        .map(r => Array.isArray(r) ? r : [])
        .filter(r => r.some(c => String(c).trim() !== ''));

    if (!normalizedRows.length) {
        toast(`${sourceLabel} file is empty.`, false);
        return [];
    }

    const header = normalizedRows[0].map(h => String(h).trim().toLowerCase());
    const aliases = {
        question_text: ['question text', 'question_text'],
        answer_0: ['answer a', 'answer_0'],
        answer_1: ['answer b', 'answer_1'],
        answer_2: ['answer c', 'answer_2'],
        answer_3: ['answer d', 'answer_3'],
        correct_answer_index: ['correct answer', 'correct_answer_index']
    };
    const idxMap = {};
    Object.keys(aliases).forEach(key => {
        idxMap[key] = -1;
        aliases[key].some(alias => {
            const idx = header.indexOf(alias);
            if (idx !== -1) {
                idxMap[key] = idx;
                return true;
            }
            return false;
        });
    });
    if (Object.values(idxMap).some(i => i === -1)) {
        toast('Template header must include: Question Text, Answer A-D, Correct Answer', false);
        return [];
    }

    const questions = [];
    for (let i = 1; i < normalizedRows.length; i++) {
        const cols = normalizedRows[i].map(v => String(v).trim());
        const qt   = cols[idxMap.question_text] || '';
        const a0   = cols[idxMap.answer_0] || '';
        const a1   = cols[idxMap.answer_1] || '';
        const a2   = cols[idxMap.answer_2] || '';
        const a3   = cols[idxMap.answer_3] || '';
        const rawCorrect = String(cols[idxMap.correct_answer_index] || '').trim().toUpperCase();
        let idx = { A: 0, B: 1, C: 2, D: 3 }[rawCorrect];
        if (idx === undefined) {
            const numeric = parseInt(rawCorrect, 10);
            if (!isNaN(numeric)) {
                idx = numeric >= 0 && numeric <= 3 ? numeric : numeric - 1;
            }
        }
        if (isNaN(idx) || idx < 0 || idx > 3) idx = 0;
        if (!qt || !a0 || !a1 || !a2 || !a3) continue;
        questions.push({ question_text: qt, answer_0: a0, answer_1: a1, answer_2: a2, answer_3: a3, correct_answer_index: idx });
    }

    return questions;
}

function handleCsvFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        const text  = String(e.target.result).replace(/\r/g, '');
        const lines = text.split('\n').filter(l => l.trim() !== '');
        const rows = lines.map(parseCsvLine);
        parsedUploadQuestions = buildQuestionsFromRows(rows, 'CSV');
        renderUploadPreview();
        updateUploadCount();
    };
    reader.readAsText(file);
}

function handleXlsxFile(file) {
    if (!file) return;
    if (typeof XLSX === 'undefined') {
        toast('XLSX support is not available right now. Please upload CSV.', false);
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        const workbook = XLSX.read(e.target.result, { type: 'array' });
        if (!workbook.SheetNames.length) {
            parsedUploadQuestions = [];
            renderUploadPreview();
            updateUploadCount();
            toast('XLSX file has no worksheet.', false);
            return;
        }

        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1, raw: false, defval: '' });
        parsedUploadQuestions = buildQuestionsFromRows(rows, 'XLSX');
        renderUploadPreview();
        updateUploadCount();
    };
    reader.readAsArrayBuffer(file);
}

function handleUploadFile(file) {
    if (!file) return;
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (ext === 'csv') {
        handleCsvFile(file);
        return;
    }
    if (ext === 'xlsx') {
        handleXlsxFile(file);
        return;
    }

    toast('Please upload a .csv or .xlsx file.', false);
}

function renderUploadPreview() {
    const box = document.getElementById('uploadPreview');
    if (!parsedUploadQuestions.length) {
        box.innerHTML = '<div class="modal-empty">No valid questions found in file.</div>';
        return;
    }
    box.innerHTML = parsedUploadQuestions.map((q, i) => `
        <div class="import-q-item">
            <div style="flex:1;">
                <div class="import-q-text">${i + 1}. ${escapeHtml(q.question_text)}</div>
                <div class="import-q-answers">
                    ${['A', 'B', 'C', 'D'].map((l, ci) => `<span class="import-q-ans${ci === q.correct_answer_index ? ' correct' : ''}">${l}: ${escapeHtml([q.answer_0, q.answer_1, q.answer_2, q.answer_3][ci])}</span>`).join('')}
                </div>
            </div>
        </div>
    `).join('');
}

function updateUploadCount() {
    const n = parsedUploadQuestions.length;
    document.getElementById('uploadCount').textContent = n + ' question' + (n !== 1 ? 's' : '') + ' parsed';
    document.getElementById('btnUpload').disabled = n === 0;
}

function doUpload() {
    const targetLesson = document.getElementById('uploadTargetLesson').value;
    const btn          = document.getElementById('btnUpload');
    if (!parsedUploadQuestions.length) return;

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    const fd = new FormData();
    fd.append('action',    'import');
    fd.append('lesson_id', targetLesson);
    fd.append('questions', JSON.stringify(parsedUploadQuestions));

    fetch('../api/game_questions_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Upload Questions';
            if (data.success) {
                toast(`${data.imported} question${data.imported !== 1 ? 's' : ''} uploaded! Refreshing...`);
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.message || 'Upload failed.', false);
            }
        })
        .catch(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Upload Questions';
            toast('Network error.', false);
        });
}

document.addEventListener('DOMContentLoaded', function () {
    doSearch();
});
</script>
</body>
</html>