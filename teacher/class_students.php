<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkTeacherAuth();
$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_classes');

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$dbError = null;

try {
// Get all classes for filter dropdown (only for initial page load)
if (!$isAjax) {
    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    $stmt = $db->prepare("SELECT c.class_id, c.class_name, sy.label as school_year FROM classes c JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1 WHERE c.teacher_id = ? ORDER BY c.class_name");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll();
}

// Get filter parameters (including pagination)
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 10;
$offset = ($page - 1) * $per_page;

// Prepare base filters
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$baseWhere = "c.teacher_id = ?";
$baseParams = [$teacher_id];

if ($class_id > 0) {
    $ownerStmt = $db->prepare("SELECT teacher_id FROM classes WHERE class_id = ? LIMIT 1");
    $ownerStmt->execute([$class_id]);
    $owner = $ownerStmt->fetch();
    if ($owner && intval($owner['teacher_id']) === intval($teacher_id)) {
        $baseWhere .= " AND cs.class_id = ?";
        $baseParams[] = $class_id;
    } else {
        $class_id = 0; // ignore invalid class filter
    }
}

if (!empty($search)) {
    $baseWhere .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? )";
    $searchParam = "%$search%";
    $baseParams = array_merge($baseParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Aggregated counts for stats and total
$countQuery = "
    SELECT
        COUNT(DISTINCT s.student_id) AS total_students,
        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS active_students,
        SUM(CASE WHEN LOWER(s.gender) = 'male' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN LOWER(s.gender) = 'female' THEN 1 ELSE 0 END) AS female_count,
        COUNT(DISTINCT c.class_id) AS classes_count
    FROM students s
    JOIN users u ON s.user_id = u.user_id AND u.status = 'active'
    JOIN class_students cs ON s.student_id = cs.student_id
    JOIN classes c ON cs.class_id = c.class_id
    JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
    WHERE $baseWhere
";

$countStmt = $db->prepare($countQuery);
$countStmt->execute($baseParams);
$counts = $countStmt->fetch();

$total_students_all = (int)($counts['total_students'] ?? 0);
$active_students = (int)($counts['active_students'] ?? 0);
$male_count = (int)($counts['male_count'] ?? 0);
$female_count = (int)($counts['female_count'] ?? 0);
$classes_count = (int)($counts['classes_count'] ?? 0);

$total_pages = $per_page > 0 ? (int)ceil($total_students_all / $per_page) : 1;

// Fetch paginated students
$query = "
    SELECT s.*, u.email, u.username, u.status, u.created_at,
           c.class_name, c.class_id, sy.label as school_year
    FROM students s
    JOIN users u ON s.user_id = u.user_id AND u.status = 'active'
    JOIN class_students cs ON s.student_id = cs.student_id
    JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ?
    JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
    WHERE 1=1
";

$queryParams = [$teacher_id];
if ($class_id > 0) {
    $query .= " AND cs.class_id = ?";
    $queryParams[] = $class_id;
}
if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $queryParams = array_merge($queryParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$query .= " ORDER BY s.last_name, s.first_name ";
$query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $db->prepare($query);
$stmt->execute($queryParams);
$students = $stmt->fetchAll();

// For template compatibility
$total_students = $total_students_all;

// If AJAX request, return JSON data with pagination
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'students' => $students,
            'stats' => [
                'total_students' => $total_students_all,
                'active_students' => $active_students,
                'classes_count' => $classes_count,
                'male_count' => $male_count,
                'female_count' => $female_count
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_students_all,
                'total_pages' => $total_pages
            ]
        ]
    ]);
    exit;
}
} catch (PDOException $e) {
    error_log('class_students.php DB error: ' . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again later.']);
        exit;
    }
    $dbError = 'A database error occurred. Please try again later.';
    $classes = []; $students = []; $total_students_all = 0;
    $active_students = 0; $male_count = 0; $female_count = 0;
    $classes_count = 0; $total_students = 0; $total_pages = 1;
    $page = 1; $per_page = 10;
}
?>
<?php
// Calculate unique classes count
$students_with_classes = array_filter($students, function($s) { return !empty($s['class_id']); });
$class_ids = array_column($students_with_classes, 'class_id');
$classes_count = count(array_unique($class_ids));

// Calculate gender counts
$male_count = count(array_filter($students, function($s) { return strtolower($s['gender'] ?? '') === 'male'; }));
$female_count = count(array_filter($students, function($s) { return strtolower($s['gender'] ?? '') === 'female'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Students - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Modern Class Students Styling */
        .class-selection-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .class-selection-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-color: #3b82f6;
        }

        .class-select-wrapper {
            position: relative;
            max-width: 400px;
        }

        .class-select-wrapper select {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            appearance: none;
            cursor: pointer;
        }

        .class-select-wrapper select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .class-select-wrapper::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr) 140px;
            gap: 1rem;
            align-items: end;
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-action-btn {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            border: none;
            text-decoration: none;
            cursor: pointer;
            min-width: 0;
        }

        .filter-action-btn.add {
            background: #10b981;
            color: #ffffff;
        }

        .filter-action-btn.upload {
            background: #f59e0b;
            color: #ffffff;
        }

        .filter-action-btn.clear {
            background: #f1f5f9;
            color: #64748b;
        }

        .class-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-color: #10b981;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.25rem 0;
        }

        .stat-content p {
            color: #64748b;
            margin: 0;
            font-size: 0.875rem;
        }

        .students-table-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 2px solid #e2e8f0;
        }

        .table-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table thead th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .modern-table tbody td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
        }

        .modern-table tbody tr:hover {
            background: #f8fafc;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .student-info-cell {
            display: flex;
            align-items: center;
        }

        .student-details h4 {
            margin: 0 0 0.25rem 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }

        .student-details p {
            margin: 0;
            font-size: 0.75rem;
            color: #64748b;
        }

        .gender-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .gender-male {
            background: #dbeafe;
            color: #1e40af;
        }

        .gender-female {
            background: #fce7f3;
            color: #be185d;
        }

        .gender-other {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .empty-state-modern {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state-modern i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state-modern h3 {
            margin: 0 0 0.5rem 0;
            color: #374151;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .class-stats {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                width: 100%;
            }

            .filter-action-btn {
                width: 100%;
            }

            .table-header {
                padding: 1rem;
            }

            .modern-table thead th,
            .modern-table tbody td {
                padding: 0.75rem 1rem;
            }

            .student-info-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        /* Utility classes for JavaScript */
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .ml-2 { margin-left: 0.5rem; }
        .ml-4 { margin-left: 1rem; }
        .text-xs { font-size: 0.75rem; line-height: 1rem; }
        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .whitespace-nowrap { white-space: nowrap; }
        .capitalize { text-transform: capitalize; }
        .rounded-full { border-radius: 9999px; }
        .bg-green-100 { background-color: #dcfce7; }
        .bg-red-100 { background-color: #fee2e2; }
        .text-green-800 { color: #166534; }
        .text-red-800 { color: #dc2626; }
        .text-gray-900 { color: #111827; }
        .text-gray-500 { color: #6b7280; }
        .text-gray-400 { color: #9ca3af; }
        .text-blue-500 { color: #3b82f6; }
        .text-pink-500 { color: #ec4899; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .flex-shrink-0 { flex-shrink: 0; }
        .h-10 { height: 2.5rem; }
        .w-10 { width: 2.5rem; }
        .bg-gradient-to-r { background-image: linear-gradient(to right, var(--tw-gradient-stops)); }
        .from-blue-500 { --tw-gradient-from: #3b82f6; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(59, 130, 246, 0)); }
        .to-purple-600 { --tw-gradient-to: #9333ea; }
        .justify-center { justify-content: center; }
        .text-white { color: #ffffff; }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            visibility: visible;
            opacity: 1;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #374151;
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
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            z-index: 10000;
            display: none;
        }

        /* Upload Modal Styles */
        .modal-lg {
            max-width: min(900px, 96vw) !important;
            width: min(900px, 96vw) !important;
            min-width: 0 !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
        }

        .upload-step {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .step-header h4 {
            margin: 0;
            color: #1e293b;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .step-content p {
            margin: 0 0 1rem 0;
            color: #64748b;
        }

        .btn-outline-primary {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-outline-primary:hover {
            background: #3b82f6;
            color: white;
        }

        .file-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .file-upload-content i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .file-upload-content p {
            margin: 0 0 0.5rem 0;
            color: #64748b;
            font-size: 1rem;
        }

        .file-upload-link {
            color: #3b82f6;
            text-decoration: underline;
            cursor: pointer;
        }

        .file-upload-link:hover {
            color: #2563eb;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            color: #166534;
        }

        .file-info i {
            color: #16a34a;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .btn-link {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            padding: 0;
        }

        .btn-link:hover {
            color: #b91c1c;
        }

        .upload-requirements {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
        }

        .upload-requirements h5 {
            margin: 0 0 0.5rem 0;
            color: #92400e;
            font-size: 1rem;
            font-weight: 600;
        }

        .upload-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .upload-requirements li {
            color: #92400e;
            margin-bottom: 0.25rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        .swal-on-top { z-index: 99999 !important; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1><i class="fas fa-users"></i> Class Students</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <a href="classes.php">Class Management</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Class Students</span>
            </nav>
            <?php if (!empty($dbError)): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>

            <div class="content-area">
                <!-- Filters Card -->
                <div class="class-selection-card">
                    <h2 style="margin: 0 0 1.5rem 0; color: #1e293b;"><i class="fas fa-filter"></i> Filter Students</h2>
                    <div style="margin: 0;">
                        <div class="filters-grid">
                            <div class="class-select-wrapper">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151;">Filter by Class</label>
                                <select id="class-filter">
                                    <option value="0">All Classes</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['class_id']; ?>" <?php if ($class_id == $c['class_id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($c['class_name'] . ' (' . $c['school_year'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151;">Search Students</label>
                                <div style="position: relative;">
                                    <input type="text" id="live-search" placeholder="Search by name, email, or username..." style="width: 100%; padding: 1rem 3rem 1rem 1.5rem; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; transition: all 0.3s ease;" value="<?php echo htmlspecialchars($search); ?>">
                                    <i class="fas fa-search" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;"></i>
                                    <div id="search-spinner" style="position: absolute; right: 2.5rem; top: 50%; transform: translateY(-50%); display: none;">
                                        <i class="fas fa-spinner fa-spin" style="color: #64748b; font-size: 0.875rem;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="filter-actions">
                                <a href="#" id="clear-filters" class="filter-action-btn clear">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#374151;">Per Page</label>
                                <select id="per-page-select" style="width:100%; padding:0.75rem 1rem; border:2px solid #e2e8f0; border-radius:12px;">
                                    <option value="10" <?php if ($per_page == 10) echo 'selected'; ?>>10</option>
                                    <option value="20" <?php if ($per_page == 20) echo 'selected'; ?>>20</option>
                                    <option value="50" <?php if ($per_page == 50) echo 'selected'; ?>>50</option>
                                    <option value="100" <?php if ($per_page == 100) echo 'selected'; ?>>100</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="class-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-students"><?php echo $total_students; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="active-students"><?php echo $active_students; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-classes"><?php echo $classes_count; ?></h3>
                            <p>Classes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-venus-mars"></i>
                        </div>
                        <div class="stat-content">
                            <h3><span id="male-count"><?php echo $male_count; ?></span>/<span id="female-count"><?php echo $female_count; ?></span></h3>
                            <p>Male/Female</p>
                        </div>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="students-table-card">
                    <div class="table-header">
                        <i class="fas fa-list-ul"></i>
                        <h2>Student List <?php if ($class_id > 0): ?>(Filtered by Class)<?php elseif (!empty($search)): ?>(Search Results)<?php endif; ?></h2>
                        <div style="margin-left: auto; color: #e2e8f0; font-size: 0.875rem;">
                            <?php echo $total_students; ?> student<?php echo $total_students !== 1 ? 's' : ''; ?> found
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table id="students-table" class="modern-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Gender</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state-modern">
                                                <i class="fas fa-users"></i>
                                                <h3>No Students Found</h3>
                                                <p><?php if (!empty($search) || $class_id > 0): ?>No students match your current filters.<?php else: ?>No students have been added yet.<?php endif; ?></p>
                                                <?php if (!empty($search) || $class_id > 0): ?>
                                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="color: #3b82f6; text-decoration: none; font-weight: 500;">Clear filters to see all students</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $stud): ?>
                                        <tr>
                                            <td>
                                                <div class="student-info-cell">
                                                    <div class="student-avatar">
                                                        <?php echo strtoupper(substr($stud['first_name'], 0, 1) . substr($stud['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="student-details">
                                                        <h4><?php echo htmlspecialchars($stud['first_name'] . ' ' . $stud['last_name']); ?></h4>
                                                        <p>@<?php echo htmlspecialchars($stud['username']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($stud['class_name'])): ?>
                                                    <span style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">
                                                        <?php echo htmlspecialchars($stud['class_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #64748b; font-style: italic;">No Class</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="gender-badge gender-<?php echo strtolower($stud['gender'] ?? 'other'); ?>">
                                                    <?php echo htmlspecialchars($stud['gender'] ?? 'Other'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($stud['email']); ?></td>
                                            <td>
                                                <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; <?php echo $stud['status'] === 'active' ? 'background: #dcfce7; color: #166534;' : 'background: #fee2e2; color: #dc2626;'; ?>">
                                                    <?php echo ucfirst($stud['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($stud['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px;">
                        <div id="pagination" style="display:flex; gap:8px; align-items:center;"></div>
                        <div id="results-summary" style="color:#475569; font-weight:600;"></div>
                    </div>
                </div>
            </div>

            <div class="toast" id="toast" style="display:none;"><span class="toast-message"></span></div>
        </main>
    </div>

<script>
let debounceTimer;
let currentPage = 1;
let perPage = 10;

function fetchStudents(page = 1) {
    currentPage = page;
    const params = new URLSearchParams();
    const q = document.getElementById('live-search').value.trim();
    const cid = document.getElementById('class-filter').value;
    if (q) params.append('search', q);
    if (cid && cid !== '0') params.append('class_id', cid);
    params.append('page', currentPage);
    params.append('per_page', perPage);

    const spinner = document.getElementById('search-spinner');
    if (spinner) spinner.style.display = 'inline-block';

    fetch(window.location.pathname + '?' + params.toString(), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        renderTable(data.data.students);
        renderStats(data.data.stats);
        renderPagination(data.data.pagination);
    })
    .catch(err => console.error(err))
    .finally(() => { if (spinner) spinner.style.display = 'none'; });
}

function renderTable(students) {
    const tbody = document.getElementById('students-table').querySelector('tbody');
    tbody.innerHTML = '';
    if (!students || students.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state-modern"><i class="fas fa-users"></i><h3>No Students Found</h3><p>No students match your current filters.</p></div></td></tr>`;
        return;
    }

    students.forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="student-info-cell">
                    <div class="student-avatar">${(s.first_name||'').charAt(0)}${(s.last_name||'').charAt(0)}</div>
                    <div class="student-details"><h4>${s.first_name} ${s.last_name}</h4><p>@${s.username}</p></div>
                </div>
            </td>
            <td>${s.class_name ? `<span class="class-badge">${s.class_name}</span>` : '<span class="text-muted">No Class</span>'}</td>
            <td><span class="gender-badge gender-${(s.gender||'other').toLowerCase()}">${s.gender||'Other'}</span></td>
            <td>${s.email}</td>
            <td>${s.status === 'active' ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'}</td>
            <td>${new Date(s.created_at).toLocaleDateString()}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderStats(stats) {
    document.getElementById('total-students').textContent = stats.total_students;
    document.getElementById('active-students').textContent = stats.active_students;
    document.getElementById('total-classes').textContent = stats.classes_count;
    document.getElementById('male-count').textContent = stats.male_count;
    document.getElementById('female-count').textContent = stats.female_count;
}

function renderPagination(pagination) {
    const paginationDiv = document.getElementById('pagination');
    const resultsSummary = document.getElementById('results-summary');
    if (!pagination) { paginationDiv.innerHTML = ''; resultsSummary.textContent = ''; return; }
    const { page, per_page, total, total_pages } = pagination;
    // Build buttons
    const maxDisplayAll = 50;
    const windowSize = (total_pages <= maxDisplayAll) ? total_pages : 25;
    let start = Math.max(1, page - Math.floor(windowSize/2));
    let end = Math.min(total_pages, start + windowSize - 1);
    if (end - start < windowSize - 1) start = Math.max(1, end - windowSize + 1);

    const parts = [];
    function pageBtn(label, p, active=false, extraClass=''){
        const aria = `aria-label="Go to page ${p}"`;
        const tab = active ? 'tabindex="0"' : 'tabindex="0"';
        return `<button class="page-btn ${active? 'active':''} ${extraClass}" data-page="${p}" ${aria} ${tab}>${label}</button>`;
    }

    // prev
    parts.push(pageBtn('<', Math.max(1, page-1), false, 'prev-btn'));
    if (start > 1) { parts.push(pageBtn(1,1)); if (start > 2) parts.push('<span class="ellipsis">...</span>'); }
    for (let p=start; p<=end; p++) parts.push(pageBtn(p, p, p===page));
    if (end < total_pages) { if (end < total_pages-1) parts.push('<span class="ellipsis">...</span>'); parts.push(pageBtn(total_pages, total_pages)); }
    // next
    parts.push(pageBtn('>', Math.min(total_pages, page+1), false, 'next-btn'));

    paginationDiv.setAttribute('role','navigation');
    paginationDiv.setAttribute('aria-label','Pagination Navigation');
    paginationDiv.innerHTML = parts.join(' ');

    // Summary
    const startItem = (page-1)*per_page + 1;
    const endItem = Math.min(total, page*per_page);
    resultsSummary.textContent = `Results: ${startItem} - ${endItem} of ${total}`;
    // small accessible label
    resultsSummary.setAttribute('aria-live','polite');
}

function debounce(fn, delay){ clearTimeout(debounceTimer); debounceTimer = setTimeout(fn, delay); }

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('live-search');
    const classFilter = document.getElementById('class-filter');
    const perPageSelect = document.getElementById('per-page-select');

    perPage = parseInt(perPageSelect ? perPageSelect.value : 10) || 10;

    // Event wiring
    searchInput.addEventListener('input', function(){ debounce(()=>fetchStudents(1), 300); });
    classFilter.addEventListener('change', ()=>fetchStudents(1));
    document.getElementById('clear-filters').addEventListener('click', function(e){ e.preventDefault(); searchInput.value=''; classFilter.value='0'; fetchStudents(1); });
    if (perPageSelect) perPageSelect.addEventListener('change', function(){ perPage = parseInt(this.value) || perPage; fetchStudents(1); });
    document.getElementById('pagination').addEventListener('click', function(e){ const btn = e.target.closest('.page-btn'); if (!btn) return; const p = parseInt(btn.getAttribute('data-page')) || 1; fetchStudents(p); });
    // Initial load
    fetchStudents(1);
});

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

function showToast(message) {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.querySelector('.toast-message').textContent = message;
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3500);
    }
}

</script>

</body>
</html>
