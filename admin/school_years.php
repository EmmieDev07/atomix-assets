<?php
/**
 * School Year Management - Admin Side
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();
$dbError = null;
$all_school_years = [];
$active_school_years = [];
$inactive_school_years = [];

try {
    $all_school_years = $db->query("SELECT * FROM school_year ORDER BY sy_id DESC")->fetchAll();
    $active_school_years   = array_values(array_filter($all_school_years, function($sy) { return $sy['is_active']; }));
    $inactive_school_years = array_values(array_filter($all_school_years, function($sy) { return !$sy['is_active']; }));
} catch (Exception $e) {
    error_log('School years page error: ' . $e->getMessage());
    $dbError = 'Failed to load school year data.';
}

// Generate school year options starting from 2025
$current_year = date('Y');
$school_year_options = [];
for ($year = 2025; $year <= $current_year + 20; $year++) {  // Extended to +20 for many options
    $school_year_options[] = $year . '-' . ($year + 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Year Management - Admin</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/js/school_years.js" defer></script>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
            min-height: 100vh;
            color: #1e293b;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #1e293b;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .school-year-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            color: #1e40af;
            padding: 2rem;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 2px solid #e0f2fe;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e40af;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
            color: #64748b;
        }

        .school-year-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .school-year-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .school-year-card h2 {
            color: #1e40af;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .school-year-card h2 i {
            color: #1e40af;
        }

        .school-year-grid {
            display: grid;
            gap: 1.5rem;
        }

        .school-year-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .school-year-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateX(4px);
        }

        .school-year-item.active {
            border-color: #22c55e;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .school-year-info h4 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .school-year-meta {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .school-year-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .school-year-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .school-year-status.active {
            background: #dcfce7;
            color: #166534;
        }

        .school-year-status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .school-year-actions {
            display: flex;
            gap: 1rem;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .message {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 5px solid;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            border-left-color: #22c55e;
        }

        .message.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
            opacity: 0.6;
        }

        .empty-state h3 {
            color: #1e40af;
            margin-bottom: 1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 18px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 18px 18px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .modal-actions {
            padding: 1.5rem 2rem;
            background: #f9fafb;
            border-radius: 0 0 18px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Toast Styles */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
            z-index: 1001;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .toast.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.3);
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 1.5rem 0.5rem; }
            .sidebar { position: static; width: 100%; flex-direction: row; padding: 1rem; }
            .sidebar h2 { display: none; }
            .school-year-stats { grid-template-columns: 1fr; }
            .school-year-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="logo">
                <img src="../logoatomix.png" alt="Atomix Logo" style="height: 32px; width: auto;">
                <span>Atomix Admin</span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="system_analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Analytics</span></a>
                <a href="school_years.php" class="nav-item active"><i class="fas fa-calendar-alt"></i><span>School Years</span></a>
                <a href="classes.php" class="nav-item"><i class="fas fa-chalkboard"></i><span>Classes</span></a>
                <a href="students.php" class="nav-item"><i class="fas fa-users"></i><span>Students</span></a>
                <a href="teachers_list.php" class="nav-item"><i class="fas fa-chalkboard-teacher"></i><span>Teachers</span></a>
                <a href="questions.php" class="nav-item"><i class="fas fa-question-circle"></i><span>Questions</span></a>
                <a href="chapters.php" class="nav-item"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>School Year Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">School Years</span>
                </div>
            </nav>

            <div class="content-area">
                <?php if (!empty($dbError)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>
                <div class="page-title">School Year Management</div>

                <!-- Statistics Cards -->
                   
                <!-- School Years List with Tabs -->
                <div class="school-year-card">
                    <h2><i class="fas fa-calendar-alt"></i> School Years</h2>
                    <div style="display:flex; gap:1rem; margin-bottom:1rem; align-items:center;">
                        <button id="tab-active" class="btn btn-sm btn-primary" onclick="showTab('active')">Active (<?php echo count($active_school_years); ?>)</button>
                        <button id="tab-archived" class="btn btn-sm btn-secondary" onclick="showTab('archived')">Archived (<?php echo count($inactive_school_years); ?>)</button>
                    </div>
                    <div class="school-year-grid" id="grid-active" data-tab="active" style="display:block;">
                        <?php if (empty($active_school_years)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Active School Years</h3>
                                <p>Create or activate a school year to make it available.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_school_years as $sy): ?>
                                <div class="school-year-item active" data-id="<?php echo $sy['sy_id']; ?>">
                                    <div class="school-year-info">
                                        <h4><?php echo htmlspecialchars($sy['label']); ?></h4>
                                        <div class="school-year-meta">
                                            <span><i class="fas fa-info-circle"></i> ID: <?php echo $sy['sy_id']; ?></span>
                                            <span class="school-year-status active">
                                                <i class="fas fa-check-circle"></i>
                                                Active
                                            </span>
                                        </div>
                                    </div>
                                    <div class="school-year-actions">
                                        <button class="btn btn-sm btn-info" onclick="editSY(<?php echo $sy['sy_id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="school-year-grid" id="grid-archived" data-tab="archived" style="display:none;">
                        <?php if (empty($inactive_school_years)): ?>
                            <div class="empty-state">
                                <i class="fas fa-archive"></i>
                                <h3>No Archived School Years</h3>
                                <p>Inactive school years will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($inactive_school_years as $sy): ?>
                                <div class="school-year-item" data-id="<?php echo $sy['sy_id']; ?>">
                                    <div class="school-year-info">
                                        <h4><?php echo htmlspecialchars($sy['label']); ?></h4>
                                        <div class="school-year-meta">
                                            <span><i class="fas fa-info-circle"></i> ID: <?php echo $sy['sy_id']; ?></span>
                                            <span class="school-year-status inactive">
                                                <i class="fas fa-times-circle"></i>
                                                Inactive
                                            </span>
                                        </div>
                                    </div>
                                    <div class="school-year-actions">
                                        <button class="btn btn-sm btn-info" onclick="editSY(<?php echo $sy['sy_id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 2rem; text-align: center;">
                        <button class="btn btn-primary" onclick="openModal('addSYModal')">
                            <i class="fas fa-plus"></i> Add New School Year
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Add School Year Modal -->
    <div class="modal" id="addSYModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Add School Year</h3>
                <button class="close-btn" onclick="closeModal('addSYModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addSYForm" onsubmit="submitSY(event)">
                    <div class="form-group">
                        <label for="syLabel">School Year Label *</label>
                        <select id="syLabel" name="label" required>
                            <option value="">Select School Year</option>
                            <?php foreach ($school_year_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="syActive">Status</label>
                        <select id="syActive" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addSYModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save School Year</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit School Year Modal -->
    <div class="modal" id="editSYModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-edit"></i> Edit School Year</h3>
                <button class="close-btn" onclick="closeModal('editSYModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editSYForm" onsubmit="updateSY(event)">
                    <input type="hidden" id="editSyId" name="sy_id">
                    <div class="form-group">
                        <label for="editSyLabel">School Year Label *</label>
                        <select id="editSyLabel" name="label" required>
                            <option value="">Select School Year</option>
                            <?php foreach ($school_year_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editSyActive">Status</label>
                        <select id="editSyActive" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editSYModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update School Year</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="toast" id="toast"><span class="toast-message"></span></div>
    <script>
        // Ensure school_years.js is available (fallback loader)
        if (typeof editSY === 'undefined') {
            var _s = document.createElement('script');
            _s.src = '../assets/js/school_years.js';
            _s.defer = false;
            _s.onload = function(){ console.log('Loaded school_years.js fallback'); };
            document.body.appendChild(_s);
        }
        // Tab switching for Active / Archived lists
        function showTab(tab) {
            document.getElementById('grid-active').style.display = (tab === 'active') ? 'block' : 'none';
            document.getElementById('grid-archived').style.display = (tab === 'archived') ? 'block' : 'none';
            // Update button styles
            document.getElementById('tab-active').classList.toggle('btn-primary', tab === 'active');
            document.getElementById('tab-active').classList.toggle('btn-secondary', tab !== 'active');
            document.getElementById('tab-archived').classList.toggle('btn-primary', tab === 'archived');
            document.getElementById('tab-archived').classList.toggle('btn-secondary', tab !== 'archived');
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function confirmAdminLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Log Out?',
            text: 'Are you sure you want to log out?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Cancel'
        }).then(r => { if (r.isConfirmed) window.location.href = 'logout.php'; });
    }
    </script>
</body>
</html>
