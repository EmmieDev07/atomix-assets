<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_classes');

$dbError = null;
try {
// Get all classes (both active and archived)
$stmt = $db->prepare("SELECT c.*, sy.label as school_year, sy.is_active as sy_active FROM classes c LEFT JOIN school_year sy ON c.sy_id = sy.sy_id WHERE c.teacher_id = ? ORDER BY c.class_id DESC");
$stmt->execute([$_SESSION['teacher_id']]);
$all_classes = $stmt->fetchAll();

// Separate active and archived classes
$active_classes = array_filter($all_classes, function($class) { return $class['sy_active']; });
$archived_classes = array_filter($all_classes, function($class) { return !$class['sy_active']; });
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('classes.php DB error: ' . $e->getMessage());
    $all_classes = []; $active_classes = []; $archived_classes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
        }

        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            background: #f3f4f6;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f0f9ff;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .student-info {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-row {
            margin-bottom: 0.5rem;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .details-section {
            margin-bottom: 2rem;
        }

        .details-section h4 {
            color: #1f2937;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .details-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .detail-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .score-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .score-badge.completed {
            background: #dcfce7;
            color: #166534;
        }

        .score-badge.in-progress {
            background: #fef3c7;
            color: #92400e;
        }

        .score-pill {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-right: 0.35rem;
            margin-top: 0.2rem;
        }

        .score-pill.good {
            background: #dcfce7;
            color: #166534;
        }

        .score-pill.medium {
            background: #fef3c7;
            color: #92400e;
        }

        .score-pill.low {
            background: #fee2e2;
            color: #991b1b;
        }

        .score-pill.none {
            background: #e5e7eb;
            color: #4b5563;
        }

        .detail-meta {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .no-data {
            color: #6b7280;
            font-style: italic;
            text-align: center;
            padding: 2rem;
        }

        .search-container {
            position: relative;
        }

        .search-input-wrapper {
            position: relative;
            max-width: 500px;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 40px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 16px;
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .status-badge.archived {
            background: #fef3c7;
            color: #92400e;
        }

        /* Tab Content Visibility */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
     <div class="dashboard-container">
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
                
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Class Management</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Class Management</span>
            </nav>
            <?php if (!empty($dbError)): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>

            <div class="content-area">
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="active-classes">
                        <i class="fas fa-graduation-cap"></i> Active Classes (<?php echo count($active_classes); ?>)
                    </button>
                    <button class="tab-btn" data-tab="archived-classes">
                        <i class="fas fa-archive"></i> Archived Classes (<?php echo count($archived_classes); ?>)
                    </button>
                </div>

                <!-- Active Classes Tab -->
                <div class="tab-content active" id="active-classes-tab">
                    <div class="section-header">
                        <h2>Active Classes</h2>
                        <div class="section-info" style="margin-bottom:0;">
                            <i class="fas fa-user-shield"></i>
                            You can only view the sections assigned to your account by the admin.
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>School Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="activeClassesTableBody">
                                <?php if (empty($active_classes)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No active classes are assigned to you yet.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($active_classes as $class): ?>
                                <tr data-id="<?php echo $class['class_id']; ?>">
                                    <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['school_year'] ?? 'N/A'); ?></td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-info" onclick="viewStudents(<?php echo $class['class_id']; ?>, '<?php echo htmlspecialchars($class['class_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-users"></i> View Students
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Archived Classes Tab -->
                <div class="tab-content" id="archived-classes-tab">
                    <div class="section-header">
                        <h2>Archived Classes</h2>
                        <div class="section-info">
                            <i class="fas fa-info-circle"></i>
                            These classes belong to inactive school years and are preserved for historical records.
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>School Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archived_classes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No archived classes found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($archived_classes as $class): ?>
                                <tr data-id="<?php echo $class['class_id']; ?>">
                                    <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['school_year'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge archived">
                                            <i class="fas fa-archive"></i> Archived
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-info" onclick="viewStudents(<?php echo $class['class_id']; ?>, '<?php echo htmlspecialchars($class['class_name'], ENT_QUOTES); ?>', true)">
                                            <i class="fas fa-users"></i> View Students
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span class="toast-message"></span>
    </div>
</body>

<!-- View Students Modal -->
<div class="modal" id="viewStudentsModal">
    <div class="modal-content" style="margin:0 auto;width:min(96vw, 1500px);max-width:1500px;">
        <div class="modal-header">
            <h3><i class="fas fa-users"></i> Students in <span id="studentsClassName"></span></h3>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary btn-sm" onclick="exportStudentsExcel()" title="Download as CSV">
                    <i class="fas fa-download"></i> Export to Excel
                </button>
                <button class="btn btn-info btn-sm" onclick="exportStudentsPdf()" title="Download class report as PDF">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="close-btn" onclick="closeModal('viewStudentsModal')">&times;</button>
            </div>
        </div>
        <div class="modal-body">
            <!-- Search Input -->
            <div class="search-container" style="margin-bottom: 20px;">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="studentSearch" placeholder="Search students by name, email, or status..." class="search-input">
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Quizzes Taken</th>
                        <th>Stages Completed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsListBody">
                    <tr><td colspan="8" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Student Details Modal -->
<div class="modal" id="studentDetailsModal">
    <div class="modal-content" style="margin:0 auto;max-width:1000px;">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> Student Details</h3>
            <button class="close-btn" onclick="closeModal('studentDetailsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="student-info">
                <div class="info-row">
                    <strong>Name:</strong> <span id="studentDetailName"></span>
                </div>
                <div class="info-row">
                    <strong>Email:</strong> <span id="studentDetailEmail"></span>
                </div>
                <div class="info-row">
                    <strong>Gender:</strong> <span id="studentDetailGender"></span>
                </div>
                <div class="info-row">
                    <strong>Status:</strong> <span id="studentDetailStatus"></span>
                </div>
            </div>

            <div class="details-section">
                <h4><i class="fas fa-clipboard-check"></i> Quiz Performance</h4>
                <div id="quizDetails" class="details-list"></div>
            </div>

            <div class="details-section">
                <h4><i class="fas fa-file-signature"></i> Exam Results</h4>
                <div id="examDetails" class="details-list"></div>
            </div>

            <div class="details-section">
                <h4><i class="fas fa-gamepad"></i> Game Progress</h4>
                <div style="margin-bottom: 10px;">
                    <label for="gameChapterFilter" style="font-weight: 600; margin-right: 8px;">Chapter:</label>
                    <select id="gameChapterFilter" onchange="filterStudentGameDetails()" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 220px;">
                        <option value="">All Chapters</option>
                    </select>
                </div>
                <div id="gameDetails" class="details-list"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions
function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) {
        return;
    }
    modal.classList.add('show');
}
function closeModal(id) {
    var modal = document.getElementById(id);
    if (!modal) {
        return;
    }
    modal.classList.remove('show');
}

function viewStudents(classId, className, isArchived = false) {
    const modalTitle = document.querySelector('#viewStudentsModal h3');
    if (isArchived) {
        modalTitle.innerHTML = '<i class="fas fa-users"></i> Students in <span id="studentsClassName"></span> <span class="status-badge archived" style="font-size: 0.8rem; margin-left: 10px;"><i class="fas fa-archive"></i> Archived Class</span>';
    } else {
        modalTitle.innerHTML = '<i class="fas fa-users"></i> Students in <span id="studentsClassName"></span>';
    }

    document.getElementById('studentsClassName').textContent = className;
    // Store the class ID for export functionality
    window.currentClassId = classId;
    openModal('viewStudentsModal');
    const tbody = document.getElementById('studentsListBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center">Loading...</td></tr>';

    // Clear search input
    document.getElementById('studentSearch').value = '';
    fetch('../api/class_students_api.php?class_id=' + classId)
        .then(res => {
            if (!res.ok) {
                throw new Error('HTTP error! status: ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            console.log('API Response:', data); // Debug log
            if (data.success && data.students && data.students.length > 0) {
                // Store original data for search functionality
                window.currentStudentData = data.students;

                // Display all students initially
                displayStudents(window.currentStudentData);

                // Set up search functionality
                setupStudentSearch();
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No students found in this class.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading students:', error);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">Error loading students: ' + error.message + '</td></tr>';
        });
}

function displayStudents(students) {
    const tbody = document.getElementById('studentsListBody');
    tbody.innerHTML = students.map(row => {
        const totalQuizzes = row.quiz_stats ? row.quiz_stats.total_quizzes_taken || 0 : 0;
        const statusBadge = row.status === 'active'
            ? '<span style="color: #16a34a; font-weight: bold;">Active</span>'
            : '<span style="color: #dc2626; font-weight: bold;">Inactive</span>';

        // Game progress data
        const gameStats = row.game_stats || {};
        const completedStages = gameStats.completed_stages || 0;

        return `<tr>
            <td>${row.first_name || ''}</td>
            <td>${row.last_name || ''}</td>
            <td class='gender-col'>${row.gender || 'N/A'}</td>
            <td>${row.email || ''}</td>
            <td>${statusBadge}</td>
            <td>${totalQuizzes}</td>
            <td>${completedStages}</td>
            <td>
                <button class="btn btn-sm btn-info" onclick="viewStudentDetails(${JSON.stringify(row).replace(/"/g, '&quot;')})">
                    <i class="fas fa-eye"></i> Details
                </button>
            </td>
        </tr>`;
    }).join('');
}

function setupStudentSearch() {
    const searchInput = document.getElementById('studentSearch');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();

        if (!window.currentStudentData) return;

        if (searchTerm === '') {
            // Show all students if search is empty
            displayStudents(window.currentStudentData);
            return;
        }

        // Filter students based on search term
        const filteredStudents = window.currentStudentData.filter(student => {
            const fullName = `${student.first_name || ''} ${student.last_name || ''}`.toLowerCase();
            const email = (student.email || '').toLowerCase();
            const status = (student.status || '').toLowerCase();
            const gender = (student.gender || '').toLowerCase();

            return fullName.includes(searchTerm) ||
                   email.includes(searchTerm) ||
                   status.includes(searchTerm) ||
                   gender.includes(searchTerm);
        });

        displayStudents(filteredStudents);
    });
}

function viewStudentDetails(studentData) {
    // Populate student details modal
    document.getElementById('studentDetailName').textContent = `${studentData.first_name} ${studentData.last_name}`;
    document.getElementById('studentDetailEmail').textContent = studentData.email;
    document.getElementById('studentDetailGender').textContent = studentData.gender || 'N/A';
    document.getElementById('studentDetailStatus').textContent = studentData.status;

    // Quiz details
    const quizDetails = document.getElementById('quizDetails');
    if (studentData.quiz_scores && studentData.quiz_scores.length > 0) {
        quizDetails.innerHTML = studentData.quiz_scores.map(quiz => {
            const score = Number(quiz.score);
            const total = Number(quiz.total_score);
            const hasScore = quiz.score !== null && quiz.score !== undefined && quiz.score !== '';
            const hasTotal = Number.isFinite(total) && total > 0;
            const scoreText = hasScore
                ? (hasTotal
                    ? `Score: ${score}/${total} (${((score / total) * 100).toFixed(1)}%)`
                    : `Score: ${score}`)
                : 'Score: Not available';
            const submittedText = quiz.submitted_at
                ? `Submitted: ${new Date(quiz.submitted_at).toLocaleDateString()}`
                : 'Submitted: Not available';

            return `
            <div class="detail-item">
                <div class="detail-header">
                    <strong>${quiz.quiz_title || 'Untitled quiz'}</strong>
                    <span class="score-badge completed">${scoreText}</span>
                </div>
                <div class="detail-meta">
                    ${submittedText}
                </div>
            </div>
        `;
        }).join('');
    } else {
        quizDetails.innerHTML = '<p class="no-data">No quiz scores available</p>';
    }

    // Exam details
    const examDetails = document.getElementById('examDetails');
    if (studentData.exam_results && studentData.exam_results.length > 0) {
        examDetails.innerHTML = studentData.exam_results.map(exam => {
            const score = Number(exam.score);
            const total = Number(exam.total_questions);
            const hasTotal = Number.isFinite(total) && total > 0;
            const scoreText = hasTotal
                ? `Score: ${score}/${total} (${((score / total) * 100).toFixed(1)}%)`
                : 'Score: Not available';
            const submittedText = exam.submitted_at
                ? `Submitted: ${new Date(exam.submitted_at).toLocaleDateString()}`
                : 'Submitted: Not available';

            return `
                <div class="detail-item">
                    <div class="detail-header">
                        <strong>${exam.exam_title || 'Untitled exam'}</strong>
                        <span class="score-badge completed">${scoreText}</span>
                    </div>
                    <div class="detail-meta">${submittedText}</div>
                </div>
            `;
        }).join('');
    } else {
        examDetails.innerHTML = '<p class="no-data">No exam results available</p>';
    }

    // Game details
    const gameDetails = document.getElementById('gameDetails');

    const buildScorePill = (label, score, total) => {
        if (score === null || score === undefined || Number(total) <= 0) {
            return `<span class="score-pill none">${label}: N/A</span>`;
        }

        const pct = (Number(score) / Number(total)) * 100;
        const toneClass = pct >= 80 ? 'good' : (pct >= 50 ? 'medium' : 'low');
        return `<span class="score-pill ${toneClass}">${label}: ${score}/${total}</span>`;
    };

    const renderGameProgressItems = (progressItems) => {
        if (!progressItems || progressItems.length === 0) {
            gameDetails.innerHTML = '<p class="no-data">No game progress available</p>';
            return;
        }

        gameDetails.innerHTML = progressItems.map(stage => {
            const progressStatus = (stage.status || '').replace('_', ' ');
            const progressStatusLabel = progressStatus
                ? progressStatus.charAt(0).toUpperCase() + progressStatus.slice(1)
                : 'Unknown';
            const pretestPill = buildScorePill('Pre-test', stage.pretest_score, stage.pretest_total_questions);
            const assessmentPill = buildScorePill('Assessment', stage.assessment_score, stage.assessment_total_questions);

            return `
            <div class="detail-item">
                <div class="detail-header">
                    <strong>${stage.chapter_title || 'No Chapter'} - ${stage.stage_name}</strong>
                    <span class="score-badge ${stage.status === 'completed' ? 'completed' : 'in-progress'}">
                        ${stage.status === 'completed' ? '✓' : '○'} ${progressStatusLabel}
                    </span>
                </div>
                <div class="detail-meta">
                    Concept: ${stage.science_concept} | Updated: ${new Date(stage.last_updated).toLocaleDateString()}<br>
                    ${pretestPill}${assessmentPill}
                </div>
            </div>
        `;
        }).join('');
    };

    window.currentStudentDetails = studentData;
    populateGameChapterFilter(studentData.game_progress || []);
    renderGameProgressItems(studentData.game_progress || []);

    openModal('studentDetailsModal');
}

function populateGameChapterFilter(gameProgress) {
    const filter = document.getElementById('gameChapterFilter');
    if (!filter) return;

    const chapters = Array.from(new Set((gameProgress || []).map(item => item.chapter_title || 'No Chapter'))).sort();
    filter.innerHTML = '<option value="">All Chapters</option>';

    chapters.forEach(chapter => {
        const option = document.createElement('option');
        option.value = chapter;
        option.textContent = chapter;
        filter.appendChild(option);
    });
}

function filterStudentGameDetails() {
    const selectedChapter = (document.getElementById('gameChapterFilter') || {}).value || '';
    const studentData = window.currentStudentDetails || {};
    const gameProgress = studentData.game_progress || [];
    const gameDetails = document.getElementById('gameDetails');

    const buildScorePill = (label, score, total) => {
        if (score === null || score === undefined || Number(total) <= 0) {
            return `<span class="score-pill none">${label}: N/A</span>`;
        }

        const pct = (Number(score) / Number(total)) * 100;
        const toneClass = pct >= 80 ? 'good' : (pct >= 50 ? 'medium' : 'low');
        return `<span class="score-pill ${toneClass}">${label}: ${score}/${total}</span>`;
    };

    const filtered = gameProgress.filter(stage => {
        if (!selectedChapter) return true;
        return (stage.chapter_title || 'No Chapter') === selectedChapter;
    });

    if (filtered.length === 0) {
        gameDetails.innerHTML = '<p class="no-data">No game progress available for this chapter</p>';
        return;
    }

    gameDetails.innerHTML = filtered.map(stage => {
        const progressStatus = (stage.status || '').replace('_', ' ');
        const progressStatusLabel = progressStatus
            ? progressStatus.charAt(0).toUpperCase() + progressStatus.slice(1)
            : 'Unknown';
        const pretestPill = buildScorePill('Pre-test', stage.pretest_score, stage.pretest_total_questions);
        const assessmentPill = buildScorePill('Assessment', stage.assessment_score, stage.assessment_total_questions);

        return `
        <div class="detail-item">
            <div class="detail-header">
                <strong>${stage.chapter_title || 'No Chapter'} - ${stage.stage_name}</strong>
                <span class="score-badge ${stage.status === 'completed' ? 'completed' : 'in-progress'}">
                    ${stage.status === 'completed' ? '✓' : '○'} ${progressStatusLabel}
                </span>
            </div>
            <div class="detail-meta">
                Concept: ${stage.science_concept} | Updated: ${new Date(stage.last_updated).toLocaleDateString()}<br>
                ${pretestPill}${assessmentPill}
            </div>
        </div>
    `;
    }).join('');
}

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));

    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));

    // Show selected tab content
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    // Add active class to selected tab button
    const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active');
    }
}

// Export students to CSV function
function exportStudentsExcel() {
    // Extract class_id from the API call in viewStudents
    const studentsClassName = document.getElementById('studentsClassName').textContent;
    
    // Get the class_id from current student data
    if (!window.currentStudentData || window.currentStudentData.length === 0) {
        alert('No student data to export');
        return;
    }
    
    // We need to get the class_id - it should be stored from the viewStudents function
    if (!window.currentClassId) {
        alert('Unable to determine class ID');
        return;
    }
    
    // Create a download link and trigger it
    const downloadLink = document.createElement('a');
    downloadLink.href = '../api/export_students_csv.php?class_id=' + window.currentClassId + '&t=' + Date.now();
    downloadLink.download = 'students_export.csv';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function exportStudentsPdf() {
    if (!window.currentClassId) {
        alert('Unable to determine class ID');
        return;
    }

    const downloadLink = document.createElement('a');
    downloadLink.href = '../api/export_class_report_pdf.php?class_id=' + window.currentClassId + '&t=' + Date.now();
    downloadLink.download = 'class_report.pdf';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Initialize default tab
document.addEventListener('DOMContentLoaded', function() {
    // Show active classes tab by default
    document.getElementById('active-classes-tab').classList.add('active');
    document.querySelector('.tab-btn').classList.add('active');

    // Add event listeners to tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
});
</script>
</body>
</html>
