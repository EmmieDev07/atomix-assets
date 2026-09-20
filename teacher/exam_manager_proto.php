<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkTeacherAuth();

$db = Database::getInstance()->getConnection();
$teacherId = (int)$_SESSION['teacher_id'];

// Fetch classes for dropdowns / checkboxes
$stmt = $db->prepare('SELECT c.class_id, c.class_name FROM classes c JOIN school_year sy ON sy.sy_id = c.sy_id AND sy.is_active = 1 WHERE c.teacher_id = ? ORDER BY c.class_name');
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Manager Prototype & Tester</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box !important; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #0f172a; padding: 20px; }
        .proto-container { max-width: 1100px; margin: 0 auto; }
        .proto-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .proto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn { padding: 8px 14px; font-weight: 700; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .badge { font-size: 10px; padding: 3px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; }
        .badge-live { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .badge-draft { background: #f1f5f9; color: #475569; border: 1px solid #94a3b8; }
        .class-checkboxes { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; background: #f1f5f9; padding: 10px; border-radius: 6px; }
        .class-checkboxes label { background: #fff; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1; font-weight: 600; font-size: 12px; cursor: pointer; }
        .input-field { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 10px; font-size: 13px; }
    </style>
</head>
<body>

<div class="proto-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h2><i class="fas fa-vial" style="color:#3b82f6;"></i> Exam Manager Prototype</h2>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="proto-grid">
        <!-- TEST FORM -->
        <div class="proto-card">
            <h3><i class="fas fa-pen"></i> Create / Test Exam Deployment</h3>
            <form id="testExamForm">
                <input type="hidden" id="testExamId" value="0">
                
                <label style="font-weight:700; font-size:12px;">Exam Title</label>
                <input type="text" id="testTitle" class="input-field" placeholder="e.g., Science Quiz Grade 6 Mars" required>

                <label style="font-weight:700; font-size:12px;">Target Classes</label>
                <div class="class-checkboxes">
                    <?php foreach($classes as $c): ?>
                        <label>
                            <input type="checkbox" name="protoClass" value="<?= (int)$c['class_id'] ?>">
                            <?= htmlspecialchars($c['class_name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin: 15px 0;">
                    <label style="font-weight:700; font-size:12px; display:block; margin-bottom: 4px;">Deployment Status</label>
                    <label style="margin-right: 15px; cursor:pointer;"><input type="radio" name="protoDeployed" value="0" checked> Draft</label>
                    <label style="color:#10b981; font-weight:800; cursor:pointer;"><input type="radio" name="protoDeployed" value="1"> Live & Deployed</label>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-save"></i> Save Exam</button>
            </form>
        </div>

        <!-- EXAM LIST & REAL-TIME MONITORING PROTOTYPE -->
        <div>
            <div class="proto-card">
                <h3><i class="fas fa-list"></i> Saved Exams</h3>
                <div id="protoExamList">Loading exams...</div>
            </div>

            <div class="proto-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3><i class="fas fa-chart-line" style="color:#10b981;"></i> Live Monitoring Feed</h3>
                    <button class="btn btn-secondary" onclick="loadLiveFeed()" style="padding:4px 8px; font-size:11px;"><i class="fas fa-sync"></i> Refresh</button>
                </div>
                <div id="protoLiveFeed" style="margin-top:10px; font-size:12px;">Fetching live progress...</div>
            </div>
        </div>
    </div>
</div>

<script>
const api = '../api/exam_api.php';

async function fetchExams() {
    let res = await fetch(api + '?action=list');
    let data = await res.json();
    let container = document.getElementById('protoExamList');

    if (!data.success || !data.exams.length) {
        container.innerHTML = '<p style="font-size:12px; color:#64748b;">No exams found.</p>';
        return;
    }

    container.innerHTML = data.exams.map(e => `
        <div style="border-bottom:1px solid #cbd5e1; padding: 8px 0; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong>${e.exam_title}</strong><br>
                <small style="color:#64748b;">Classes: ${e.classes || 'None'}</small>
            </div>
            <div>
                ${Number(e.is_deployed) === 1 
                    ? '<span class="badge badge-live">Live</span>' 
                    : '<span class="badge badge-draft">Draft</span>'}
            </div>
        </div>
    `).join('');
}

async function loadLiveFeed() {
    let res = await fetch(api + '?action=live_monitoring');
    let data = await res.json();
    let container = document.getElementById('protoLiveFeed');

    if (!data.success || !data.sessions.length) {
        container.innerHTML = '<p style="color:#64748b;">No student entries in exam_results yet.</p>';
        return;
    }

    container.innerHTML = data.sessions.map(s => `
        <div style="background:#f1f5f9; padding:8px; border-radius:6px; margin-bottom:6px;">
            <strong style="color:#0f172a;">${s.student_name}</strong> (${s.class_name})<br>
            <span style="color:#475569;">Exam: ${s.exam_title}</span> | 
            <span style="font-weight:800; color:#10b981;">Score: ${s.score ?? 0} / ${s.total_questions ?? 0}</span>
        </div>
    `).join('');
}

document.getElementById('testExamForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    let class_ids = [...document.querySelectorAll('input[name="protoClass"]:checked')].map(c => Number(c.value));
    let is_deployed = Number(document.querySelector('input[name="protoDeployed"]:checked').value);
    let title = document.getElementById('testTitle').value;

    if (!class_ids.length) {
        return Swal.fire('Error', 'Please select at least one class!', 'warning');
    }

    // Default mock section with 1 question for schema compliance
    let dummySection = [{
        section_type: 'multiple_choice',
        section_label: 'Section 1',
        instructions: 'Test instructions',
        questions: [{ questionText: 'Sample Test Question', choices: ['A', 'B'], correctAnswer: 'A' }]
    }];

    async function submitSave(forceOverride = false) {
        let payload = {
            action: 'save',
            exam_id: document.getElementById('testExamId').value,
            exam_title: title,
            class_ids: class_ids,
            is_deployed: is_deployed,
            duration_minutes: 60,
            sections: dummySection,
            force_override: forceOverride
        };

        let res = await fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        let data = await res.json();

        if (data.success) {
            Swal.fire('Saved!', 'Exam saved successfully.', 'success');
            fetchExams();
            loadLiveFeed();
        } else if (data.conflict) {
            const confirm = await Swal.fire({
                title: 'Duplicate Live Warning!',
                text: data.error + ' Do you want to un-deploy the existing exam and deploy this one?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Replace Existing Live Exam',
                cancelButtonText: 'Keep as Draft'
            });

            if (confirm.isConfirmed) {
                await submitSave(true);
            }
        } else {
            Swal.fire('Error', data.error || 'Failed to save.', 'error');
        }
    }

    await submitSave(false);
});

fetchExams();
loadLiveFeed();
</script>
</body>
</html>