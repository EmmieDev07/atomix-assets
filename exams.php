<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php'; 
require_once 'includes/auth_check.php';
checkTeacherAuth(); 

$db = Database::getInstance()->getConnection(); 
requireTeacherPermission($db, 'can_manage_quizzes');

$stmt = $db->prepare('SELECT c.class_id, c.class_name FROM classes c JOIN school_year sy ON sy.sy_id = c.sy_id AND sy.is_active = 1 WHERE c.teacher_id = ? ORDER BY c.class_name'); 
$stmt->execute([$_SESSION['teacher_id']]); 
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams & Live Monitor - Atomix</title>
    <link rel="stylesheet" href="assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box !important; }
        body, html { overflow-x: hidden; width: 100%; font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; margin: 0; }

        /* Navigation Tabs */
        .tab-nav {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #cbd5e1;
            margin-bottom: 20px;
            background: #ffffff;
            padding: 10px 20px 0;
            border-radius: 8px 8px 0 0;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover { color: #1e293b; }
        .tab-btn.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* General Layout */
        .exam-layout {
            display: grid;
            grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
            width: 100%;
        }

        .exam-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            width: 100%;
        }

        /* Live Monitor Widgets Grid */
        .widget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .widget-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .widget-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .widget-icon.blue { background: #eff6ff; color: #3b82f6; }
        .widget-icon.green { background: #f0fdf4; color: #10b981; }
        .widget-icon.orange { background: #fff7ed; color: #f97316; }
        .widget-icon.purple { background: #faf5ff; color: #a855f7; }

        .widget-data h4 { margin: 0; font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; }
        .widget-data span { font-size: 22px; font-weight: 800; color: #0f172a; }

        /* Data Tables */
        .table-container {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .monitor-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .monitor-table th {
            background: #f8fafc;
            color: #475569;
            padding: 12px 14px;
            font-weight: 700;
            border-bottom: 1px solid #cbd5e1;
        }
        .monitor-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .monitor-table tr:hover { background: #f1f5f9; }

        /* UI Buttons & Controls */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-weight: 600;
            font-size: 13px;
            padding: 7px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        .btn-primary { background-color: #3b82f6; color: #fff; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-secondary { background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background-color: #e2e8f0; }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .btn-sm { padding: 4px 8px; font-size: 11px; border-radius: 4px; }

        .exam-card h3 {
            color: #1e293b;
            font-size: 14px;
            font-weight: 700;
            margin: 12px 0 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .exam-list { max-height: 480px; overflow-y: auto; }
        .exam-list button {
            width: 100%;
            text-align: left;
            margin-bottom: 6px;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
        }
        .exam-list button:hover { border-color: #3b82f6; background: #f0f7ff; }
        .exam-list button strong { color: #0f172a; font-size: 12px; display: block; margin-bottom: 2px; }
        .exam-list button small { color: #64748b; font-size: 10px; display: flex; align-items: center; gap: 4px; }

        .status-badge {
            font-size: 10px;
            padding: 3px 7px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }
        .status-badge.deployed { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .status-badge.draft { background: #f1f5f9; color: #334155; border: 1px solid #94a3b8; }
        .status-badge.completed { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .status-badge.taking { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; animation: pulse 2s infinite; }
        .status-badge.not-started { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .deployment-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
        .deployment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 6px; }
        .deployment-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            background: #fff;
        }
        .deployment-card input[type="radio"] { display: none !important; }
        .deployment-card i { font-size: 14px; margin-bottom: 3px; color: #64748b; }
        .deployment-card span.status-title { font-size: 11px; font-weight: 700; color: #1e293b; display: block; }
        .deployment-card span.status-desc { font-size: 9px; color: #64748b; line-height: 1.1; }

        .deployment-card:has(input[value="0"]:checked) { border-color: #475569; background: #f1f5f9; }
        .deployment-card:has(input[value="0"]:checked) i { color: #475569; }
        .deployment-card:has(input[value="1"]:checked) { border-color: #10b981; background: #f0fdf4; }
        .deployment-card:has(input[value="1"]:checked) i { color: #10b981; }

        .field { margin-bottom: 10px; width: 100%; }
        .field label { display: block; font-weight: 600; font-size: 11px; color: #1e293b; margin-bottom: 3px; }
        .field input, .field textarea, .field select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 12px;
            color: #0f172a;
            background-color: #fff;
        }

        .classes { display: flex; flex-wrap: wrap; gap: 6px; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .classes label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fff;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }
        .classes label input[type="checkbox"] { width: 12px; height: 12px; accent-color: #3b82f6; cursor: pointer; margin: 0; }
        .classes label:has(input[type="checkbox"]:checked) { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }

        .bank-select-wrapper {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            border-radius: 6px;
            padding: 8px;
            margin: 8px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
        }

        .section {
            border: 1px solid #cbd5e1;
            border-left: 4px solid #3b82f6;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            background: #f8fafc;
        }

        .section-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        .question-card-item {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
            margin-top: 8px;
        }

        .question-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }

        .question-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; }
        .mcq-choices-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 6px; }

        .choice-input-wrapper {
            display: flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            background: #fff;
        }

        .choice-prefix {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            padding: 4px 8px;
            border-right: 1px solid #cbd5e1;
            font-size: 11px;
            min-width: 24px;
            text-align: center;
        }
        .choice-input-wrapper input { border: none !important; padding: 4px 6px !important; font-size: 11px !important; }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
        }

        .btn-danger-outline {
            background: transparent;
            border: 1px solid #fee2e2;
            color: #ef4444;
            padding: 3px 6px;
            font-size: 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        @media(max-width: 900px) { .exam-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <main class="main-content" style="padding: 1.5rem;">
            <header class="top-header" style="margin-bottom: 1rem;">
                <h1 style="font-size: 1.5rem; margin: 0; color: #0f172a;">Exams & Live Dashboard</h1>
            </header>

            <!-- Navigation Tabs -->
            <nav class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('builderTab', this)">
                    <i class="fas fa-edit"></i> Exam Builder & Manager
                </button>
                <button class="tab-btn" id="live-monitor-tab" onclick="switchTab('monitorTab', this)">
                    <i class="fas fa-chart-line"></i> Live Exam Monitor
                </button>
            </nav>

            <div class="content-wrapper">
                
                <!-- TAB 1: EXAM BUILDER -->
                <div id="builderTab" class="tab-content active">
                    <div class="exam-layout">
                        <aside class="exam-card">
                            <button class="btn btn-primary" style="width: 100%; justify-content: center;" type="button" onclick="newExam()">
                                <i class="fas fa-plus"></i> New Exam
                            </button>
                            <h3><i class="fas fa-folder-open"></i> My Exams</h3>
                            <div id="examList" class="exam-list">Loading…</div>
                        </aside>

                        <section class="exam-card">
                            <h2 id="formTitle" style="color: #1e293b; font-size: 16px; font-weight: 700; margin-bottom: 12px;">Create Exam</h2>
                            <form id="examForm">
                                <input type="hidden" id="examId">
                                
                                <div class="field">
                                    <label>Exam Title *</label>
                                    <input id="title" required placeholder="First Quarter Examination">
                                </div>

                                <div class="field">
                                    <label>Target Classes *</label>
                                    <div class="classes">
                                        <?php foreach($classes as $class): ?>
                                            <label>
                                                <input type="checkbox" name="class" value="<?= (int)$class['class_id'] ?>"> 
                                                <span><?= htmlspecialchars($class['class_name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="field">
                                    <div class="deployment-box">
                                        <label style="font-weight: 700; font-size: 11px; color: #1e293b; display: flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-rocket" style="color: #3b82f6;"></i> Unity Game Deployment State
                                        </label>
                                        <div class="deployment-options">
                                            <label class="deployment-card">
                                                <input type="radio" name="is_deployed" id="statusDraft" value="0" checked>
                                                <i class="fas fa-pause-circle"></i>
                                                <span class="status-title">Draft Mode</span>
                                                <span class="status-desc">Hidden from Unity game client.</span>
                                            </label>
                                            <label class="deployment-card">
                                                <input type="radio" name="is_deployed" id="statusDeployed" value="1">
                                                <i class="fas fa-rocket"></i>
                                                <span class="status-title">Live & Deployed</span>
                                                <span class="status-desc">Active in game client for students.</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="question-grid">
                                    <div class="field">
                                        <label>School Name</label>
                                        <input id="school" value="Saints Ignatius Learning Center">
                                    </div>
                                    <div class="field">
                                        <label>Department / Subtitle</label>
                                        <input id="department" value="Junior Department">
                                    </div>
                                    <div class="field">
                                        <label>Duration (Minutes) *</label>
                                        <input id="duration" type="number" min="1" value="60">
                                    </div>
                                </div>

                                <div class="question-grid">
                                    <div class="field">
                                        <label>Start Time (Availability Window)</label>
                                        <input id="start" type="datetime-local">
                                    </div>
                                    <div class="field">
                                        <label>End Time (Availability Window)</label>
                                        <input id="end" type="datetime-local">
                                    </div>
                                </div>

                                <h3 style="margin-top: 12px; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-layer-group" style="color: #3b82f6;"></i> Exam Sections
                                </h3>
                                <div id="sections"></div>

                                <div class="form-footer">
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn btn-secondary" onclick="addSection()">
                                            <i class="fas fa-folder-plus"></i> Add Section
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Exam Config
                                        </button>
                                    </div>
                                    <div>
                                        <button type="button" id="deleteBtn" class="btn btn-danger" style="display:none;" onclick="deleteExam()">
                                            <i class="fas fa-trash-alt"></i> Delete Exam
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>

                <!-- TAB 2: LIVE MONITORING DASHBOARD -->
                <div id="monitorTab" class="tab-content">
                    <!-- Stat Widgets -->
                    <div class="widget-grid">
                        <div class="widget-card">
                            <div class="widget-icon blue"><i class="fas fa-users"></i></div>
                            <div class="widget-data">
                                <h4>Enrolled Students</h4>
                                <span id="statTotalStudents">0</span>
                            </div>
                        </div>
                        <div class="widget-card">
                            <div class="widget-icon orange"><i class="fas fa-gamepad"></i></div>
                            <div class="widget-data">
                                <h4>Taking Exam (Live)</h4>
                                <span id="statTaking">0</span>
                            </div>
                        </div>
                        <div class="widget-card">
                            <div class="widget-icon green"><i class="fas fa-check-circle"></i></div>
                            <div class="widget-data">
                                <h4>Completed</h4>
                                <span id="statCompleted">0</span>
                            </div>
                        </div>
                        <div class="widget-card">
                            <div class="widget-icon purple"><i class="fas fa-chart-pie"></i></div>
                            <div class="widget-data">
                                <h4>Average Score</h4>
                                <span id="statAvgScore">0%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Live Sessions Data Table -->
                    <div class="exam-card" style="padding: 0; overflow: hidden;">
                        <div style="padding: 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; border: none; padding: 0;">
                                <i class="fas fa-broadcast-tower" style="color: #10b981;"></i> Active Deployed Exam Sessions
                            </h3>
                            <button class="btn btn-sm btn-secondary" onclick="loadLiveMonitoringData()">
                                <i class="fas fa-sync-alt"></i> Refresh Now
                            </button>
                        </div>
                        <div class="table-container">
                            <table class="monitor-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Target Class</th>
                                        <th>Active Deployed Exam</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Submitted Time</th>
                                    </tr>
                                </thead>
                                <tbody id="liveMonitorTableBody">
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">
                                            <i class="fas fa-spinner fa-spin"></i> Loading live session data...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- AI Exam Assistant Modal -->
    <div id="aiModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1100; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 90%; max-width: 550px; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0; font-size: 1.2rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-robot" style="color: #10b981;"></i> AI Auto-Select Questions
            </h3>
            <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1rem;">
                Specify quantities per lesson to automatically populate questions matching format <strong id="aiModalTypeBadge" style="color:#10b981; text-transform:uppercase;">Multiple Choice</strong>:
            </p>

            <div class="field">
                <label style="font-weight:700;">Select Lessons & Quantities</label>
                <div id="aiLessonList" style="max-height: 220px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem; background: #f8fafc;">
                    Loading available lessons...
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.25rem;">
                <span id="aiTotalCountLabel" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">Total Questions: 0</span>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeAiAssistantModal()">Cancel</button>
                    <button type="button" id="btnAiBuild" class="btn btn-success" onclick="runAiSectionImport()">
                        <i class="fas fa-bolt"></i> Auto-Populate Section
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
const api = 'api/exam_api.php';
let sections = [], bankQuestions = [];
let currentTargetSectionIndex = null;
let monitorInterval = null;

const $ = id => document.getElementById(id);
const esc = s => String(s || '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));

// Tab Switching
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    $(tabId).classList.add('active');
    btn.classList.add('active');

    if (tabId === 'monitorTab') {
        loadLiveMonitoringData();
        if (!monitorInterval) {
            monitorInterval = setInterval(loadLiveMonitoringData, 5000); // Auto refresh every 5s
        }
    } else {
        if (monitorInterval) {
            clearInterval(monitorInterval);
            monitorInterval = null;
        }
    }
}

// Live Monitoring Data Loader
async function loadLiveMonitoringData() {
    try {
        let res = await fetch(api + '?action=live_monitoring');
        let data = await res.json();

        if (!data.success) return;

        let sessions = data.sessions || [];
        let total = sessions.length;
        let taking = 0, completed = 0, totalPct = 0;

        let tbodyHtml = '';
        if (sessions.length === 0) {
            tbodyHtml = `<tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">No deployed exam sessions active right now.</td></tr>`;
        } else {
            tbodyHtml = sessions.map(s => {
                let statusBadge = '';
                if (s.status === 'completed') {
                    completed++;
                    let pct = (s.total_questions > 0) ? Math.round((s.score / s.total_questions) * 100) : 0;
                    totalPct += pct;
                    statusBadge = `<span class="status-badge completed"><i class="fas fa-check-circle"></i> Completed</span>`;
                } else if (s.status === 'taking') {
                    taking++;
                    statusBadge = `<span class="status-badge taking"><i class="fas fa-gamepad"></i> In Game</span>`;
                } else {
                    statusBadge = `<span class="status-badge not-started"><i class="fas fa-clock"></i> Not Started</span>`;
                }

                let scoreDisplay = s.status === 'completed' ? `<strong>${s.score}</strong> / ${s.total_questions} (${Math.round((s.score/s.total_questions)*100)}%)` : '--';
                let timeDisplay = s.end_time ? s.end_time : '--';

                return `
                    <tr>
                        <td><strong>${esc(s.student_name)}</strong></td>
                        <td>${esc(s.class_name)}</td>
                        <td><span class="badge bg-light text-dark border">${esc(s.exam_title)}</span></td>
                        <td>${statusBadge}</td>
                        <td>${scoreDisplay}</td>
                        <td>${timeDisplay}</td>
                    </tr>
                `;
            }).join('');
        }

        $('liveMonitorTableBody').innerHTML = tbodyHtml;
        $('statTotalStudents').textContent = total;
        $('statTaking').textContent = taking;
        $('statCompleted').textContent = completed;
        $('statAvgScore').textContent = completed > 0 ? Math.round(totalPct / completed) + '%' : '0%';

    } catch (err) {
        console.error("Monitoring fetch failed", err);
    }
}

// Exam Builder Logic
function newExam() { 
    $('examForm').reset();
    $('examId').value = '';
    $('formTitle').textContent = 'Create Exam';
    $('deleteBtn').style.display = 'none';
    $('statusDraft').checked = true;
    sections = [];
    addSection(); 
}

function addSection(data = { section_type: 'multiple_choice', section_label: 'Section ' + (sections.length + 1), instructions: 'Read each question carefully.', questions: [] }) {
    sections.push(data);
    renderSections();
}

function addQuestion(i) {
    let type = sections[i].section_type;
    let defaultChoices = (type === 'multiple_choice') ? ['', '', '', ''] : [];
    sections[i].questions.push({ questionText: '', choices: defaultChoices, correctAnswer: 'A' });
    renderSections();
}

function updateSection(i, key, value) { sections[i][key] = value; }
function updateQuestion(i, q, key, value) { sections[i].questions[q][key] = value; }

function updateChoice(i, q, choiceIdx, value) {
    if (!sections[i].questions[q].choices) sections[i].questions[q].choices = ['', '', '', ''];
    sections[i].questions[q].choices[choiceIdx] = value;
}

function bankOptions(i) {
    let type = sections[i].section_type,
        needed = type === 'multiple_choice' ? 'mcq' : type === 'true_false' ? 'true_false' : 'short_answer';
    return bankQuestions.filter(q => q.question_type === needed).map(q => `<option value="${q.question_id}">${esc(q.chapter_title + ' - ' + q.lesson_title + ': ' + q.question_text)}</option>`).join('');
}

function insertBankQuestion(i, id) {
    if (!id) return;
    let q = bankQuestions.find(q => q.question_id === Number(id));
    if (!q) {
        Swal.fire({ icon: 'warning', title: 'Question Not Found', text: 'Please select a valid question from the bank.', confirmButtonColor: '#3b82f6' });
        return;
    }
    sections[i].questions.push({
        questionText: q.question_text,
        choices: q.choices || ['', '', '', ''],
        correctAnswer: q.correct_answer || 'A'
    });
    renderSections();
}

function renderSections() { 
    $('sections').innerHTML = sections.map((s, i) => {
        return `
        <div class="section">
            <div class="section-header-block">
                <span class="section-title-label"><i class="fas fa-align-left"></i> ${esc(s.section_label || 'Section ' + (i + 1))}</span>
                <button type="button" class="btn-danger-outline" onclick="sections.splice(${i},1);renderSections()">
                    <i class="fas fa-trash-alt"></i> Delete Section
                </button>
            </div>
            
            <div class="question-grid">
                <div class="field">
                    <label>Type</label>
                    <select onchange="updateSection(${i},'section_type',this.value);renderSections()">
                        <option value="multiple_choice" ${s.section_type==='multiple_choice'?'selected':''}>Multiple Choice</option>
                        <option value="true_false" ${s.section_type==='true_false'?'selected':''}>True / False</option>
                        <option value="identification" ${s.section_type==='identification'?'selected':''}>Identification</option>
                    </select>
                </div>
                <div class="field">
                    <label>Label</label>
                    <input value="${esc(s.section_label)}" oninput="updateSection(${i},'section_label',this.value)">
                </div>
                <div class="field">
                    <label>Instructions</label>
                    <input value="${esc(s.instructions)}" oninput="updateSection(${i},'instructions',this.value)">
                </div>
            </div>

            <div class="bank-select-wrapper">
                <div style="flex:1;">
                    <span style="font-size:11px; font-weight:700; color:#1e40af;"><i class="fas fa-magic"></i> Individual Question Bank:</span>
                    <select id="bank-${i}" onchange="insertBankQuestion(${i},this.value)" style="width:100%; margin-top:2px;">
                        <option value="">Quick search and click to automatically insert...</option>
                        ${bankOptions(i)}
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-success" style="margin-top: 14px;" onclick="openSectionAiAssistant(${i})">
                    <i class="fas fa-wand-magic-sparkles"></i> AI Select Multiple
                </button>
            </div>

            ${s.questions.map((q, j) => {
                let typeHtml = '';
                let labels = ['A', 'B', 'C', 'D'];
                let currentKey = (q.correctAnswer || 'A').toUpperCase();

                if (s.section_type === 'multiple_choice') {
                    let choices = q.choices || ['', '', '', ''];
                    typeHtml = `
                        <div class="field" style="grid-column: span 2;">
                            <label>Choices / Options</label>
                            <div class="mcq-choices-grid">
                                ${choices.map((c, cIdx) => `
                                    <div class="choice-input-wrapper">
                                        <span class="choice-prefix">${labels[cIdx]}</span>
                                        <input type="text" placeholder="Option ${labels[cIdx]}" value="${esc(c)}" oninput="updateChoice(${i}, ${j}, ${cIdx}, this.value)">
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="field">
                            <label>Correct Key *</label>
                            <select onchange="updateQuestion(${i}, ${j}, 'correctAnswer', this.value)">
                                ${labels.map(l => `<option value="${l}" ${currentKey === l ? 'selected' : ''}>Option ${l}</option>`).join('')}
                            </select>
                        </div>
                    `;
                } else if (s.section_type === 'true_false') {
                    typeHtml = `
                        <div class="field">
                            <label>Correct Answer *</label>
                            <select onchange="updateQuestion(${i}, ${j}, 'correctAnswer', this.value)">
                                <option value="True" ${currentKey === 'TRUE' ? 'selected' : ''}>True</option>
                                <option value="False" ${currentKey === 'FALSE' ? 'selected' : ''}>False</option>
                            </select>
                        </div>
                    `;
                } else {
                    typeHtml = `
                        <div class="field">
                            <label>Correct Term *</label>
                            <input type="text" placeholder="Specify term..." value="${esc(q.correctAnswer)}" oninput="updateQuestion(${i}, ${j}, 'correctAnswer', this.value)">
                        </div>
                    `;
                }

                return `
                    <div class="question-card-item">
                        <div class="question-card-header">
                            <span style="font-weight:700; color:#334155; font-size:11px;"><i class="fas fa-question-circle"></i> Question ${j+1}</span>
                            <button type="button" class="btn-danger-outline" onclick="sections[${i}].questions.splice(${j},1);renderSections()">
                                <i class="fas fa-times"></i> Delete
                            </button>
                        </div>
                        <div class="field">
                            <label>Question Text *</label>
                            <textarea rows="2" placeholder="Write question details..." oninput="updateQuestion(${i}, ${j}, 'questionText', this.value)">${esc(q.questionText)}</textarea>
                        </div>
                        <div class="question-grid">
                            ${typeHtml}
                        </div>
                    </div>
                `;
            }).join('')}

            <button type="button" class="btn btn-sm btn-secondary" style="margin-top: 8px;" onclick="addQuestion(${i})">
                <i class="fas fa-plus"></i> Add Question Manually
            </button>
        </div>
    `}).join('');
}

async function list() {
    let r = await fetch(api + '?action=list'),
        d = await r.json();
    $('examList').innerHTML = d.exams.length ? d.exams.map(e => `
        <button onclick="loadExam(${e.exam_id})">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 4px; margin-bottom: 2px;">
                <strong style="flex: 1; margin: 0;"><i class="fas fa-file-signature"></i> ${esc(e.exam_title)}</strong>
                ${Number(e.is_deployed) === 1 
                    ? `<span class="status-badge deployed"><i class="fas fa-rocket"></i> Live</span>` 
                    : `<span class="status-badge draft"><i class="fas fa-edit"></i> Draft</span>`
                }
            </div>
            <small><i class="fas fa-users"></i> ${esc(e.classes||'No class assigned')}</small>
        </button>
    `).join('') : '<div style="text-align:center; color:#64748b; padding:10px; font-size:11px;">No exams created yet.</div>';
}

async function loadExam(id) {
    let d = await (await fetch(api + '?action=get&exam_id=' + id)).json();
    if (!d.success) { 
        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load exam details.', confirmButtonColor: '#ef4444' });
        return; 
    }
    let e = d.exam;
    $('examId').value = e.exam_id;
    $('title').value = e.exam_title;
    $('school').value = e.school_name;
    $('department').value = e.department_subtitle;
    $('duration').value = e.duration_minutes;
    $('start').value = (e.start_time || '').replace(' ', 'T').slice(0, 16);
    $('end').value = (e.end_time || '').replace(' ', 'T').slice(0, 16);
    document.querySelectorAll('input[name=class]').forEach(c => c.checked = e.class_ids.includes(Number(c.value)));
    
    if (Number(e.is_deployed) === 1) $('statusDeployed').checked = true;
    else $('statusDraft').checked = true;

    sections = e.sections || [];
    renderSections();
    $('formTitle').textContent = 'Edit Exam';
    $('deleteBtn').style.display = 'inline-flex';
}

$('examForm').addEventListener('submit', async ev => {
    ev.preventDefault();
    saveExam(false);
});

async function saveExam(forceOverride = false) {
    let class_ids = [...document.querySelectorAll('input[name=class]:checked')].map(x => Number(x.value));
    
    if (!sections.length || sections.some(s => !s.questions.length)) {
        Swal.fire({ icon: 'warning', title: 'Missing Content', text: 'Please add at least one question to each section before saving.', confirmButtonColor: '#3b82f6' });
        return;
    }
    
    let is_deployed = document.querySelector('input[name="is_deployed"]:checked').value;

    let body = {
        action: 'save',
        exam_id: $('examId').value,
        exam_title: $('title').value,
        class_ids,
        is_deployed: Number(is_deployed),
        school_name: $('school').value,
        department_subtitle: $('department').value,
        duration_minutes: $('duration').value,
        start_time: $('start').value.replace('T', ' '),
        end_time: $('end').value.replace('T', ' '),
        sections,
        force_override: forceOverride
    };

    try {
        let res = await fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        let d = await res.json();

        if (d.conflict) {
            const confirmOverride = await Swal.fire({
                icon: 'warning',
                title: 'Live Exam Conflict Detected',
                text: d.error + ' Would you like to automatically switch the existing live exam to Draft Mode and deploy this one?',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Replace & Deploy',
                cancelButtonText: 'Cancel'
            });

            if (confirmOverride.isConfirmed) {
                saveExam(true);
            }
            return;
        }

        if (d.success) {
            $('examId').value = d.exam_id;
            list();
            
            Swal.fire({
                icon: 'success',
                title: 'Exam Saved!',
                text: Number(is_deployed) === 1 ? 'Exam is now LIVE on Unity.' : 'Exam saved to Drafts.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Save Failed', text: d.error || 'Could not save exam.', confirmButtonColor: '#ef4444' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Server Error', text: 'Could not complete save request.', confirmButtonColor: '#ef4444' });
    }
}

async function deleteExam() {
    const result = await Swal.fire({
        title: 'Delete Exam?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    });

    if (!result.isConfirmed) return;

    let d = await(await fetch(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', exam_id: $('examId').value })
    })).json();

    if (d.success) {
        Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Exam deleted successfully.', timer: 1500, showConfirmButton: false });
        newExam();
        list();
    } else {
        Swal.fire({ icon: 'error', title: 'Delete Failed', text: 'Could not delete exam.', confirmButtonColor: '#ef4444' });
    }
}

async function loadBank() {
    let d = await(await fetch(api + '?action=question_bank')).json();
    if (d.success) {
        bankQuestions = d.questions;
        renderSections();
    }
}

/* AI SECTION ASSISTANT FUNCTIONS */
async function openSectionAiAssistant(sectionIdx) {
    currentTargetSectionIndex = sectionIdx;
    let sec = sections[sectionIdx];
    
    $('aiModalTypeBadge').textContent = sec.section_type.replace('_', ' ');
    const modal = document.getElementById('aiModal');
    modal.style.display = 'flex';

    const lessonListEl = document.getElementById('aiLessonList');
    lessonListEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading lessons...';

    try {
        let res = await fetch(`api/ai_exam_assistant.php?action=get_lessons&section_type=${sec.section_type}`);
        let data = await res.json();

        if (data.success && data.lessons.length > 0) {
            lessonListEl.innerHTML = data.lessons.map(l => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid #e2e8f0;">
                    <div style="flex: 1;">
                        <strong style="color: #1e293b; font-size:0.85rem; display: block;">${esc(l.chapter_title)}</strong>
                        <span style="color: #64748b; font-size:0.75rem;">${esc(l.lesson_title)} (${l.total_questions} available)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        <span style="font-size: 0.75rem; color: #475569;">Qty:</span>
                        <input type="number" class="ai-les-count" data-les-id="${l.lesson_id}" min="0" max="${l.total_questions}" value="0" style="width: 55px; padding: 0.25rem; font-weight:700; text-align: center;" oninput="updateAiTotalCount()">
                    </div>
                </div>
            `).join('');
        } else {
            lessonListEl.innerHTML = `<div style="color: #64748b; padding: 0.5rem; font-size: 0.85rem;">No bank questions found matching format '${sec.section_type}'.</div>`;
        }
    } catch (err) {
        lessonListEl.innerHTML = '<div style="color: #ef4444; padding: 0.5rem; font-size: 0.85rem;">Failed to load lessons.</div>';
    }
}

function updateAiTotalCount() {
    let total = 0;
    document.querySelectorAll('.ai-les-count').forEach(inp => total += parseInt(inp.value) || 0);
    document.getElementById('aiTotalCountLabel').textContent = `Total Questions: ${total}`;
}

function closeAiAssistantModal() {
    document.getElementById('aiModal').style.display = 'none';
}

async function runAiSectionImport() {
    if (currentTargetSectionIndex === null) return;

    let lessonConfig = [];
    document.querySelectorAll('.ai-les-count').forEach(inp => {
        let count = parseInt(inp.value) || 0;
        if (count > 0) lessonConfig.push({ lesson_id: parseInt(inp.dataset.lesId), count: count });
    });

    if (!lessonConfig.length) {
        Swal.fire({ icon: 'warning', title: 'Selection Needed', text: 'Please specify a count for at least one lesson.', confirmButtonColor: '#3b82f6' });
        return;
    }

    closeAiAssistantModal();
    
    try {
        let res = await fetch('api/ai_exam_assistant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'auto_build_section',
                lesson_config: lessonConfig,
                section_type: sections[currentTargetSectionIndex].section_type
            })
        });

        let data = await res.json();
        if (!data.success) throw new Error(data.error || 'Import failed.');

        sections[currentTargetSectionIndex].questions.push(...data.questions);
        renderSections();
        
        Swal.fire({ icon: 'success', title: 'Questions Imported', text: `Added ${data.total} questions to Section ${currentTargetSectionIndex + 1}!`, timer: 1500, showConfirmButton: false });
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'AI Assistant Error', text: err.message, confirmButtonColor: '#ef4444' });
    }
}

list();
newExam();
loadBank();
</script>
</body>
</html>