<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php'; 
require_once '../includes/auth_check.php';
checkTeacherAuth(); 

$db = Database::getInstance()->getConnection(); 
requireTeacherPermission($db, 'can_manage_quizzes');

$classes = [];
try {
    $stmt = $db->prepare('SELECT c.class_id, c.class_name FROM classes c JOIN school_year sy ON sy.sy_id = c.sy_id AND sy.is_active = 1 WHERE c.teacher_id = ? ORDER BY c.class_name'); 
    $stmt->execute([$_SESSION['teacher_id']]); 
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Graceful fallback if database engine/table errors occur
    $classes = [];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams & Monitoring - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box !important; }
        body, html { overflow-x: hidden; width: 100%; background-color: #f1f5f9; }

        /* Navigation Tabs */
        .page-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 2px;
        }
        .tab-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .tab-btn:hover { background: #f8fafc; color: #1e293b; }
        .tab-btn.active {
            background: #ffffff;
            color: #2563eb;
            border-color: #cbd5e1;
            border-bottom: 3px solid #2563eb;
            margin-bottom: -2px;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .exam-layout {
            display: grid;
            grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
            width: 100%;
        }

        .exam-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            width: 100%;
            min-width: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-weight: 500;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        .btn-primary { background-color: #3b82f6; color: #fff; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-secondary { background-color: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background-color: #f8fafc; }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-sm { padding: 4px 10px; font-size: 12px; border-radius: 6px; }

        .exam-card h3 {
            color: #334155;
            font-size: 13px;
            font-weight: 600;
            margin: 16px 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .exam-list { max-height: 480px; overflow-y: auto; padding-right: 2px; }
        .exam-list button {
            width: 100%;
            text-align: left;
            margin-bottom: 6px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 8px;
            cursor: pointer;
        }
        .exam-list button:hover { border-color: #93c5fd; background: #f0f7ff; }
        .exam-list button strong { color: #334155; font-size: 13px; font-weight: 600; display: block; margin-bottom: 2px; }
        .exam-list button small { color: #64748b; font-size: 11px; display: flex; align-items: center; gap: 4px; }

        .status-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }
        .status-badge.deployed { background: #d1fae5; color: #047857; }
        .status-badge.draft { background: #f1f5f9; color: #64748b; }

        .deployment-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .deployment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 6px; }
        .deployment-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            background: #fff;
            user-select: none;
        }
        .deployment-card input[type="radio"] { display: none !important; }
        .deployment-card i { font-size: 14px; margin-bottom: 4px; color: #64748b; }
        .deployment-card span.status-title { font-size: 11px; font-weight: 600; color: #334155; display: block; }
        .deployment-card span.status-desc { font-size: 10px; color: #64748b; line-height: 1.2; }

        .deployment-card:has(input[value="0"]:checked) { border-color: #64748b; background: #f1f5f9; }
        .deployment-card:has(input[value="0"]:checked) i { color: #475569; }
        .deployment-card:has(input[value="1"]:checked) { border-color: #10b981; background: #f0fdf4; }
        .deployment-card:has(input[value="1"]:checked) i { color: #10b981; }

        .field { margin-bottom: 12px; width: 100%; }
        .field label { display: block; font-weight: 500; font-size: 12px; color: #475569; margin-bottom: 4px; }
        .field input, .field textarea, .field select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            color: #334155;
            background-color: #fff;
        }

        .classes {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            background: #f8fafc;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .classes label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            padding: 5px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
        }
        .classes label input[type="checkbox"] { width: 13px; height: 13px; accent-color: #3b82f6; cursor: pointer; margin: 0; }
        .classes label:has(input[type="checkbox"]:checked) { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }

        .bank-select-wrapper {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .section {
            border: 1px solid #e2e8f0;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 14px;
            margin: 12px 0;
            background: #fafafa;
            width: 100%;
        }

        .section-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        .question-card-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            width: 100%;
        }

        .question-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; width: 100%; }
        
        .choice-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; margin-top: 6px; }
        .choice-item-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            border-radius: 6px;
        }
        .choice-badge {
            display: flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 4px; font-weight: 600; font-size: 11px;
            background: #e0e7ff; color: #3730a3; flex-shrink: 0;
        }
        .choice-badge.is-correct { background: #d1fae5; color: #065f46; }
        .choice-item-box input[type="text"] { border: none !important; background: transparent !important; outline: none !important; width: 100%; font-size: 12px; color: #334155; }

        .answer-key-select-wrapper {
            margin-top: 10px; display: flex; align-items: center; gap: 8px;
            background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 6px; width: fit-content;
        }

        .monitoring-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 16px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .summary-icon {
            width: 46px; height: 46px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
            flex-shrink: 0;
        }
        .summary-title { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .summary-val { font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 2px; }

        .monitoring-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .table-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .table-header-flex h3 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .monitoring-table-wrapper {
            overflow-x: auto;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
        }

        .mon-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .mon-table th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .mon-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            white-space: nowrap;
        }

        .mon-table tbody tr:last-child td { border-bottom: none; }
        .mon-table td.student-name { color: #0f172a; font-weight: 600; }

        .pill-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .pill-completed { background: #d1fae5; color: #047857; }
        .pill-answering { background: #fef3c7; color: #b45309; animation: pulse 1.5s infinite; }
        .pill-not-started { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        #loadingOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            z-index: 999999; flex-direction: column; align-items: center; justify-content: center;
            color: #ffffff; font-weight: 600; font-size: 1rem;
        }
        .spinner {
            width: 40px; height: 40px; border: 4px solid #cbd5e1; border-top-color: #3b82f6;
            border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 0.8rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div id="loadingOverlay">
        <div class="spinner"></div>
        <div id="loadingText">Processing...</div>
    </div>

    <div class="dashboard-container">
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <h1>Exams & Progress Monitoring</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Teacher'); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>

            <div class="page-tabs">
                <button class="tab-btn active" onclick="switchTab('editorTab', this)">
                    <i class="fas fa-edit"></i> Exam Builder & Manager
                </button>
                <button class="tab-btn" onclick="switchTab('monitoringTab', this); fetchLiveMonitoringData();">
                    <i class="fas fa-chart-line"></i> Live Exam Monitor
                </button>
            </div>

            <div class="content-wrapper">
                <div id="editorTab" class="tab-content active">
                    <div class="exam-layout">
                        <aside class="exam-card">
                            <button class="btn btn-primary" style="width: 100%; justify-content: center;" type="button" onclick="newExam()">
                                <i class="fas fa-plus"></i> New Exam
                            </button>
                            <h3><i class="fas fa-folder-open"></i> My Exams</h3>
                            <div id="examList" class="exam-list">Loading…</div>
                        </aside>

                        <section class="exam-card">
                            <h2 id="formTitle" style="color: #0f172a; font-size: 16px; font-weight: 600; margin-bottom: 12px;">Create Exam</h2>
                            <form id="examForm">
                                <input type="hidden" id="examId">
                                
                                <div class="field">
                                    <label>Exam Title *</label>
                                    <input id="title" required placeholder="First Quarter Examination">
                                </div>

                                <div class="field">
                                    <label>Target Classes *</label>
                                    <div class="classes">
                                        <?php if (!empty($classes)): ?>
                                            <?php foreach($classes as $class): ?>
                                                <label>
                                                    <input type="checkbox" name="class" value="<?= (int)$class['class_id'] ?>"> 
                                                    <span><?= htmlspecialchars($class['class_name']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="font-size:11px; color:#64748b;">No active classes assigned or database initializing.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="field">
                                    <div class="deployment-box">
                                        <label style="font-weight: 600; font-size: 11px; color: #334155; display: flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-rocket" style="color: #3b82f6;"></i> Deployment State
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
                                    <div class="field"><label>School Name</label><input id="school" value="Saints Ignatius Learning Center"></div>
                                    <div class="field"><label>Department / Subtitle</label><input id="department" value="Junior Department"></div>
                                    <div class="field"><label>Duration (Minutes) *</label><input id="duration" type="number" min="1" value="60"></div>
                                </div>

                                <div class="question-grid">
                                    <div class="field"><label>Start Time</label><input id="start" type="datetime-local"></div>
                                    <div class="field"><label>End Time</label><input id="end" type="datetime-local"></div>
                                </div>

                                <h3 style="margin-top: 12px; font-size: 13px;"><i class="fas fa-layer-group" style="color: #3b82f6;"></i> Exam Sections</h3>
                                <div id="sections"></div>

                                <div class="form-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" class="btn btn-secondary" onclick="addSection()"><i class="fas fa-folder-plus"></i> Add Section</button>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Exam Config</button>
                                    </div>
                                    <div>
                                        <button type="button" id="deleteBtn" class="btn btn-danger" style="display:none;" onclick="deleteExam()"><i class="fas fa-trash-alt"></i> Delete Exam</button>
                                    </div>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>

                <div id="monitoringTab" class="tab-content">
                    <div class="monitoring-summary-grid">
                        <div class="summary-card">
                            <div class="summary-icon" style="background:#eff6ff; color:#3b82f6;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="summary-title">Enrolled Students</div>
                                <div id="monEnrolled" class="summary-val">0</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon" style="background:#fff7ed; color:#f97316;"><i class="fas fa-gamepad"></i></div>
                            <div>
                                <div class="summary-title">Taking Exam (Live)</div>
                                <div id="monAnswering" class="summary-val">0</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon" style="background:#f0fdf4; color:#10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="summary-title">Completed</div>
                                <div id="monCompleted" class="summary-val">0</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon" style="background:#faf5ff; color:#a855f7;"><i class="fas fa-chart-pie"></i></div>
                            <div>
                                <div class="summary-title">Average Score</div>
                                <div id="monAvgScore" class="summary-val">0%</div>
                            </div>
                        </div>
                    </div>

                    <div class="monitoring-table-card">
                        <div class="table-header-flex">
                            <h3><i class="fas fa-broadcast-tower" style="color:#10b981;"></i> Active Deployed Exam Sessions</h3>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <select id="monExamFilter" onchange="renderMonitoringList()" style="padding:6px 10px; border-radius:6px; border:1px solid #cbd5e1; font-size:12px;">
                                    <option value="all">All Active Exams</option>
                                </select>
                                <button class="btn btn-secondary btn-sm" onclick="fetchLiveMonitoringData()"><i class="fas fa-sync-alt"></i> Refresh Now</button>
                            </div>
                        </div>

                        <div class="monitoring-table-wrapper">
                            <table class="mon-table">
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
                                <tbody id="monitoringTableBody">
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding: 2rem; color:#64748b;">No active student sessions recorded yet. Data logs automatically as students open Unity exam scenes.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="aiModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1100; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 90%; max-width: 550px; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0; font-size: 1.1rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-robot" style="color: #10b981;"></i> AI Select Questions for <span id="aiModalSectionTitle" style="color:#3b82f6;">Section</span>
            </h3>
            <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1rem;">
                Target Format: <strong id="aiModalTypeBadge" style="color:#10b981; text-transform:uppercase;">Multiple Choice</strong>
            </p>

            <div class="field">
                <label style="font-weight:600; display:block; margin-bottom:0.4rem;">Select Lessons & Quantities</label>
                <div id="aiLessonList" style="max-height: 220px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.6rem; background: #f8fafc;">Loading lessons...</div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.25rem;">
                <span id="aiTotalCountLabel" style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">Total Questions: 0</span>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeAiAssistantModal()">Cancel</button>
                    <button type="button" id="btnAiBuild" class="btn" style="background: #10b981; color: white;" onclick="runAiSectionImport()">
                        <i class="fas fa-bolt"></i> Auto-Populate
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
const api='../api/exam_api.php';
let sections=[], bankQuestions=[], allExamsList=[], monitoringData=[];
let currentTargetSectionIndex = null;
let liveInterval = null;

const $=id=>document.getElementById(id);
const esc=s=>String(s||'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));

function showLoading(text="Processing..."){ $('loadingText').textContent = text; $('loadingOverlay').style.display = 'flex'; }
function hideLoading(){ $('loadingOverlay').style.display = 'none'; }

async function safeFetchJson(url, options = {}) {
    const res = await fetch(url, options);
    const rawText = await res.text();
    
    let parsedData = null;
    try {
        parsedData = JSON.parse(rawText);
    } catch(err) {
        console.error("[ExamManager] Server returned non-JSON response:", rawText);
        throw new Error(`Server error (${res.status}). Database table engine repair required.`);
    }

    if (!res.ok) {
        if (parsedData && parsedData.debug_trace) {
            console.error("[ExamManager Backend Trace]:", parsedData.debug_trace);
        }
        throw new Error(parsedData.error || `HTTP ${res.status} Internal Error`);
    }

    return parsedData;
}

function switchTab(tabId, btn){
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    $(tabId).classList.add('active');

    if(tabId === 'monitoringTab'){
        if(!liveInterval) liveInterval = setInterval(fetchLiveMonitoringData, 5000);
    } else {
        if(liveInterval){ clearInterval(liveInterval); liveInterval = null; }
    }
}

function newExam(){ 
    $('examForm').reset();
    $('examId').value = '';
    $('formTitle').textContent = 'Create Exam';
    $('deleteBtn').style.display = 'none';
    $('statusDraft').checked = true;
    
    // Clear check boxes explicitly
    document.querySelectorAll('input[name=class]').forEach(c => c.checked = false);
    
    // Completely reset section array state
    sections = [];
    addSection(); 
}

function addSection(data = null){
    let defaultData = data ? JSON.parse(JSON.stringify(data)) : {
        section_type: 'multiple_choice',
        section_label: 'Section ' + (sections.length + 1),
        instructions: 'Read each question carefully.',
        questions: []
    };
    sections.push(defaultData);
    renderSections();
}

function addQuestion(i){
    let type = sections[i].section_type;
    let defaultChoices = (type === 'multiple_choice' || type === 'matching') ? ['', '', '', ''] : [];
    sections[i].questions.push({
        questionText: '',
        choices: defaultChoices,
        correctAnswer: (type === 'multiple_choice' || type === 'matching' ? 'A' : (type === 'true_false' ? 'True' : ''))
    });
    renderSections();
}

function updateSection(i,key,value){ sections[i][key]=value; }
function updateQuestion(i,q,key,value){ sections[i].questions[q][key]=value; }

function updateChoice(i,q,choiceIdx,value){
    if(!sections[i].questions[q].choices) sections[i].questions[q].choices = ['', '', '', ''];
    sections[i].questions[q].choices[choiceIdx] = value;
}

function bankOptions(i) {
    let currentType = (sections[i].section_type || '').toLowerCase().trim();
    
    // Strictly filter bank questions matching the section type
    let filtered = bankQuestions.filter(q => {
        let qType = (q.question_type || q.type || '').toLowerCase().trim();
        
        // Handle variations (e.g. "multiple_choice" vs "multiple choice")
        if (currentType === 'multiple_choice') {
            return qType === 'multiple_choice' || qType === 'multiple choice' || qType === 'mcq';
        }
        if (currentType === 'true_false') {
            return qType === 'true_false' || qType === 'true false' || qType === 'tf';
        }
        if (currentType === 'identification') {
            return qType === 'identification' || qType === 'fill_in_the_blank' || qType === 'short_answer';
        }
        if (currentType === 'matching') {
            return qType === 'matching' || qType === 'matching_type';
        }
        
        return qType === currentType;
    });

    if (!filtered.length) {
        return `<option value="">No ${currentType.replace('_', ' ')} questions found in Bank</option>`;
    }

    return filtered.map(q => {
        let displayText = `${q.chapter_title || 'Chapter'} - ${q.lesson_title || 'Lesson'}: ${q.question_text || 'Untitled Question'}`;
        return `<option value="${q.question_id}">${esc(displayText)}</option>`;
    }).join('');
}

function insertBankQuestion(i, id) {
    if (!id) return;
    let q = bankQuestions.find(q => Number(q.question_id) === Number(id));
    if (!q) return;
    
    let targetType = sections[i].section_type;
    let defaultChoices = ['', '', '', ''];

    // Handle choice parsing safely across different bank formats
    if (q.choices) {
        if (Array.isArray(q.choices)) {
            defaultChoices = [...q.choices];
        } else if (typeof q.choices === 'string') {
            try {
                let parsed = JSON.parse(q.choices);
                if (Array.isArray(parsed)) defaultChoices = parsed;
            } catch(e) {
                defaultChoices = ['', '', '', ''];
            }
        }
    }

    // Standardize correct answer default
    let defaultAnswer = q.correct_answer || q.answer || '';
    if (!defaultAnswer) {
        if (targetType === 'multiple_choice' || targetType === 'matching') defaultAnswer = 'A';
        else if (targetType === 'true_false') defaultAnswer = 'True';
    }

    sections[i].questions.push({
        questionText: q.question_text || '',
        choices: defaultChoices,
        correctAnswer: defaultAnswer
    });

    // Reset dropdown selector after adding
    let selectEl = $(`bank-${i}`);
    if (selectEl) selectEl.value = '';

    renderSections();
}

function renderSections(){ 
    $('sections').innerHTML=sections.map((s,i)=>{
        let labels = ['A', 'B', 'C', 'D'];
        return `
        <div class="section">
            <div class="section-header-block">
                <span style="font-size:12px; font-weight:600; color:#1e3a8a;"><i class="fas fa-align-left"></i> ${esc(s.section_label || 'Section '+(i+1))}</span>
                <button type="button" class="btn btn-danger btn-sm" onclick="sections.splice(${i},1);renderSections()"><i class="fas fa-trash-alt"></i> Delete Section</button>
            </div>
            
            <div class="question-grid">
                <div class="field">
                    <label>Type</label>
                    <select onchange="updateSection(${i},'section_type',this.value);renderSections()">
                        <option value="multiple_choice" ${s.section_type==='multiple_choice'?'selected':''}>Multiple Choice</option>
                        <option value="true_false" ${s.section_type==='true_false'?'selected':''}>True / False</option>
                        <option value="identification" ${s.section_type==='identification'?'selected':''}>Identification</option>
                        <option value="matching" ${s.section_type==='matching'?'selected':''}>Matching</option>
                    </select>
                </div>
                <div class="field"><label>Label</label><input value="${esc(s.section_label)}" oninput="updateSection(${i},'section_label',this.value)"></div>
                <div class="field"><label>Instructions</label><input value="${esc(s.instructions)}" oninput="updateSection(${i},'instructions',this.value)"></div>
            </div>

            <div class="bank-select-wrapper">
                <div style="flex:1;">
                    <span style="font-size:11px; font-weight:600; color:#1e40af;"><i class="fas fa-list-ul"></i> Manual Question Bank Import (${esc(s.section_type.replace('_',' '))}):</span>
                    <select id="bank-${i}" onchange="insertBankQuestion(${i},this.value)" style="width:100%;">
                        <option value="">-- Choose question to add manually --</option>
                        ${bankOptions(i)}
                    </select>
                </div>
                <div>
                    <button type="button" class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white;" onclick="openSectionAiAssistant(${i})">
                        <i class="fas fa-wand-magic-sparkles"></i> AI Auto-Select
                    </button>
                </div>
            </div>

            ${s.questions.map((q,j)=>{
                let choicesHtml = '';
                let currentAnswerKey = (q.correctAnswer || 'A').toString().trim();

                if (s.section_type === 'multiple_choice' || s.section_type === 'matching') {
                    let choices = q.choices || ['', '', '', ''];
                    let answerUpper = currentAnswerKey.toUpperCase();
                    choicesHtml = `
                        <div style="margin-top:0.5rem; grid-column: span 3;">
                            <label style="font-weight:600; font-size:0.8rem; color:#475569;">Answer Choices:</label>
                            <div class="choice-grid">
                                ${choices.map((c, cIdx) => {
                                    let letter = labels[cIdx];
                                    let isMatched = (letter === answerUpper);
                                    return `
                                        <div class="choice-item-box">
                                            <span class="choice-badge ${isMatched ? 'is-correct' : ''}">${letter}</span>
                                            <input type="text" value="${esc(c)}" placeholder="Option ${letter}" oninput="updateChoice(${i}, ${j}, ${cIdx}, this.value)">
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                            <div class="answer-key-select-wrapper">
                                <label style="font-size:0.8rem; font-weight:600; color:#065f46; margin:0;"><i class="fas fa-check-circle"></i> Correct Answer Key:</label>
                                <select onchange="updateQuestion(${i}, ${j}, 'correctAnswer', this.value); renderSections();">
                                    ${labels.map(l => `<option value="${l}" ${answerUpper === l ? 'selected' : ''}>Option ${l}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                    `;
                } else if (s.section_type === 'true_false') {
                    let tfVal = currentAnswerKey.toLowerCase();
                    let isTrue = tfVal === 'true';
                    choicesHtml = `
                        <div class="field" style="margin-top:0.5rem; width:220px;">
                            <label style="font-size:0.8rem; color:#059669; font-weight:600;"><i class="fas fa-check-circle"></i> Correct Answer Key:</label>
                            <select onchange="updateQuestion(${i}, ${j}, 'correctAnswer', this.value); renderSections();" style="border-color:#10b981; font-weight:600;">
                                <option value="True" ${isTrue ? 'selected' : ''}>True</option>
                                <option value="False" ${!isTrue ? 'selected' : ''}>False</option>
                            </select>
                        </div>
                    `;
                } else {
                    choicesHtml = `
                        <div class="field" style="margin-top:0.5rem; width:320px;">
                            <label style="font-size:0.8rem; color:#059669; font-weight:600;"><i class="fas fa-check-circle"></i> Correct Answer Key:</label>
                            <input type="text" value="${esc(q.correctAnswer)}" placeholder="Enter correct answer" oninput="updateQuestion(${i}, ${j}, 'correctAnswer', this.value)" style="border-color:#10b981; font-weight:600;">
                        </div>
                    `;
                }

                return `
                    <div class="question-card-item">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-weight:600; color:#334155; font-size:11px;"><i class="fas fa-question-circle"></i> Question ${j+1}</span>
                            <button type="button" class="btn btn-danger btn-sm" onclick="sections[${i}].questions.splice(${j},1);renderSections()"><i class="fas fa-times"></i> Delete</button>
                        </div>
                        <div class="field">
                            <label>Question Text *</label>
                            <textarea rows="2" placeholder="Write question detail..." oninput="updateQuestion(${i}, ${j}, 'questionText', this.value)">${esc(q.questionText)}</textarea>
                        </div>
                        ${choicesHtml}
                    </div>
                `;
            }).join('')}

            <button type="button" class="btn btn-sm btn-secondary" style="margin-top: 8px;" onclick="addQuestion(${i})"><i class="fas fa-plus"></i> Add Manual Question</button>
        </div>
    `}).join('');
}

async function list(){
    try {
        let d = await safeFetchJson(api+'?action=list');
        allExamsList = d.exams || [];
        $('examList').innerHTML=allExamsList.length?allExamsList.map(e=>`
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
        `).join(''):'<div style="text-align:center; color:#64748b; padding:10px; font-size:11px;">No exams created yet.</div>';
    } catch (err) {
        $('examList').innerHTML = `<div style="color:#ef4444; font-size:11px; padding:8px;">Table repair required in database.</div>`;
    }
}

async function loadExam(id){
    showLoading("Loading Exam Data...");
    try {
        let d = await safeFetchJson(`${api}?action=get&exam_id=${id}`);
        if(!d.success || !d.exam) {
            return Swal.fire('Error', d.error || 'Could not load exam details.', 'error');
        }
        let e = d.exam;
        $('examId').value = e.exam_id;
        $('title').value = e.exam_title;
        $('school').value = e.school_name || '';
        $('department').value = e.department_subtitle || '';
        $('duration').value = e.duration_minutes || 60;
        $('start').value = (e.start_time||'').replace(' ','T').slice(0,16);
        $('end').value = (e.end_time||'').replace(' ','T').slice(0,16);
        
        let targetClasses = Array.isArray(e.class_ids) ? e.class_ids : [];
        document.querySelectorAll('input[name=class]').forEach(c=>c.checked = targetClasses.includes(Number(c.value)));
        
        if (Number(e.is_deployed) === 1) $('statusDeployed').checked = true;
        else $('statusDraft').checked = true;

        sections = e.sections || [];
        renderSections();
        $('formTitle').textContent = 'Edit Exam';
        $('deleteBtn').style.display = 'inline-flex';
    } catch(err) {
        Swal.fire('Exam Manager Error', err.message, 'error');
    } finally {
        hideLoading();
    }
}

$('examForm').addEventListener('submit', async ev => {
    ev.preventDefault();
    let currentExamId = Number($('examId').value || 0);
    let class_ids = [...document.querySelectorAll('input[name=class]:checked')].map(x=>Number(x.value));
    let is_deployed = Number(document.querySelector('input[name="is_deployed"]:checked').value);
    
    if(!class_ids.length){
        return Swal.fire('Target Class Required', 'Please assign at least one target class.', 'warning');
    }

    if(!sections.length || sections.some(s=>!s.questions.length)){
        return Swal.fire('Missing Content', 'Please add at least one question to each section.', 'warning');
    }

    if (is_deployed === 1) {
        let conflictingExams = allExamsList.filter(e => {
            if (Number(e.exam_id) === currentExamId) return false;
            if (Number(e.is_deployed) !== 1) return false;
            
            let existingClassIds = e.class_ids || [];
            return existingClassIds.some(cid => class_ids.includes(Number(cid)));
        });

        if (conflictingExams.length > 0) {
            let conflictNames = conflictingExams.map(c => `• <b>${esc(c.exam_title)}</b>`).join('<br>');
            const result = await Swal.fire({
                title: 'Deployment Conflict Detected',
                html: `Another Live/Deployed exam is already assigned to the same class section:<br><br>${conflictNames}<br><br>Would you like to <b>un-deploy</b> the other exam and set this one as the active Live exam?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Replace & Deploy',
                cancelButtonText: 'Keep as Draft'
            });

            if (!result.isConfirmed) {
                $('statusDraft').checked = true;
                is_deployed = 0;
            }
        }
    }

    showLoading("Saving Exam Configuration...");

    let body={
        action:'save',
        exam_id:$('examId').value,
        exam_title:$('title').value,
        class_ids,
        is_deployed,
        school_name:$('school').value,
        department_subtitle:$('department').value,
        duration_minutes:$('duration').value,
        start_time:$('start').value.replace('T',' '),
        end_time:$('end').value.replace('T',' '),
        sections
    };

    try {
        let d = await safeFetchJson(api, {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(body)
        });

        if(d.success){
            $('examId').value = d.exam_id;
            await list();
            Swal.fire({
                icon: 'success',
                title: 'Exam Saved!',
                text: is_deployed === 1 ? 'Exam is LIVE and ready for Unity sync!' : 'Exam saved to Drafts.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Save Failed', d.error || 'Could not save exam settings.', 'error');
        }
    } catch(err) {
        Swal.fire('Save Error', err.message, 'error');
    } finally {
        hideLoading();
    }
});

async function deleteExam(){
    const result = await Swal.fire({
        title: 'Delete Exam?', text: "This action cannot be undone!", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonText: 'Cancel', confirmButtonText: 'Yes, delete!'
    });
    if (!result.isConfirmed) return;

    showLoading("Deleting Exam...");
    try {
        let d = await safeFetchJson(api, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:'delete', exam_id:$('examId').value})
        });

        if(d.success){
            Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1500, showConfirmButton: false });
            newExam(); list();
        }
    } catch(err) {
        Swal.fire('Delete Failed', err.message, 'error');
    } finally { hideLoading(); }
}

async function loadBank(){
    try {
        // Fetch full question pool using the same backend query source as AI assistant
        let d = await safeFetchJson('../api/ai_exam_assistant.php?action=get_all_questions');
        if(d.success){ 
            bankQuestions = d.questions || []; 
            renderSections(); 
        }
    } catch(err) {
        // Fallback to standard endpoint if dedicated AI query isn't present
        try {
            let d = await safeFetchJson(api+'?action=question_bank');
            if(d.success){ bankQuestions = d.questions || []; renderSections(); }
        } catch(e) { console.warn("Question bank query paused."); }
    }
}

async function fetchLiveMonitoringData() {
    try {
        let data = await safeFetchJson(`${api}?action=live_monitoring`);
        if(data.success){
            monitoringData = data.sessions || [];
            updateMonitoringStats();
            renderMonitoringList();
        }
    } catch(err){ console.warn("Failed syncing monitoring feed."); }
}

function updateMonitoringStats() {
    let enrolled = monitoringData.length;
    let answering = monitoringData.filter(s => s.status === 'taking').length;
    let completed = monitoringData.filter(s => s.status === 'completed').length;
    
    let completedSessions = monitoringData.filter(s => s.status === 'completed' && s.total_questions > 0);
    let avgScorePercent = 0;
    if (completedSessions.length > 0) {
        let totalPct = completedSessions.reduce((acc, curr) => acc + ((curr.score / curr.total_questions) * 100), 0);
        avgScorePercent = Math.round(totalPct / completedSessions.length);
    }

    $('monEnrolled').textContent = enrolled;
    $('monAnswering').textContent = answering;
    $('monCompleted').textContent = completed;
    $('monAvgScore').textContent = `${avgScorePercent}%`;

    let filter = $('monExamFilter');
    let currentVal = filter.value;
    let activeExamsList = Array.from(new Set(monitoringData.map(s => JSON.stringify({id: s.exam_id, title: s.exam_title}))));

    filter.innerHTML = `<option value="all">All Active Exams</option>` + activeExamsList.map(itemStr => {
        let item = JSON.parse(itemStr);
        return `<option value="${item.id}" ${currentVal == item.id ? 'selected' : ''}>${esc(item.title)}</option>`;
    }).join('');
}

function renderMonitoringList() {
    let tbody = $('monitoringTableBody');
    let filterVal = $('monExamFilter').value;

    let filtered = monitoringData;
    if (filterVal !== 'all') {
        filtered = monitoringData.filter(s => String(s.exam_id) === String(filterVal));
    }

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem; color:#64748b;">No student sessions recorded yet. Data logs automatically as students open Unity exam scenes.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(s => {
        let statusPill = '';
        if (s.status === 'completed') {
            statusPill = `<span class="pill-status pill-completed"><i class="fas fa-check-circle"></i> COMPLETED</span>`;
        } else if (s.status === 'taking') {
            statusPill = `<span class="pill-status pill-answering"><i class="fas fa-gamepad"></i> ANSWERING</span>`;
        } else {
            statusPill = `<span class="pill-status pill-not-started"><i class="fas fa-clock"></i> NOT STARTED</span>`;
        }

        let scoreDisplay = '--';
        if (s.status === 'completed' && s.total_questions > 0) {
            let pct = Math.round((s.score / s.total_questions) * 100);
            scoreDisplay = `${s.score} / ${s.total_questions} (${pct}%)`;
        }

        let submittedTime = s.end_time ? esc(s.end_time) : '--';

        return `
            <tr>
                <td class="student-name">${esc(s.student_name)}</td>
                <td>${esc(s.class_name)}</td>
                <td>${esc(s.exam_title)}</td>
                <td>${statusPill}</td>
                <td>${scoreDisplay}</td>
                <td>${submittedTime}</td>
            </tr>
        `;
    }).join('');
}

async function openSectionAiAssistant(sectionIdx) {
    currentTargetSectionIndex = sectionIdx;
    let sec = sections[sectionIdx];
    
    $('aiModalSectionTitle').textContent = sec.section_label || `Section ${sectionIdx + 1}`;
    $('aiModalTypeBadge').textContent = sec.section_type.replace('_', ' ');

    $('aiModal').style.display = 'flex';
    const lessonListEl = $('aiLessonList');
    lessonListEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading available lessons...';

    try {
        let data = await safeFetchJson(`../api/ai_exam_assistant.php?action=get_lessons&section_type=${sec.section_type}`);

        if (data.success && data.lessons.length > 0) {
            lessonListEl.innerHTML = data.lessons.map(l => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                    <div style="flex: 1; padding-right: 0.5rem;">
                        <strong style="color: #334155; font-weight:600; font-size:0.85rem; display: block;">${esc(l.chapter_title)}</strong>
                        <span style="color: #64748b; font-size:0.75rem;">${esc(l.lesson_title)}</span>
                        <div style="color: #10b981; font-size:0.7rem; font-weight:600;">${l.total_questions} questions</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <span style="font-size: 0.8rem; color: #64748b;">Qty:</span>
                        <input type="number" class="ai-les-count" data-les-id="${l.lesson_id}" min="0" max="${l.total_questions}" value="0" style="width: 55px; padding: 0.25rem; border: 1px solid #cbd5e1; border-radius: 5px; font-weight: 600; text-align: center;" oninput="updateAiTotalCount()">
                    </div>
                </div>
            `).join('');
        } else {
            lessonListEl.innerHTML = `<div style="color: #64748b; padding: 0.5rem; font-size:0.8rem;">No matching questions found in Question Bank.</div>`;
        }
    } catch (err) {
        lessonListEl.innerHTML = '<div style="color: #ef4444; padding: 0.5rem; font-size:0.8rem;">Failed to load lessons.</div>';
    }
}

function updateAiTotalCount() {
    let total = 0;
    document.querySelectorAll('.ai-les-count').forEach(inp => total += parseInt(inp.value) || 0);
    $('aiTotalCountLabel').textContent = `Total Questions: ${total}`;
}

function closeAiAssistantModal() { $('aiModal').style.display = 'none'; }

async function runAiSectionImport() {
    if (currentTargetSectionIndex === null) return;

    let lessonConfig = [];
    document.querySelectorAll('.ai-les-count').forEach(inp => {
        let count = parseInt(inp.value) || 0;
        if (count > 0) lessonConfig.push({ lesson_id: parseInt(inp.dataset.lesId), count: count });
    });

    if (!lessonConfig.length) return Swal.fire('Warning', 'Please specify a count for at least one lesson.', 'warning');

    closeAiAssistantModal();
    showLoading("AI Assistant is importing questions & choices...");

    try {
        let data = await safeFetchJson('../api/ai_exam_assistant.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'auto_build_section', lesson_config: lessonConfig, section_type: sections[currentTargetSectionIndex].section_type })
        });

        if (!data.success) throw new Error(data.error || 'Failed to import.');

        sections[currentTargetSectionIndex].questions.push(...data.questions);
        renderSections();
        Swal.fire({ icon: 'success', title: 'Populated!', text: `Added ${data.total} questions!`, timer: 1500, showConfirmButton: false });
    } catch (err) {
        Swal.fire('Import Failed', err.message, 'error');
    } finally {
        hideLoading();
    }
}

list();
newExam();
loadBank();
</script>
</body>
</html>