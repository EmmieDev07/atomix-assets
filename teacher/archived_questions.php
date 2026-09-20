<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_questions');

$teacher_id = getTeacherId();
$questions = [];
$allChoices = [];
$dbError = null;

try {
    $stmt = $db->prepare("SELECT q.*, l.lesson_title, c.chapter_title, t.first_name, t.last_name
        FROM questions_master q
        JOIN lessons l ON q.lesson_id = l.lesson_id
        JOIN chapters c ON l.chapter_id = c.chapter_id
        LEFT JOIN teachers t ON q.created_by_teacher_id = t.teacher_id
        WHERE q.created_by_teacher_id = ? AND q.is_archived = 1
        ORDER BY q.question_id DESC");
    $stmt->execute([$teacher_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($questions) {
        $ids = array_column($questions, 'question_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $choiceStmt = $db->prepare("SELECT * FROM quiz_choices WHERE question_id IN ($placeholders) ORDER BY choice_id");
        $choiceStmt->execute($ids);
        while ($choice = $choiceStmt->fetch(PDO::FETCH_ASSOC)) {
            $allChoices[$choice['question_id']][] = $choice;
        }
    }
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('archived_questions.php DB error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Questions - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .question-card { background:#fff; border:1.5px solid #e2e8f0; border-radius:12px; padding:18px 24px; margin-bottom:16px; box-shadow:0 2px 4px rgba(0,0,0,.02); }
        .card-header-flex { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:12px; }
        .tag-badge-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; row-gap:8px; flex:1; }
        .badge-type { background:#fef3c7; color:#b45309; padding:5px 12px; border-radius:14px; font-weight:700; text-transform:capitalize; font-size:.78rem; }
        .badge-vis { background:#e2e8f0; color:#475569; padding:5px 12px; border-radius:14px; font-weight:600; font-size:.78rem; }
        .badge-archived { background:#fee2e2; color:#b91c1c; padding:5px 12px; border-radius:14px; font-weight:700; font-size:.78rem; }
        .meta-tag { color:#64748b; font-size:.82rem; display:inline-flex; align-items:center; gap:5px; background:#f8fafc; padding:4px 10px; border-radius:8px; border:1px solid #f1f5f9; }
        .choices-preview-grid { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
        .choice-pill { border-radius:20px; padding:6px 14px; font-size:.85rem; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .choice-pill.correct { background:#d1fae5; color:#059669; border:1px solid #a7f3d0; }
        .choice-pill.incorrect { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
        @media (max-width:600px) { .question-card { padding:16px; } .card-header-flex { flex-direction:column; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar"><?php include 'sidebar.php'; ?></aside>
    <main class="main-content">
        <header class="top-header">
            <h1>Archived Questions</h1>
            <a href="profile.php" class="user-info" title="My Profile"><span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span><i class="fas fa-user-circle"></i></a>
        </header>
        <nav class="breadcrumb" aria-label="breadcrumb"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span><span class="breadcrumb-current">Archived Questions</span></nav>
        <?php if ($dbError): ?><div class="alert alert-danger"><?php echo htmlspecialchars($dbError); ?></div><?php endif; ?>
        <div class="content-area">
            <div class="section-header"><div><h2><i class="fas fa-box-archive"></i> Archived Questions</h2><p>Restore archived questions whenever you need them again.</p></div><a href="questions.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Questions</a></div>
            <div class="questions-container">
                <?php if (!$questions): ?>
                    <div class="empty-state"><i class="fas fa-box-open"></i><h3>No archived questions</h3><p>Questions you archive will appear here.</p></div>
                <?php else: foreach ($questions as $q): ?>
                    <div class="question-card">
                        <div class="card-header-flex">
                            <div class="tag-badge-group">
                                <span class="badge-type"><?php echo htmlspecialchars(str_replace('_', ' ', $q['question_type'])); ?></span>
                                <span class="badge-archived"><i class="fas fa-box-archive"></i> Archived</span>
                                <span class="badge-vis"><?php echo ucfirst(htmlspecialchars($q['visibility'])); ?></span>
                                <span class="meta-tag"><i class="fas fa-book"></i> <?php echo htmlspecialchars($q['lesson_title']); ?></span>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" onclick="unarchiveQuestion(<?php echo (int)$q['question_id']; ?>)" title="Unarchive question"><i class="fas fa-box-open"></i> Unarchive</button>
                        </div>
                        <div style="font-weight:600;font-size:1rem;color:#0f172a;line-height:1.4;"><?php echo htmlspecialchars($q['question_text']); ?></div>
                        <?php if (!empty($allChoices[$q['question_id']])): ?><div class="choices-preview-grid"><?php foreach ($allChoices[$q['question_id']] as $choice): ?><div class="choice-pill <?php echo $choice['is_correct'] ? 'correct' : 'incorrect'; ?>"><i class="far fa-circle<?php echo $choice['is_correct'] ? '-check' : ''; ?>"></i><?php echo htmlspecialchars($choice['choice_text']); ?></div><?php endforeach; ?></div><?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </main>
</div>
<script>
async function unarchiveQuestion(id) {
    const result = await Swal.fire({ title:'Unarchive Question?', text:'This question will return to the active question list.', icon:'question', showCancelButton:true, confirmButtonText:'Unarchive', confirmButtonColor:'#10b981' });
    if (!result.isConfirmed) return;
    const data = new FormData();
    data.append('action', 'toggle_archive_question');
    data.append('question_id', id);
    try {
        const response = await fetch('../api/question_api.php', { method:'POST', body:data });
        const json = await response.json();
        if (!json.success) throw new Error(json.message || 'Unarchive failed');
        await Swal.fire({ icon:'success', title:'Unarchived', text:json.message, timer:1400, showConfirmButton:false });
        location.reload();
    } catch (error) {
        Swal.fire({ icon:'error', title:'Unarchive failed', text:error.message });
    }
}
</script>
</body>
</html>
