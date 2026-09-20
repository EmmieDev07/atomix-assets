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
// Fetch this teacher's classes for the class filter.
$classesStmt = $db->prepare(
    "SELECT c.class_id, c.class_name, sy.label AS school_year
     FROM classes c
     LEFT JOIN school_year sy ON c.sy_id = sy.sy_id
     WHERE c.teacher_id = ?
     ORDER BY sy.is_active DESC, c.class_name"
);
$classesStmt->execute([$teacher_id]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
$classIds = array_map('intval', array_column($classes, 'class_id'));
$selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
if ($selected_class_id !== 0 && !in_array($selected_class_id, $classIds, true)) {
    $selected_class_id = 0;
}

// Step 1: fetch all chapters
$chaptersStmt = $db->query(
    "SELECT chapter_id, chapter_title, chapter_order
     FROM chapters
     ORDER BY chapter_order"
);
$chapters = $chaptersStmt->fetchAll(PDO::FETCH_ASSOC);

// Step 2: selected chapter — default to first
$selected_chapter_id = isset($_GET['chapter_id']) ? (int) $_GET['chapter_id'] : (int) ($chapters[0]['chapter_id'] ?? 0);
$selected_chapter = null;
foreach ($chapters as $ch) {
    if ((int)$ch['chapter_id'] === $selected_chapter_id) { $selected_chapter = $ch; break; }
}

// Step 3: fetch stages for the selected chapter
$stages = [];
$progress_stage_ids = [];
if ($selected_chapter_id > 0) {
    $stagesStmt = $db->prepare(
        "SELECT stage_id, stage_name, science_concept, stage_order
         FROM stages
         WHERE chapter_id = ?
         ORDER BY stage_order"
    );
    $stagesStmt->execute([$selected_chapter_id]);
    $stages = $stagesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Detect which stages in this chapter actually have progress under current class filter.
    $progressStageStmt = $db->prepare(
        "SELECT DISTINCT gp.stage_id
         FROM game_progress gp
         JOIN class_students cs ON gp.student_id = cs.student_id
         JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ?
         JOIN stages s ON s.stage_id = gp.stage_id
         WHERE s.chapter_id = ?
           AND (? = 0 OR c.class_id = ?)"
    );
    $progressStageStmt->execute([$teacher_id, $selected_chapter_id, $selected_class_id, $selected_class_id]);
    $progress_stage_ids = array_map('intval', array_column($progressStageStmt->fetchAll(PDO::FETCH_ASSOC), 'stage_id'));
}

// Step 4: selected stage — 0 means all stages in selected chapter.
$selected_stage_id = isset($_GET['stage_id']) ? (int) $_GET['stage_id'] : 0;
$stage_ids = array_map('intval', array_column($stages, 'stage_id'));
if ($selected_stage_id !== 0 && !in_array($selected_stage_id, $stage_ids, true)) {
    $selected_stage_id = 0;
}

// Get student progress for the selected stage
$students = [];
$stage_info = null;

if ($selected_chapter_id > 0) {
    // Get stage information
    if ($selected_stage_id > 0) {
        $stageStmt = $db->prepare(
            "SELECT s.*, g.game_title
             FROM stages s
             JOIN games g ON s.game_id = g.game_id
             WHERE s.stage_id = ?"
        );
        $stageStmt->execute([$selected_stage_id]);
        $stage_info = $stageStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get student progress (specific stage or all stages in chapter)
        $progressStmt = $db->prepare(
            "SELECT gp.progress_id, gp.status, gp.last_updated,
                    st.student_id, st.first_name, st.last_name,
                    c.class_name, sy.label as school_year,
                    stage_lookup.stage_name AS progress_stage_name,
                    spr.score AS pretest_score, spr.total_questions AS pretest_total_questions,
                    spr.attempted_at AS pretest_attempted_at,
                    ar.score AS assessment_score, ar.total_questions AS assessment_total_questions,
                    ar.attempted_at AS assessment_attempted_at
             FROM class_students cs
             JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ?
             JOIN students st ON cs.student_id = st.student_id
             JOIN users u ON st.user_id = u.user_id
             LEFT JOIN school_year sy ON c.sy_id = sy.sy_id
             LEFT JOIN game_progress gp ON gp.student_id = st.student_id
                 AND (? = 0 OR gp.stage_id = ?)
             LEFT JOIN stages stage_lookup ON stage_lookup.stage_id = gp.stage_id
             LEFT JOIN student_pretest_results spr ON spr.student_id = st.student_id
                 AND spr.chapter_id = stage_lookup.chapter_id
                 AND spr.attempted_at = (
                     SELECT MAX(spr2.attempted_at)
                     FROM student_pretest_results spr2
                     WHERE spr2.student_id = st.student_id
                       AND spr2.chapter_id = stage_lookup.chapter_id
                 )
             LEFT JOIN student_assessment_results ar ON ar.student_id = st.student_id
                 AND ar.chapter_id = stage_lookup.chapter_id
                 AND ar.stage_id = gp.stage_id
                 AND ar.assessment_type = 'stage'
                 AND ar.attempted_at = (
                     SELECT MAX(ar2.attempted_at)
                     FROM student_assessment_results ar2
                     WHERE ar2.student_id = st.student_id
                       AND ar2.chapter_id = stage_lookup.chapter_id
                       AND ar2.stage_id = gp.stage_id
                       AND ar2.assessment_type = 'stage'
                 )
             WHERE (? = 0 OR c.class_id = ?)
               AND (? = 0 OR stage_lookup.chapter_id = ?)
               AND gp.progress_id IS NOT NULL
             ORDER BY gp.last_updated DESC, st.last_name, st.first_name"
        );
        $progressStmt->execute([
            $teacher_id,
            $selected_stage_id,
            $selected_stage_id,
            $selected_class_id,
            $selected_class_id,
            $selected_chapter_id,
            $selected_chapter_id
        ]);
        $students = $progressStmt->fetchAll(PDO::FETCH_ASSOC);
}
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('game_progress.php DB error: ' . $e->getMessage());
    $classes = []; $chapters = []; $stages = []; $students = [];
    $selected_class_id = 0; $selected_chapter_id = 0; $selected_stage_id = 0;
    $selected_chapter = null; $stage_info = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Progress - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
         <aside class="sidebar">
            <?php include 'sidebar.php'; ?>

            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>Game Progress</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Game Progress</span>
            </nav>
            <?php if (!empty($dbError)): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>

            <div class="content-area">
                <div class="section-header">
                    <h2>Student Game Progress by Stage</h2>
                </div>

                <form method="get" class="filter-bar" style="margin-bottom: 20px;">
                    <div class="filter-group">
                        <label for="classSelect">Class:</label>
                        <select id="classSelect" name="class_id" onchange="this.form.submit()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo (int) $class['class_id']; ?>" <?php echo $selected_class_id === (int) $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?><?php echo !empty($class['school_year']) ? ' (' . htmlspecialchars($class['school_year']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="chapterSelect">Chapter:</label>
                        <select id="chapterSelect" name="chapter_id" onchange="this.form.submit()">
                            <?php foreach ($chapters as $ch): ?>
                                <option value="<?php echo (int) $ch['chapter_id']; ?>" <?php echo $selected_chapter_id === (int) $ch['chapter_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ch['chapter_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($stages)): ?>
                    <div class="filter-group">
                        <label for="stageSelect">Stage:</label>
                        <select id="stageSelect" name="stage_id" onchange="this.form.submit()">
                            <option value="0" <?php echo $selected_stage_id === 0 ? 'selected' : ''; ?>>All Stages</option>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo (int) $stage['stage_id']; ?>" <?php echo $selected_stage_id === (int) $stage['stage_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($stage['stage_name']); ?>
                                    <?php if (!empty($stage['science_concept'])): ?>
                                        - <?php echo htmlspecialchars($stage['science_concept']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </form>

                <?php if ($selected_chapter): ?>
                <div class="stage-info" style="margin-bottom: 20px; padding: 15px; background: var(--gray-100); border-radius: var(--radius);">
                    <?php if ($stage_info): ?>
                        <h3><?php echo htmlspecialchars($stage_info['game_title']); ?> &rsaquo; <?php echo htmlspecialchars($stage_info['stage_name']); ?> &rsaquo; <?php echo htmlspecialchars($selected_chapter['chapter_title']); ?></h3>
                    <?php else: ?>
                        <h3><?php echo htmlspecialchars($selected_chapter['chapter_title']); ?> &rsaquo; All Stages</h3>
                    <?php endif; ?>
                    <?php if (!empty($stage_info['science_concept'])): ?>
                        <p><strong>Science Concept:</strong> <?php echo htmlspecialchars($stage_info['science_concept']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="margin-bottom: 10px; color: #4b5563; font-size: 0.95rem;">
                    Showing <?php echo count($students); ?> progress row<?php echo count($students) === 1 ? '' : 's'; ?>.
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Stage</th>
                                <th>Pretest Score</th>
                                <th>Pretest Attempt</th>
                                <th>Assessment Score</th>
                                <th>Assessment Attempt</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        No students have started this selection yet.
                                        <?php if ($selected_stage_id !== 0 && !empty($progress_stage_ids)): ?>
                                            Try another stage in this chapter.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name'] ?? 'No Class'); ?></td>
                                        <td><?php echo htmlspecialchars($student['progress_stage_name'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($student['pretest_score'] === null || (int)$student['pretest_total_questions'] === 0): ?>
                                                -
                                            <?php else: ?>
                                                <?php echo (int) $student['pretest_score']; ?>/<?php echo (int) $student['pretest_total_questions']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($student['pretest_attempted_at'])): ?>
                                                -
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($student['pretest_attempted_at']))); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['assessment_score'] === null || (int)$student['assessment_total_questions'] === 0): ?>
                                                -
                                            <?php else: ?>
                                                <?php echo (int) $student['assessment_score']; ?>/<?php echo (int) $student['assessment_total_questions']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($student['assessment_attempted_at'])): ?>
                                                -
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($student['assessment_attempted_at']))); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $student['status']; ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $student['status']))); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($student['last_updated']))); ?></td>
                                        <td>
                                            <button class="btn-reset-progress" title="Reset chapter progress & pretest"
                                                onclick="openResetModal(<?php echo (int)$student['student_id']; ?>, <?php echo $selected_chapter_id; ?>, '<?php echo htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')"
                                            ><i class="fas fa-undo"></i> Reset</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
<!-- Reset Progress Modal -->
<div id="resetProgressModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 10px;"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i> Reset Progress</h3>
        <p id="resetModalMsg" style="margin:0 0 20px;color:#374151;"></p>
        <p style="font-size:.85rem;color:#6b7280;margin:0 0 24px;">This will delete all game progress, stage assessments, and the pretest for the selected chapter. The student will need to retake the pretest before accessing Stage 1 again.</p>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button onclick="closeResetModal()" style="padding:8px 20px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;">Cancel</button>
            <button id="confirmResetBtn" onclick="confirmReset()" style="padding:8px 20px;border:none;border-radius:8px;background:#ef4444;color:#fff;cursor:pointer;font-weight:600;">Yes, Reset</button>
        </div>
    </div>
</div>

<style>
.btn-reset-progress{padding:5px 12px;border:none;border-radius:6px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;cursor:pointer;font-size:.83rem;font-weight:600;transition:background .15s;}
.btn-reset-progress:hover{background:#fee2e2;}
</style>

<script>
let _resetStudentId = 0, _resetChapterId = 0;

function openResetModal(studentId, chapterId, studentName) {
    _resetStudentId = studentId;
    _resetChapterId = chapterId;
    document.getElementById('resetModalMsg').textContent = 'Reset all progress for ' + studentName + ' in this chapter?';
    const modal = document.getElementById('resetProgressModal');
    modal.style.display = 'flex';
}

function closeResetModal() {
    document.getElementById('resetProgressModal').style.display = 'none';
}

function confirmReset() {
    const btn = document.getElementById('confirmResetBtn');
    btn.disabled = true;
    btn.textContent = 'Resetting...';

    const fd = new FormData();
    fd.append('student_id', _resetStudentId);
    fd.append('chapter_id', _resetChapterId);

    fetch('../api/reset_student_progress.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeResetModal();
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Reset failed'));
                btn.disabled = false;
                btn.textContent = 'Yes, Reset';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Yes, Reset';
        });
}
</script>
</body>
</html>
