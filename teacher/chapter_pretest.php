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
    // All chapters including lesson_content for AI context extraction
    $chapters = $db->query("
        SELECT chapter_id, chapter_title, chapter_order, lesson_content, CHAR_LENGTH(lesson_content) AS text_len 
        FROM chapters 
        ORDER BY chapter_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Existing pretests created by this teacher
    $ptRows = $db->prepare("
        SELECT cp.pretest_id, cp.chapter_id, cp.title, cp.passing_score, cp.is_active,
               COUNT(cpq.pq_id) AS question_count
        FROM chapter_pretests cp
        LEFT JOIN chapter_pretest_questions cpq ON cpq.pretest_id = cp.pretest_id
        WHERE cp.created_by_teacher_id = ?
        GROUP BY cp.pretest_id
    ");
    $ptRows->execute([$teacher_id]);
    $pretestMap = [];
    foreach ($ptRows->fetchAll() as $pt) {
        $pretestMap[$pt['chapter_id']] = $pt;
    }

    // All pretest questions indexed by pretest_id
    if (!empty($pretestMap)) {
        $ptIds = array_column(array_values($pretestMap), 'pretest_id');
        $in    = implode(',', array_map('intval', $ptIds));
        $qRows = $db->query("
            SELECT pq_id, pretest_id, question_text, answer_0, answer_1, answer_2, answer_3,
                   correct_answer_index, question_order
            FROM chapter_pretest_questions
            WHERE pretest_id IN ($in)
            ORDER BY question_order, pq_id
        ")->fetchAll();
        $questionsMap = [];
        foreach ($qRows as $q) {
            $questionsMap[$q['pretest_id']][] = $q;
        }
    } else {
        $questionsMap = [];
    }
} catch (PDOException $e) {
    $dbError       = 'A database error occurred. Please try again later.';
    error_log('chapter_pretest.php DB error: ' . $e->getMessage());
    $chapters      = [];
    $pretestMap    = [];
    $questionsMap  = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Pretests - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --pri:#4f46e5;--pri-50:#eef2ff;--pri-100:#e0e7ff;--pri-dark:#4338ca;--pri-darker:#3730a3;
            --ok:#16a34a;--ok-50:#f0fdf4;--ok-100:#dcfce7;--ok-200:#bbf7d0;
            --warn:#d97706;--warn-50:#fffbeb;--warn-100:#fef3c7;
            --danger:#dc2626;--danger-50:#fef2f2;
            --gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-300:#cbd5e1;
            --gray-400:#94a3b8;--gray-500:#64748b;--gray-700:#334155;--gray-900:#0f172a;
            --radius:10px;--radius-lg:14px;
            --shadow:0 1px 4px rgba(0,0,0,.05),0 1px 2px rgba(0,0,0,.04);
            --shadow-md:0 4px 14px rgba(0,0,0,.08);
        }
        .pt-wrap { padding: 28px 32px; max-width: 960px; }

        /* stats */
        .stats-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px; }
        .stat-card {
            background:#fff;border:1.5px solid var(--gray-200);border-radius:var(--radius-lg);
            padding:16px 20px;display:flex;align-items:center;gap:14px;
            box-shadow:var(--shadow);transition:transform .15s,box-shadow .15s;
        }
        .stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
        .stat-icon{width:44px;height:44px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1rem;}
        .stat-icon--blue{background:#dbeafe;color:#1d4ed8;}
        .stat-icon--green{background:var(--ok-100);color:var(--ok);}
        .stat-icon--purple{background:#ede9fe;color:#7c3aed;}
        .stat-num{font-size:1.6rem;font-weight:900;color:var(--gray-900);line-height:1;}
        .stat-label{font-size:.72rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;}

        /* top bar */
        .pt-topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px;}
        .pt-title{font-size:1.1rem;font-weight:800;color:var(--gray-900);display:flex;align-items:center;gap:10px;}
        .pt-count{background:var(--pri-100);color:var(--pri-darker);font-size:.71rem;font-weight:800;border-radius:20px;padding:4px 12px;}

        /* AI Button & Modal UI Styles */
        .btn-ai-open {
            padding: 9px 18px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border: none; border-radius: var(--radius);
            font-size: .83rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 7px;
            box-shadow: 0 2px 8px rgba(16,185,129,.3);
            transition: opacity .15s, transform .15s, box-shadow .15s;
        }
        .btn-ai-open:hover { opacity: .92; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(16,185,129,.4); }

        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.65);
            backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff; border-radius: 18px;
            width: 780px; max-width: 95vw; max-height: 90vh;
            display: flex; flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,.28);
            animation: modalIn .2s cubic-bezier(.34,1.56,.64,1);
            overflow: hidden;
        }
        @keyframes modalIn {
            from { transform: scale(.95) translateY(-8px); opacity: 0; }
            to   { transform: scale(1)   translateY(0);    opacity: 1; }
        }
        .modal-header {
            display: flex; align-items: center; gap: 14px;
            padding: 20px 24px; border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, #f0fdf4, #eef2ff);
        }
        .modal-header-icon {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16,185,129,.3);
        }
        .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--gray-900); flex: 1; font-weight: 800; }
        .modal-close {
            background: #fff; border: 1px solid var(--gray-200); color: var(--gray-600);
            cursor: pointer; font-size: 1rem; padding: 6px 11px;
            border-radius: 8px; line-height: 1; transition: all .15s;
        }
        .modal-close:hover { background: #fee2e2; color: var(--danger); border-color: #fca5a5; }

        .modal-body { flex: 1; overflow-y: auto; padding: 22px 26px; position: relative; }
        .modal-footer {
            padding: 16px 26px; border-top: 1px solid var(--gray-200);
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; flex-wrap: wrap; background: #fafbfc;
        }
        .modal-selected-count {
            font-size: .83rem; font-weight: 700;
            background: #d1fae5; color: #065f46;
            padding: 6px 16px; border-radius: 20px;
        }
        .modal-lesson-filter {
            width: 100%; box-sizing: border-box;
            padding: 11px 14px; border: 1.5px solid var(--gray-200);
            border-radius: 10px; font-size: .88rem; color: var(--gray-900);
            background: #fff; font-family: inherit; transition: border-color .15s, box-shadow .15s;
        }
        .modal-lesson-filter:focus { outline: none; border-color: var(--pri); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

        .btn-import {
            padding: 10px 22px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border: none; border-radius: 10px;
            font-size: .86rem; font-weight: 700;
            cursor: pointer; box-shadow: 0 2px 8px rgba(16,185,129,.3);
            transition: all .15s ease;
        }
        .btn-import:hover { opacity: .92; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(16,185,129,.4); }
        .btn-import:disabled { background: #86efac; cursor: not-allowed; box-shadow: none; transform: none; }

        /* Draft Card Item */
        .ai-draft-card {
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: 14px; padding: 18px; margin-bottom: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,.03); transition: border-color .15s;
        }
        .ai-draft-card:hover { border-color: #cbd5e1; }

        .ai-choice-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
            margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--gray-200);
        }
        .ai-choice-item {
            font-size: .85rem; padding: 8px 12px; border-radius: 8px;
            background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-200);
        }
        .ai-choice-item.correct {
            background: #d1fae5; color: #065f46; border-color: #86efac; font-weight: 700;
        }

        .btn-accuracy-proof {
            font-size: 0.75rem; font-weight: 700; padding: 6px 12px;
            border-radius: 8px; background: #e0f2fe; color: #0369a1;
            border: 1px solid #bae6fd; cursor: pointer; transition: all 0.15s ease;
        }
        .btn-accuracy-proof:hover { background: #0284c7; color: #ffffff; }
        .accuracy-proof-box {
            display: none; margin-top: 12px; padding: 12px 14px;
            background: #f0fdf4; border-left: 4px solid var(--ok);
            border-radius: 8px; font-size: .84rem; color: #166534; line-height: 1.5;
        }
        .accuracy-proof-box.open { display: block; }

        /* chapter card */
        .ch-card{
            background:#fff;border:1.5px solid var(--gray-200);border-radius:var(--radius-lg);
            margin-bottom:18px;box-shadow:var(--shadow);overflow:hidden;
            transition:border-color .15s,box-shadow .15s;
        }
        .ch-card:hover{border-color:#c7d2fe;box-shadow:0 2px 10px rgba(79,70,229,.08);}
        .ch-header{
            display:flex;align-items:center;gap:12px;padding:16px 18px;
            cursor:pointer;user-select:none;transition:background .15s;
        }
        .ch-header:hover{background:#fafafe;}
        .ch-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}
        .ch-icon--active{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;}
        .ch-icon--inactive{background:var(--gray-100);color:var(--gray-400);}
        .ch-name{flex:1;font-size:.95rem;font-weight:700;color:#1e293b;}
        .ch-badge{display:flex;align-items:center;gap:8px;flex-shrink:0;}
        .badge{font-size:.71rem;font-weight:700;padding:4px 11px;border-radius:20px;}
        .badge--active{background:#d1fae5;color:#065f46;}
        .badge--inactive{background:var(--gray-100);color:var(--gray-500);}
        .badge--none{background:var(--warn-100);color:#92400e;}
        .badge--q{background:var(--pri-100);color:var(--pri-darker);}
        .ch-caret{color:var(--gray-300);font-size:.78rem;transition:transform .25s;flex-shrink:0;}
        .ch-header.closed .ch-caret{transform:rotate(-90deg);}
        .ch-body{border-top:1px solid var(--gray-100);}
        .ch-body.hidden{display:none;}

        /* pretest config panel */
        .pt-config{
            padding:18px 20px;background:linear-gradient(to bottom,var(--pri-50),#fafcff);
            border-bottom:1px solid var(--pri-100);
        }
        .pt-config-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;}
        .pt-config-label{font-size:.78rem;font-weight:700;color:var(--pri-darker);text-transform:uppercase;letter-spacing:.07em;}
        .pt-config-input{
            padding:7px 12px;border:1.5px solid var(--pri-100);border-radius:8px;
            font-size:.85rem;color:#1e293b;background:#fff;font-family:inherit;
            transition:border-color .15s,box-shadow .15s;
        }
        .pt-config-input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(79,70,229,.1);}
        .pt-config-input--title{flex:1;min-width:160px;}
        .pt-config-input--pct{width:80px;}
        .btn-save-config{
            padding:8px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
            border:none;border-radius:9px;font-size:.83rem;font-weight:700;cursor:pointer;
            display:flex;align-items:center;gap:7px;box-shadow:0 2px 6px rgba(79,70,229,.3);
            transition:opacity .15s,transform .1s;
        }
        .btn-save-config:hover{opacity:.9;transform:translateY(-1px);}
        .btn-save-config:disabled{background:#a5b4fc;cursor:not-allowed;box-shadow:none;transform:none;}
        .btn-toggle{
            padding:7px 13px;border-radius:9px;font-size:.8rem;font-weight:700;cursor:pointer;
            border:1.5px solid;transition:background .15s,color .15s;display:flex;align-items:center;gap:6px;
        }
        .btn-toggle--on{background:var(--ok-50);color:var(--ok);border-color:var(--ok-200);}
        .btn-toggle--on:hover{background:var(--ok-100);}
        .btn-toggle--off{background:var(--danger-50);color:var(--danger);border-color:#fecaca;}
        .btn-toggle--off:hover{background:#fee2e2;}
        .btn-del-pretest{
            padding:7px 13px;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;
            background:transparent;color:var(--danger);border:1.5px solid #fecaca;
            display:flex;align-items:center;gap:6px;transition:background .15s;
        }
        .btn-del-pretest:hover{background:var(--danger-50);}
        .config-hint{font-size:.75rem;color:var(--gray-400);margin-top:4px;}

        /* question rows */
        .q-section{padding:0 0 10px;}
        .q-section-head{
            display:flex;align-items:center;justify-content:space-between;
            padding:10px 20px;background:var(--gray-50);border-bottom:1px solid var(--gray-100);
        }
        .q-section-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .q-section-title{font-size:.78rem;font-weight:800;color:var(--gray-700);text-transform:uppercase;letter-spacing:.07em;}
        .btn-add-q{
            display:flex;align-items:center;gap:5px;background:var(--ok-50);color:var(--ok);
            border:1.5px solid var(--ok-200);border-radius:8px;padding:6px 12px;
            font-size:.75rem;font-weight:700;cursor:pointer;flex-shrink:0;
            transition:background .15s,transform .1s;
        }
        .btn-add-q:hover{background:var(--ok-100);transform:scale(1.03);}
        .btn-template-q{
            background:var(--pri-50);color:var(--pri-darker);border-color:var(--pri-100);text-decoration:none;
        }
        .btn-template-q:hover{background:var(--pri-100);}
        .btn-upload-q{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
        .btn-upload-q:hover{background:#dbeafe;}

        .q-row{border-bottom:1px solid #f9fbfc;transition:opacity .3s;}
        .q-row:last-child{border-bottom:none;}
        .q-row-head{
            display:flex;align-items:center;gap:12px;padding:12px 20px;
            cursor:pointer;user-select:none;transition:background .15s;
        }
        .q-row-head:hover{background:#fafbfe;}
        .q-row.open .q-row-head{background:#f0f9ff;border-bottom:1px solid #e0f2fe;}
        .q-num{width:24px;height:24px;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.69rem;font-weight:800;flex-shrink:0;}
        .q-text-preview{flex:1;font-size:.86rem;color:#1e293b;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .saved-badge{font-size:.71rem;font-weight:700;padding:3px 9px;border-radius:20px;display:none;flex-shrink:0;}
        .saved-badge.ok{display:inline-block;background:#d1fae5;color:#065f46;}
        .saved-badge.err{display:inline-block;background:#fee2e2;color:#991b1b;}
        .q-row-caret{color:var(--gray-300);font-size:.78rem;transition:transform .2s;}
        .q-row.open .q-row-caret{transform:rotate(90deg);}

        .q-inspector{display:none;padding:18px 20px 20px;background:#fafbff;border-top:1px solid var(--gray-200);}
        .q-row.open .q-inspector{display:block;}
        .insp-label{font-size:.7rem;font-weight:800;color:#6d7280;text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;display:flex;align-items:center;gap:6px;}
        .insp-label::before{content:'';display:inline-block;width:3px;height:12px;background:var(--pri);border-radius:2px;}
        .insp-textarea{width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:9px;font-size:.85rem;color:#1e293b;background:#fff;font-family:inherit;resize:vertical;line-height:1.5;transition:border-color .15s,box-shadow .15s;}
        .insp-textarea:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(79,70,229,.1);}
        .answer-grid{display:flex;flex-direction:column;gap:7px;margin-top:7px;}
        .answer-row{display:flex;align-items:center;gap:8px;padding:4px;border-radius:8px;transition:background .15s;}
        .answer-letter{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:900;flex-shrink:0;background:var(--gray-100);color:var(--gray-500);border:1.5px solid var(--gray-200);transition:all .2s;}
        .answer-input{flex:1;padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:.84rem;color:#1e293b;background:#fff;font-family:inherit;transition:border-color .15s,box-shadow .15s;}
        .answer-input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(79,70,229,.08);}
        .correct-radio{width:17px;height:17px;accent-color:var(--ok);cursor:pointer;flex-shrink:0;}
        .answer-row:has(.correct-radio:checked){background:var(--ok-50);}
        .answer-row:has(.correct-radio:checked) .answer-letter{background:var(--ok);color:#fff;border-color:var(--ok);}
        .answer-row:has(.correct-radio:checked) .answer-input{border-color:#4ade80;background:var(--ok-50);}
        .insp-actions{display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-100);}
        .btn-save{padding:8px 20px;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;border:none;border-radius:9px;font-size:.83rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(79,70,229,.3);transition:opacity .15s,transform .1s,box-shadow .15s;}
        .btn-save:hover{opacity:.9;transform:translateY(-1px);}
        .btn-save:disabled{background:#a5b4fc;cursor:not-allowed;box-shadow:none;transform:none;}
        .btn-del{padding:8px 14px;background:transparent;color:#ef4444;border:1.5px solid #fecaca;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background .15s,border-color .15s;}
        .btn-del:hover{background:var(--danger-50);border-color:#f87171;}

        /* add question form */
        .add-q-form{display:none;padding:18px 20px;background:linear-gradient(to bottom,var(--ok-50),#fafffe);border-top:2px dashed #86efac;}
        .add-q-form.open{display:block;}
        .add-q-title{font-size:.86rem;font-weight:800;color:#15803d;margin:0 0 16px;display:flex;align-items:center;gap:8px;}
        .add-insp-label{font-size:.7rem;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;display:flex;align-items:center;gap:6px;}
        .add-insp-label::before{content:'';display:inline-block;width:3px;height:12px;background:var(--ok);border-radius:2px;}
        .add-textarea{width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--ok-200);border-radius:9px;font-size:.85rem;color:#1e293b;background:#fff;font-family:inherit;resize:vertical;line-height:1.5;transition:border-color .15s,box-shadow .15s;}
        .add-textarea:focus{outline:none;border-color:var(--ok);box-shadow:0 0 0 3px rgba(22,163,74,.1);}
        .add-answer-grid{display:flex;flex-direction:column;gap:7px;margin-top:7px;}
        .add-answer-row{display:flex;align-items:center;gap:8px;padding:4px;border-radius:8px;transition:background .15s;}
        .add-answer-letter{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:900;flex-shrink:0;background:var(--ok-100);color:#15803d;border:1.5px solid var(--ok-200);transition:all .2s;}
        .add-answer-input{flex:1;padding:8px 12px;border:1.5px solid var(--ok-200);border-radius:8px;font-size:.84rem;color:#1e293b;background:#fff;font-family:inherit;transition:border-color .15s,box-shadow .15s;}
        .add-answer-input:focus{outline:none;border-color:var(--ok);box-shadow:0 0 0 3px rgba(22,163,74,.1);}
        .add-correct-radio{width:17px;height:17px;accent-color:var(--ok);cursor:pointer;flex-shrink:0;}
        .add-answer-row:has(.add-correct-radio:checked){background:var(--ok-50);}
        .add-answer-row:has(.add-correct-radio:checked) .add-answer-letter{background:var(--ok);color:#fff;border-color:var(--ok);}
        .add-answer-row:has(.add-correct-radio:checked) .add-answer-input{border-color:#4ade80;background:var(--ok-50);}
        .add-form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:14px;border-top:1px dashed #86efac;}
        .btn-add-submit{padding:8px 20px;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;border:none;border-radius:9px;font-size:.83rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(22,163,74,.3);transition:opacity .15s,transform .1s;}
        .btn-add-submit:hover{opacity:.9;transform:translateY(-1px);}
        .btn-add-submit:disabled{background:#86efac;cursor:not-allowed;box-shadow:none;transform:none;}
        .btn-cancel{padding:8px 14px;background:transparent;color:var(--gray-500);border:1.5px solid var(--gray-200);border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s;}
        .btn-cancel:hover{background:var(--gray-100);border-color:var(--gray-300);}

        /* empty state inside chapter */
        .q-empty{text-align:center;padding:32px 20px;color:var(--gray-400);}
        .q-empty i{font-size:2rem;display:block;margin-bottom:10px;color:#c7d2fe;}
        .q-empty p{font-size:.85rem;}

        /* setup panel for chapters without a pretest */
        .setup-panel{padding:24px 20px;text-align:center;}
        .setup-panel p{font-size:.88rem;color:var(--gray-500);margin-bottom:14px;}
        .btn-setup{
            padding:9px 22px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
            border:none;border-radius:9px;font-size:.85rem;font-weight:700;cursor:pointer;
            box-shadow:0 2px 8px rgba(79,70,229,.3);transition:opacity .15s,transform .1s;
            display:inline-flex;align-items:center;gap:8px;
        }
        .btn-setup:hover{opacity:.9;transform:translateY(-1px);}
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>
    <main class="main-content">
        <header class="top-header">
            <h1><i class="fas fa-clipboard-check" style="color:#4f46e5;margin-right:8px;"></i>Chapter Pretests</h1>
            <a href="profile.php" class="user-info" title="My Profile">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <i class="fas fa-user-circle"></i>
            </a>
        </header>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Chapter Pretests</span>
        </nav>

        <?php if (!empty($dbError)): ?>
        <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?>
        </div>
        <?php endif; ?>

        <div class="pt-wrap">

            <!-- Stats -->
            <?php
            $totalPretests  = count($pretestMap);
            $activePretests = count(array_filter($pretestMap, fn($p) => $p['is_active']));
            $totalPQs       = array_sum(array_column(array_values($pretestMap), 'question_count'));
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon--blue"><i class="fas fa-clipboard-check"></i></div>
                    <div>
                        <div class="stat-num"><?php echo $totalPretests; ?></div>
                        <div class="stat-label">Pretests</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon--green"><i class="fas fa-toggle-on"></i></div>
                    <div>
                        <div class="stat-num"><?php echo $activePretests; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon--purple"><i class="fas fa-question-circle"></i></div>
                    <div>
                        <div class="stat-num"><?php echo $totalPQs; ?></div>
                        <div class="stat-label">Total Questions</div>
                    </div>
                </div>
            </div>

            <!-- Top bar -->
            <div class="pt-topbar">
                <div class="pt-title">
                    Chapter Pretests
                    <span class="pt-count"><?php echo count($chapters); ?> chapter<?php echo count($chapters) !== 1 ? 's' : ''; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button class="btn-ai-open" onclick="openAIModal()">
                        <i class="fas fa-wand-magic-sparkles"></i> AI Assistant
                    </button>
                </div>
            </div>

            <?php if (empty($chapters)): ?>
            <div style="text-align:center;padding:72px 20px;color:var(--gray-400);background:#fff;border-radius:var(--radius-lg);border:1.5px dashed var(--gray-200);">
                <i class="fas fa-sitemap" style="font-size:3rem;display:block;margin-bottom:14px;color:#c7d2fe;"></i>
                <p>No chapters found. Create chapters first.</p>
            </div>
            <?php else: ?>

            <?php foreach ($chapters as $ch):
                $cid     = $ch['chapter_id'];
                $pretest = $pretestMap[$cid] ?? null;
                $ptId    = $pretest ? $pretest['pretest_id'] : null;
                $qs      = $ptId && isset($questionsMap[$ptId]) ? $questionsMap[$ptId] : [];
            ?>
            <div class="ch-card" id="chcard-<?php echo $cid; ?>">

                <!-- Chapter header -->
                <div class="ch-header" onclick="toggleChapter(this)">
                    <div class="ch-icon <?php echo $pretest && $pretest['is_active'] ? 'ch-icon--active' : 'ch-icon--inactive'; ?>" id="chicon-<?php echo $cid; ?>">
                        <i class="fas fa-<?php echo $pretest ? 'clipboard-check' : 'layer-group'; ?>"></i>
                    </div>
                    <span class="ch-name"><?php echo htmlspecialchars($ch['chapter_title']); ?></span>
                    <div class="ch-badge">
                        <?php if (!$pretest): ?>
                            <span class="badge badge--none">No Pretest</span>
                        <?php elseif ($pretest['is_active']): ?>
                            <span class="badge badge--active" id="activebadge-<?php echo $cid; ?>">Active</span>
                        <?php else: ?>
                            <span class="badge badge--inactive" id="activebadge-<?php echo $cid; ?>">Inactive</span>
                        <?php endif; ?>
                        <?php if ($pretest): ?>
                            <span class="badge badge--q" id="qcount-<?php echo $cid; ?>"><?php echo $pretest['question_count']; ?> Q</span>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-chevron-down ch-caret"></i>
                </div>

                <div class="ch-body hidden" id="chbody-<?php echo $cid; ?>">

                    <?php if (!$pretest): ?>
                    <!-- Setup panel -->
                    <div class="setup-panel" id="setup-<?php echo $cid; ?>">
                        <i class="fas fa-clipboard-plus" style="font-size:2rem;color:#c7d2fe;display:block;margin-bottom:12px;"></i>
                        <p>No pretest configured for this chapter yet.<br>
                           Create one so students must pass it before playing the game.</p>
                        <button class="btn-setup" onclick="showSetupForm(<?php echo $cid; ?>)">
                            <i class="fas fa-plus"></i> Create Pretest
                        </button>
                    </div>
                    <!-- Setup form (initially hidden) -->
                    <div class="pt-config" id="configform-<?php echo $cid; ?>" style="display:none;">
                        <div class="pt-config-label" style="margin-bottom:10px;">Pretest Settings</div>
                        <div class="pt-config-row">
                            <input type="text" class="pt-config-input pt-config-input--title"
                                   id="cfg-title-<?php echo $cid; ?>" placeholder="Pretest title…" value="Chapter Pretest">
                            <button class="btn-save-config" id="cfgbtn-<?php echo $cid; ?>"
                                    onclick="saveConfig(<?php echo $cid; ?>)">
                                <i class="fas fa-save"></i> Save &amp; Activate
                            </button>
                            <button class="btn-cancel" onclick="hideSetupForm(<?php echo $cid; ?>)">Cancel</button>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- Config panel for existing pretest -->
                    <div class="pt-config" id="configform-<?php echo $cid; ?>">
                        <div class="pt-config-label" style="margin-bottom:10px;">Pretest Settings</div>
                        <div class="pt-config-row">
                            <input type="text" class="pt-config-input pt-config-input--title"
                                   id="cfg-title-<?php echo $cid; ?>" value="<?php echo htmlspecialchars($pretest['title']); ?>">
                            <button class="btn-save-config" id="cfgbtn-<?php echo $cid; ?>"
                                    onclick="saveConfig(<?php echo $cid; ?>)">
                                <i class="fas fa-save"></i> Save
                            </button>
                            <button class="btn-toggle <?php echo $pretest['is_active'] ? 'btn-toggle--on' : 'btn-toggle--off'; ?>"
                                    id="togglebtn-<?php echo $cid; ?>"
                                    onclick="toggleActive(<?php echo $cid; ?>, <?php echo $ptId; ?>)">
                                <i class="fas fa-<?php echo $pretest['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                <?php echo $pretest['is_active'] ? 'Active' : 'Inactive'; ?>
                            </button>
                            <button class="btn-del-pretest"
                                    onclick="deletePretest(<?php echo $cid; ?>, <?php echo $ptId; ?>)">
                                <i class="fas fa-trash"></i> Delete Pretest
                            </button>
                        </div>
                    </div>

                    <!-- Question list -->
                    <div class="q-section">
                        <div class="q-section-head">
                            <span class="q-section-title">Pretest Questions</span>
                            <div class="q-section-tools">
                                <a class="btn-add-q btn-template-q" href="../api/download_pretest_questions_template.php">
                                    <i class="fas fa-file-download"></i> Template
                                </a>
                                <button class="btn-add-q btn-upload-q" id="importbtn-<?php echo $cid; ?>"
                                        onclick="triggerImport(<?php echo $cid; ?>, <?php echo $ptId; ?>)">
                                    <i class="fas fa-file-excel"></i> Upload Excel
                                </button>
                                <button class="btn-add-q" onclick="toggleAddForm(<?php echo $cid; ?>, <?php echo $ptId; ?>)">
                                    <i class="fas fa-plus"></i> Add Question
                                </button>
                            </div>
                        </div>

                        <input type="file" id="importfile-<?php echo $cid; ?>" accept=".xlsx,.xls,.csv"
                               style="display:none;" onchange="uploadPQFile(<?php echo $cid; ?>, <?php echo $ptId; ?>, this)">

                        <div class="q-list" id="qlist-<?php echo $cid; ?>">
                        <?php if (empty($qs)): ?>
                        <div class="q-empty" id="qempty-<?php echo $cid; ?>">
                            <i class="fas fa-question-circle"></i>
                            <p>No questions yet. Add some!</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($qs as $qi => $q):
                            $pqid = $q['pq_id'];
                        ?>
                        <div class="q-row" id="pqcard-<?php echo $pqid; ?>">
                            <div class="q-row-head" onclick="toggleCard('<?php echo $pqid; ?>')">
                                <span class="q-num"><?php echo $qi + 1; ?></span>
                                <span class="q-text-preview"><?php echo htmlspecialchars($q['question_text']); ?></span>
                                <span class="saved-badge" id="badge-<?php echo $pqid; ?>"></span>
                                <i class="fas fa-chevron-right q-row-caret"></i>
                            </div>
                            <div class="q-inspector">
                                <div class="insp-label">Question</div>
                                <textarea class="insp-textarea" id="pqt-<?php echo $pqid; ?>" rows="2"><?php echo htmlspecialchars($q['question_text']); ?></textarea>
                                <div class="insp-label" style="margin-top:14px;">Answers — click ○ to mark correct</div>
                                <div class="answer-grid">
                                    <?php foreach (['A','B','C','D'] as $ai => $letter): ?>
                                    <div class="answer-row">
                                        <span class="answer-letter"><?php echo $letter; ?></span>
                                        <input type="text" class="answer-input"
                                               id="pa<?php echo $ai; ?>-<?php echo $pqid; ?>"
                                               value="<?php echo htmlspecialchars($q['answer_' . $ai]); ?>">
                                        <input type="radio" class="correct-radio"
                                               name="pcidx-<?php echo $pqid; ?>"
                                               value="<?php echo $ai; ?>"
                                               <?php echo $q['correct_answer_index'] == $ai ? 'checked' : ''; ?>>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="insp-actions">
                                    <button class="btn-del" onclick="deletePQ(<?php echo $pqid; ?>, <?php echo $cid; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <button class="btn-save" id="savepqbtn-<?php echo $pqid; ?>"
                                            onclick="savePQ(<?php echo $pqid; ?>)">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </div><!-- /.q-list -->

                        <!-- Add question inline form -->
                        <div class="add-q-form" id="addform-<?php echo $cid; ?>">
                            <div class="add-q-title">
                                <i class="fas fa-plus-circle"></i> New Pretest Question
                            </div>
                            <div class="add-insp-label">Question</div>
                            <textarea class="add-textarea" id="new-pqt-<?php echo $cid; ?>" rows="2" placeholder="Enter question…"></textarea>
                            <div class="add-insp-label" style="margin-top:12px;">Answers — click ○ to mark correct</div>
                            <div class="add-answer-grid">
                                <?php foreach (['A','B','C','D'] as $ni => $letter): ?>
                                <div class="add-answer-row">
                                    <span class="add-answer-letter"><?php echo $letter; ?></span>
                                    <input type="text" class="add-answer-input"
                                           id="new-pa<?php echo $ni; ?>-<?php echo $cid; ?>"
                                           placeholder="Answer <?php echo $letter; ?>…">
                                    <input type="radio" class="add-correct-radio"
                                           name="new-pcidx-<?php echo $cid; ?>"
                                           value="<?php echo $ni; ?>"
                                           <?php echo $ni === 0 ? 'checked' : ''; ?>>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="add-form-actions">
                                <button class="btn-cancel" onclick="closeAddForm(<?php echo $cid; ?>)">Cancel</button>
                                <button class="btn-add-submit" id="addbtn-<?php echo $cid; ?>"
                                        onclick="submitAddPQ(<?php echo $cid; ?>)"
                                        data-ptid="<?php echo $ptId; ?>">
                                    <i class="fas fa-plus"></i> Add Question
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
                </div><!-- /.ch-body -->
            </div><!-- /.ch-card -->
            <?php endforeach; ?>

            <?php endif; ?>
        </div><!-- /.pt-wrap -->
    </main>
</div>

<!-- Toast -->
<div class="toast" id="toast"><span class="toast-message"></span></div>

<!-- AI Generator Modal -->
<div class="modal-overlay" id="aiModal" onclick="closeAIOnOverlay(event)">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-icon">
                <i class="fas fa-brain"></i>
            </div>
            <div>
                <h3>AI Pretest Questions Generator</h3>
                <div style="font-size:0.8rem; color:#64748b; font-weight:500;">Select a single chapter to extract stored content and generate non-duplicate Pretest MCQs.</div>
            </div>
            <button class="modal-close" onclick="closeAIModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="aiLoadingOverlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.93);z-index:50;flex-direction:column;align-items:center;justify-content:center;gap:14px;border-radius:8px;">
                <div style="width:44px;height:44px;border:4px solid #d1fae5;border-top-color:#10b981;border-radius:50%;animation:aiSpin .8s linear infinite;"></div>
                <div id="aiLoadingText" style="font-size:.92rem;font-weight:600;color:#065f46;text-align:center;white-space:pre-line;max-width:280px;line-height:1.5;"></div>
                <style>@keyframes aiSpin{to{transform:rotate(360deg);}}</style>
            </div>

            <!-- Single Target Chapter Dropdown -->
            <div style="margin-bottom: 18px;">
                <label style="font-size: .86rem; font-weight: 700; color: #334155; display:block; margin-bottom:6px;">
                    <i class="fas fa-book" style="color:#16a34a;"></i> Target Chapter Pretest:
                </label>
                <select id="aiTargetChapter" class="modal-lesson-filter">
                    <option value="">-- Select Chapter --</option>
                    <?php foreach ($chapters as $ch): 
                        $pt = $pretestMap[$ch['chapter_id']] ?? null;
                    ?>
                    <option value="<?php echo $ch['chapter_id']; ?>" 
                            data-ptid="<?php echo $pt ? $pt['pretest_id'] : ''; ?>"
                            data-content="<?php echo htmlspecialchars($ch['lesson_content'] ?? ''); ?>"
                            data-title="<?php echo htmlspecialchars($ch['chapter_title']); ?>">
                        <?php echo htmlspecialchars($ch['chapter_title']) . ($pt ? '' : ' (Will create pretest)'); ?> <?php echo empty($ch['lesson_content']) ? ' (No text stored)' : ' (' . number_format($ch['text_len']) . ' chars)'; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                <div>
                    <label style="font-size: .86rem; font-weight: 700; color: #334155; display:block; margin-bottom:6px;">
                        <i class="fas fa-sliders"></i> Format Mode:
                    </label>
                    <input type="text" class="modal-lesson-filter" value="Multiple Choice (MCQ)" disabled style="background:#f1f5f9; cursor:not-allowed;">
                </div>
                <div>
                    <label style="font-size: .86rem; font-weight: 700; color: #334155; display:block; margin-bottom:6px;">
                        <i class="fas fa-list-ol"></i> Quantity:
                    </label>
                    <select id="aiQuestionCount" class="modal-lesson-filter">
                        <option value="3">3 Questions</option>
                        <option value="5" selected>5 Questions</option>
                        <option value="10">10 Questions</option>
                        <option value="15">15 Questions</option>
                        <option value="20">20 Questions</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                <button type="button" class="btn-import" id="btnGenerateAI" onclick="requestAIGeneration()">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate Shuffled Questions
                </button>
            </div>

            <!-- Preview Area -->
            <div id="aiPreviewContainer" style="display: none; border-top: 1px dashed #cbd5e1; padding-top: 18px;">
                <h4 style="margin: 0 0 16px; font-size: .98rem; color: #0f172a; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-circle-check" style="color:#16a34a;"></i> Shuffled Generated Questions Preview:
                </h4>
                <div id="aiQuestionsList" style="max-height: 320px; overflow-y: auto; padding-right: 4px;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <span class="modal-selected-count" id="aiCountBadge">0 Drafts Ready</span>
            <button class="btn-import" id="btnSaveAIQuestions" onclick="saveAIPretestQuestions()" disabled>
                <i class="fas fa-plus-circle"></i> Save to Pretest
            </button>
        </div>
    </div>
</div>

<script>
// Expose existing MySQL pretest questions map to JS for local duplication checking
const existingPretestQuestionsMap = <?php echo json_encode($questionsMap); ?>;

/* ── Collapse ── */
function toggleChapter(header) {
    header.classList.toggle('closed');
    header.nextElementSibling.classList.toggle('hidden');
}
function toggleCard(pqid) {
    document.getElementById('pqcard-' + pqid).classList.toggle('open');
}

/* ── Toast ── */
function toast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.querySelector('.toast-message').textContent = msg;
    t.style.background = ok ? '#16a34a' : '#dc2626';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}
function badge(pqid, cls, msg) {
    const b = document.getElementById('badge-' + pqid);
    b.className = 'saved-badge ' + cls;
    b.textContent = msg;
    setTimeout(() => { b.className = 'saved-badge'; }, 3000);
}

/* ── Setup form helpers ── */
function showSetupForm(cid) {
    document.getElementById('setup-' + cid).style.display = 'none';
    document.getElementById('configform-' + cid).style.display = 'block';
    document.getElementById('cfg-title-' + cid).focus();
}
function hideSetupForm(cid) {
    document.getElementById('setup-' + cid).style.display = '';
    document.getElementById('configform-' + cid).style.display = 'none';
}

/* ── Save config (create / update pretest) ── */
function saveConfig(cid) {
    const btn   = document.getElementById('cfgbtn-' + cid);
    const title = document.getElementById('cfg-title-' + cid).value.trim() || 'Chapter Pretest';
    const pct   = 70; // Default passing score

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    const fd = new FormData();
    fd.append('action', 'save_pretest');
    fd.append('chapter_id', cid);
    fd.append('title', title);
    fd.append('passing_score', pct);

    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            if (data.success) {
                toast('Pretest saved!');
                // Store ptId on add button for new pretests
                const addBtn = document.getElementById('addbtn-' + cid);
                if (addBtn) addBtn.dataset.ptid = data.pretest_id;
                // Reload to show full UI
                setTimeout(() => location.reload(), 800);
            } else {
                toast(data.message || 'Save failed.', false);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            toast('Network error.', false);
        });
}

/* ── Toggle active ── */
function toggleActive(cid, ptId) {
    const fd = new FormData();
    fd.append('action', 'toggle_active');
    fd.append('pretest_id', ptId);

    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const isOn  = data.is_active == 1;
                const btn   = document.getElementById('togglebtn-' + cid);
                const abadge = document.getElementById('activebadge-' + cid);
                const icon  = document.getElementById('chicon-' + cid);
                btn.className  = 'btn-toggle ' + (isOn ? 'btn-toggle--on' : 'btn-toggle--off');
                btn.innerHTML  = `<i class="fas fa-toggle-${isOn ? 'on' : 'off'}"></i> ${isOn ? 'Active' : 'Inactive'}`;
                if (abadge) { abadge.className = 'badge ' + (isOn ? 'badge--active' : 'badge--inactive'); abadge.textContent = isOn ? 'Active' : 'Inactive'; }
                if (icon)   { icon.className = 'ch-icon ' + (isOn ? 'ch-icon--active' : 'ch-icon--inactive'); }
                toast(isOn ? 'Pretest activated.' : 'Pretest deactivated.');
            } else {
                toast(data.message || 'Failed.', false);
            }
        })
        .catch(() => toast('Network error.', false));
}

/* ── Delete pretest ── */
function deletePretest(cid, ptId) {
    if (!confirm('Delete this entire pretest and all its questions? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_pretest');
    fd.append('pretest_id', ptId);
    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { toast('Pretest deleted.'); setTimeout(() => location.reload(), 800); }
            else toast(data.message || 'Delete failed.', false);
        })
        .catch(() => toast('Network error.', false));
}

/* ── Save question ── */
function savePQ(pqid) {
    const btn  = document.getElementById('savepqbtn-' + pqid);
    const qTxt = document.getElementById('pqt-' + pqid).value.trim();
    if (!qTxt) { toast('Question text required.', false); return; }
    const ans = [];
    for (let i = 0; i < 4; i++) ans.push(document.getElementById('pa' + i + '-' + pqid).value.trim());
    if (ans.some(a => !a)) { toast('All 4 answers required.', false); return; }
    if (hasDuplicateAnswers(ans)) { toast('Answers must be unique (no repeated choices).', false); return; }
    const radio = document.querySelector(`input[name="pcidx-${pqid}"]:checked`);
    const cidx  = radio ? parseInt(radio.value) : 0;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    const fd = new FormData();
    fd.append('action', 'update_question');
    fd.append('pq_id',  pqid);
    fd.append('question_text',        qTxt);
    fd.append('answer_0',             ans[0]);
    fd.append('answer_1',             ans[1]);
    fd.append('answer_2',             ans[2]);
    fd.append('answer_3',             ans[3]);
    fd.append('correct_answer_index', cidx);

    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            if (data.success) {
                badge(pqid, 'ok', '✓ Saved');
                toast('Question saved!');
                document.querySelector('#pqcard-' + pqid + ' .q-text-preview').textContent = qTxt;
            } else {
                badge(pqid, 'err', '✗ Error');
                toast(data.message || 'Save failed.', false);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            badge(pqid, 'err', '✗ Error');
            toast('Network error.', false);
        });
}

/* ── Delete question ── */
function deletePQ(pqid, cid) {
    if (!confirm('Delete this question?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_question');
    fd.append('pq_id',  pqid);
    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('pqcard-' + pqid);
                row.style.opacity = '0';
                setTimeout(() => { row.remove(); updateQCount(cid, -1); }, 300);
                toast('Question deleted.');
            } else {
                toast(data.message || 'Delete failed.', false);
            }
        })
        .catch(() => toast('Network error.', false));
}

/* ── Add question form ── */
function toggleAddForm(cid, ptId) {
    const form = document.getElementById('addform-' + cid);
    form.classList.toggle('open');
    const btn = document.getElementById('addbtn-' + cid);
    if (btn) btn.dataset.ptid = ptId;
    if (form.classList.contains('open')) document.getElementById('new-pqt-' + cid).focus();
}
function closeAddForm(cid) {
    document.getElementById('addform-' + cid).classList.remove('open');
}
function submitAddPQ(cid) {
    const btn   = document.getElementById('addbtn-' + cid);
    const ptId  = btn.dataset.ptid;
    const qTxt  = document.getElementById('new-pqt-' + cid).value.trim();
    if (!qTxt)  { toast('Question text required.', false); return; }
    const ans = [];
    for (let i = 0; i < 4; i++) ans.push(document.getElementById('new-pa' + i + '-' + cid).value.trim());
    if (ans.some(a => !a)) { toast('All 4 answers required.', false); return; }
    if (hasDuplicateAnswers(ans)) { toast('Answers must be unique (no repeated choices).', false); return; }
    const radio = document.querySelector(`input[name="new-pcidx-${cid}"]:checked`);
    const cidx  = radio ? parseInt(radio.value) : 0;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding…';

    const fd = new FormData();
    fd.append('action',               'add_question');
    fd.append('pretest_id',           ptId);
    fd.append('question_text',        qTxt);
    fd.append('answer_0',             ans[0]);
    fd.append('answer_1',             ans[1]);
    fd.append('answer_2',             ans[2]);
    fd.append('answer_3',             ans[3]);
    fd.append('correct_answer_index', cidx);

    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Question';
            if (data.success) {
                toast('Question added!');
                appendNewCard(cid, data.pq_id, qTxt, ans, cidx);
                resetAddForm(cid);
                closeAddForm(cid);
                updateQCount(cid, 1);
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

function resetAddForm(cid) {
    document.getElementById('new-pqt-' + cid).value = '';
    for (let i = 0; i < 4; i++) document.getElementById('new-pa' + i + '-' + cid).value = '';
    const first = document.querySelector(`input[name="new-pcidx-${cid}"][value="0"]`);
    if (first) first.checked = true;
}

function triggerImport(cid, ptId) {
    const input = document.getElementById('importfile-' + cid);
    if (!input) return;
    input.value = '';
    input.click();
}

function uploadPQFile(cid, ptId, input) {
    if (!input || !input.files || !input.files.length) return;
    const file = input.files[0];
    if (!/\.(xlsx|xls|csv)$/i.test(file.name)) {
        toast('Please upload .xlsx, .xls, or .csv file.', false);
        input.value = '';
        return;
    }

    const btn = document.getElementById('importbtn-' + cid);
    const old = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing…';
    }

    const fd = new FormData();
    fd.append('action', 'import_questions_excel');
    fd.append('pretest_id', ptId);
    fd.append('question_file', file);

    fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = old;
            }
            input.value = '';

            if (data.success) {
                let msg = data.message || 'Questions imported.';
                if (data.skipped_count > 0) {
                    msg += ` Skipped: ${data.skipped_count}.`;
                }
                toast(msg);
                setTimeout(() => location.reload(), 900);
            } else {
                let msg = data.message || 'Import failed.';
                if (Array.isArray(data.errors) && data.errors.length) {
                    msg += ' ' + data.errors.slice(0, 2).join(' ');
                }
                toast(msg, false);
            }
        })
        .catch(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = old;
            }
            input.value = '';
            toast('Network error.', false);
        });
}

/* ── Inject new card ── */
function appendNewCard(cid, pqid, qTxt, ans, cidx) {
    const list   = document.getElementById('qlist-' + cid);
    const empty  = document.getElementById('qempty-' + cid);
    if (empty) empty.remove();
    const count  = list.querySelectorAll('.q-row').length + 1;
    const labels = ['A','B','C','D'];
    const aRows  = ans.map((a, i) =>
        `<div class="answer-row">
            <span class="answer-letter">${labels[i]}</span>
            <input type="text" class="answer-input" id="pa${i}-${pqid}" value="${esc(a)}">
            <input type="radio" class="correct-radio" name="pcidx-${pqid}" value="${i}" ${i===cidx?'checked':''}>
         </div>`
    ).join('');
    const html = `
    <div class="q-row" id="pqcard-${pqid}">
        <div class="q-row-head" onclick="toggleCard('${pqid}')">
            <span class="q-num">${count}</span>
            <span class="q-text-preview">${esc(qTxt)}</span>
            <span class="saved-badge" id="badge-${pqid}"></span>
            <i class="fas fa-chevron-right q-row-caret"></i>
        </div>
        <div class="q-inspector">
            <div class="insp-label">Question</div>
            <textarea class="insp-textarea" id="pqt-${pqid}" rows="2">${esc(qTxt)}</textarea>
            <div class="insp-label" style="margin-top:14px;">Answers — click ○ to mark correct</div>
            <div class="answer-grid">${aRows}</div>
            <div class="insp-actions">
                <button class="btn-del" onclick="deletePQ(${pqid},${cid})"><i class="fas fa-trash"></i> Delete</button>
                <button class="btn-save" id="savepqbtn-${pqid}" onclick="savePQ(${pqid})"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>`;
    list.insertAdjacentHTML('beforeend', html);
    setTimeout(() => document.getElementById('pqcard-' + pqid)?.scrollIntoView({ behavior:'smooth', block:'nearest' }), 50);
}

function updateQCount(cid, delta) {
    const badge = document.getElementById('qcount-' + cid);
    if (badge) {
        const n = Math.max(0, (parseInt(badge.textContent) || 0) + delta);
        badge.textContent = n + ' Q';
    }
}

function hasDuplicateAnswers(answers) {
    const normalized = answers.map(a => String(a).trim().toLowerCase());
    return new Set(normalized).size !== normalized.length;
}

function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── AI Assistant Logic ── */
let generatedAIDrafts = [];
let sessionGeneratedQuestionsMap = {}; // Tracks questions generated per chapter during current session

function openAIModal() {
    document.getElementById('aiModal').classList.add('open');
}
function closeAIModal() {
    document.getElementById('aiModal').classList.remove('open');
}
function closeAIOnOverlay(e) {
    if (e.target === document.getElementById('aiModal')) closeAIModal();
}

function showAILoading(show, msg) {
    const overlay = document.getElementById('aiLoadingOverlay');
    overlay.style.display = show ? 'flex' : 'none';
    if (msg) document.getElementById('aiLoadingText').textContent = msg;
}

// Helper Utility to Shuffle (Rumble) Arrays
function shuffleArray(array) {
    let currentIndex = array.length, randomIndex;
    while (currentIndex !== 0) {
        randomIndex = Math.floor(Math.random() * currentIndex);
        currentIndex--;
        [array[currentIndex], array[randomIndex]] = [array[randomIndex], array[currentIndex]];
    }
    return array;
}

// Normalize strings for accurate duplicate comparisons
function normalizeQuestionText(txt) {
    return String(txt || '').toLowerCase().trim().replace(/[^\w\s]/gi, '');
}

// Shuffle questions and rumble choice options internally
function rumbleAndProcessQuestions(questionsList) {
    const processed = questionsList.map(q => {
        let choices = [
            q.answer_0 || q.choices?.[0] || '',
            q.answer_1 || q.choices?.[1] || '',
            q.answer_2 || q.choices?.[2] || '',
            q.answer_3 || q.choices?.[3] || ''
        ];
        let correctIndex = q.correct_answer_index ?? 0;

        // Shuffle Choices
        let choicesObjects = choices.map((c, i) => ({ text: c, isCorrect: i === correctIndex }));
        choicesObjects = shuffleArray(choicesObjects);

        let newCorrectIndex = choicesObjects.findIndex(c => c.isCorrect);

        return {
            question_text: q.question_text,
            answer_0: choicesObjects[0].text,
            answer_1: choicesObjects[1].text,
            answer_2: choicesObjects[2].text,
            answer_3: choicesObjects[3].text,
            correct_answer_index: newCorrectIndex,
            source_quote: q.source_quote || ''
        };
    });

    // Rumble Question Order
    return shuffleArray(processed);
}

async function requestAIGeneration() {
    const chapterSelect = document.getElementById('aiTargetChapter');
    const selectedOpt   = chapterSelect.options[chapterSelect.selectedIndex];
    const totalCount    = parseInt(document.getElementById('aiQuestionCount').value);
    const btn           = document.getElementById('btnGenerateAI');

    if (!chapterSelect.value) {
        Swal.fire({ icon: 'warning', title: 'No Chapter Selected', text: 'Please select a target chapter first.', confirmColor: '#10b981' });
        return;
    }

    const chapterId = chapterSelect.value;
    const pretestId = selectedOpt.dataset.ptid;
    const contextText = selectedOpt.dataset.content || '';
    const chapterTitle = selectedOpt.dataset.title || '';

    if (!contextText.trim()) {
        Swal.fire({ icon: 'warning', title: 'Empty Content', text: 'The selected chapter does not have any stored lesson text/module content to generate questions from.', confirmColor: '#10b981' });
        return;
    }

    // Collect existing question texts to enforce deduplication
    const existingTexts = new Set();
    
    // 1. Load questions already in the database for this chapter pretest
    if (pretestId && existingPretestQuestionsMap[pretestId]) {
        existingPretestQuestionsMap[pretestId].forEach(q => {
            existingTexts.add(normalizeQuestionText(q.question_text));
        });
    }

    // 2. Load questions generated in this active session
    if (sessionGeneratedQuestionsMap[chapterId]) {
        sessionGeneratedQuestionsMap[chapterId].forEach(txt => {
            existingTexts.add(normalizeQuestionText(txt));
        });
    }

    showAILoading(true, `Generating Pretest Questions...\n${chapterTitle}`);
    btn.disabled = true;

    try {
        const res = await fetch('generate_ai_questions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                contextText: contextText.trim().substring(0, 4000), 
                count: totalCount, 
                formatType: 'mcq' 
            })
        });
        const data = await res.json();

        if (!data.success) {
            const errLower = (data.error || '').toLowerCase();
            if (errLower.includes('quota') || errLower.includes('limit') || errLower.includes('429'))
                throw new Error('Gemini AI daily limit reached. Please try again later.');
            throw new Error(data.error || 'Generation failed.');
        }

        // Filter out duplicate questions
        let rawQuestions = data.questions || [];
        let uniqueQuestions = [];
        let skippedDuplicatesCount = 0;

        rawQuestions.forEach(q => {
            const normalized = normalizeQuestionText(q.question_text);
            if (existingTexts.has(normalized)) {
                skippedDuplicatesCount++;
            } else {
                existingTexts.add(normalized);
                uniqueQuestions.push(q);
            }
        });

        if (uniqueQuestions.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No New Questions',
                text: 'All generated questions were duplicates of existing questions. Try generating again or increasing the quantity.',
                confirmColor: '#10b981'
            });
            return;
        }

        // Apply rumbling/shuffling to choices & question order
        generatedAIDrafts = rumbleAndProcessQuestions(uniqueQuestions);

        // Store new question texts in current session tracking memory
        if (!sessionGeneratedQuestionsMap[chapterId]) {
            sessionGeneratedQuestionsMap[chapterId] = [];
        }
        generatedAIDrafts.forEach(q => {
            sessionGeneratedQuestionsMap[chapterId].push(q.question_text);
        });

        renderAIPreview();

        let alertMsg = `Successfully generated ${generatedAIDrafts.length} unique pretest questions.`;
        if (skippedDuplicatesCount > 0) {
            alertMsg += ` (${skippedDuplicatesCount} duplicate question(s) were automatically filtered out).`;
        }

        Swal.fire({ icon: 'success', title: 'Questions Generated!', text: alertMsg, confirmColor: '#10b981' });

    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Generation Failed', text: err.message, confirmColor: '#ef4444' });
    } finally {
        showAILoading(false);
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate Shuffled Questions';
    }
}

function toggleAccuracyProof(idx) {
    const el = document.getElementById('proof-' + idx);
    if (el) el.classList.toggle('open');
}

function renderAIPreview() {
    const container = document.getElementById('aiPreviewContainer');
    const list      = document.getElementById('aiQuestionsList');
    const badge     = document.getElementById('aiCountBadge');
    const saveBtn   = document.getElementById('btnSaveAIQuestions');

    container.style.display = 'block';
    list.innerHTML = '';

    generatedAIDrafts.forEach((q, idx) => {
        const item = document.createElement('div');
        item.className = 'ai-draft-card';

        const sourceQuote = q.source_quote || 'Source verified from provided text.';
        const choicesList = [
            { text: q.answer_0 || '', is_correct: q.correct_answer_index == 0 },
            { text: q.answer_1 || '', is_correct: q.correct_answer_index == 1 },
            { text: q.answer_2 || '', is_correct: q.correct_answer_index == 2 },
            { text: q.answer_3 || '', is_correct: q.correct_answer_index == 3 }
        ];
        const labels = ['A','B','C','D'];
        const answerHTML = `<div class="ai-choice-grid">${choicesList.map((ch, ci) =>
            `<div class="ai-choice-item ${ch.is_correct ? 'correct' : ''}"><strong>${labels[ci]}:</strong> ${esc(ch.text)} ${ch.is_correct ? '<i class="fas fa-check-circle"></i>' : ''}</div>`
        ).join('')}</div>`;

        item.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div style="flex:1;">
                    <div style="font-size:.92rem;font-weight:700;color:#0f172a;line-height:1.4;">Q${idx + 1}: ${esc(q.question_text)}</div>
                </div>
                <button type="button" class="btn-accuracy-proof" onclick="toggleAccuracyProof(${idx})">🔍 Source</button>
            </div>
            <div class="accuracy-proof-box" id="proof-${idx}">
                <strong>🎯 Grounded Text Snippet:</strong><br><em>"${esc(sourceQuote)}"</em>
            </div>
            ${answerHTML}`;
        list.appendChild(item);
    });

    badge.innerText  = `${generatedAIDrafts.length} Drafts Ready`;
    saveBtn.disabled = generatedAIDrafts.length === 0;
}

async function saveAIPretestQuestions() {
    const chapterSelect = document.getElementById('aiTargetChapter');
    const selectedOption = chapterSelect.options[chapterSelect.selectedIndex];
    const chapterId = chapterSelect.value;

    if (!chapterId) {
        Swal.fire({ icon: 'warning', title: 'Missing Chapter', text: 'Please select a target chapter first.', confirmColor: '#10b981' });
        return;
    }

    let pretestId = selectedOption.dataset.ptid;
    const btn = document.getElementById('btnSaveAIQuestions');

    if (!generatedAIDrafts.length) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        // Automatically create pretest if missing
        if (!pretestId) {
            const fdCfg = new FormData();
            fdCfg.append('action', 'save_pretest');
            fdCfg.append('chapter_id', chapterId);
            fdCfg.append('title', 'Chapter Pretest');
            fdCfg.append('passing_score', 70);

            const cfgRes = await fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fdCfg });
            const cfgData = await cfgRes.json();

            if (!cfgData.success) {
                Swal.fire({ icon: 'error', title: 'Pretest Creation Failed', text: cfgData.message || 'Failed to create initial pretest for chapter.', confirmColor: '#ef4444' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus-circle"></i> Save to Pretest';
                return;
            }
            pretestId = cfgData.pretest_id;
        }

        // Batch insert questions with duplicate validation handling
        let successCount = 0;
        let skippedDuplicateCount = 0;

        for (const q of generatedAIDrafts) {
            const fd = new FormData();
            fd.append('action', 'add_question');
            fd.append('pretest_id', pretestId);
            fd.append('question_text', q.question_text);
            fd.append('answer_0', q.answer_0);
            fd.append('answer_1', q.answer_1);
            fd.append('answer_2', q.answer_2);
            fd.append('answer_3', q.answer_3);
            fd.append('correct_answer_index', q.correct_answer_index);

            const res = await fetch('../api/chapter_pretest_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                successCount++;
            } else if (data.message && data.message.toLowerCase().includes('duplicate')) {
                skippedDuplicateCount++;
            }
        }

        let resultMsg = `${successCount} question(s) saved to Pretest.`;
        if (skippedDuplicateCount > 0) {
            resultMsg += ` (${skippedDuplicateCount} duplicate question(s) were skipped).`;
        }

        Swal.fire({ icon: 'success', title: 'Save Complete!', text: resultMsg, confirmColor: '#10b981' })
            .then(() => location.reload());
            
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Network error occurred during batch save.', confirmColor: '#ef4444' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus-circle"></i> Save to Pretest';
    }
}
</script>
</body>
</html>