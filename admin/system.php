<?php
/**
 * System Information - Admin Side
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();

// Get system information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => $db->query('SELECT VERSION()')->fetchColumn(),
    'database_name' => DB_NAME,
    'database_size' => 'Calculating...'
];

// Calculate database size
try {
    $stmt = $db->query("
        SELECT
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables
        WHERE table_schema = '" . DB_NAME . "'
    ");
    $size = $stmt->fetchColumn();
    $systemInfo['database_size'] = $size ? $size . ' MB' : '0 MB';
} catch (Exception $e) {
    $systemInfo['database_size'] = 'Unable to calculate';
}

// Get table counts
$tableCounts = [];
$tables = ['users', 'teachers', 'students', 'classes', 'class_students', 'chapters', 'questions', 'quizzes', 'school_year'];
foreach ($tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $tableCounts[$table] = $count;
    } catch (Exception $e) {
        $tableCounts[$table] = 'Error';
    }
}

// Get recent activity (last 10 logins)
$recentLogins = $db->query("
    SELECT u.username, u.role, u.last_login
    FROM users u
    WHERE u.last_login IS NOT NULL
    ORDER BY u.last_login DESC
    LIMIT 10
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Information - Admin</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
            min-height: 100vh;
            color: #1e293b;
        }
        .info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }
        .info-card h2 {
            color: #1e40af;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .info-card h2 i {
            color: #1e40af;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .info-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            border-left: 4px solid #1e40af;
            transition: all 0.3s ease;
        }
        .info-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateX(4px);
        }
        .info-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        .info-value {
            color: #64748b;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            background: #f1f5f9;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .table-counts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .count-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .count-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateY(-2px);
        }
        .count-item strong {
            color: #1e40af;
            font-size: 1.2rem;
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
                <a href="school_years.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>School Years</span></a>
                <a href="classes.php" class="nav-item"><i class="fas fa-chalkboard"></i><span>Classes</span></a>
                <a href="students.php" class="nav-item"><i class="fas fa-users"></i><span>Students</span></a>
                <a href="teachers_list.php" class="nav-item"><i class="fas fa-chalkboard-teacher"></i><span>Teachers</span></a>
                <a href="questions.php" class="nav-item"><i class="fas fa-question-circle"></i><span>Questions</span></a>
                <a href="chapters.php" class="nav-item"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item active"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>System Information</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">System</span>
                </div>
            </nav>

            <div class="content-area">
                <div class="info-card">
                    <h2><i class="fas fa-server"></i> System Overview</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">PHP Version</div>
                            <div class="info-value"><?php echo $systemInfo['php_version']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Web Server</div>
                            <div class="info-value"><?php echo $systemInfo['server_software']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Database</div>
                            <div class="info-value"><?php echo $systemInfo['database_name']; ?> (<?php echo $systemInfo['database_version']; ?>)</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Database Size</div>
                            <div class="info-value"><?php echo $systemInfo['database_size']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h2><i class="fas fa-table"></i> Database Table Counts</h2>
                    <div class="table-counts">
                        <?php foreach ($tableCounts as $table => $count): ?>
                            <div class="count-item">
                                <span><?php echo ucfirst(str_replace('_', ' ', $table)); ?>:</span>
                                <strong><?php echo $count; ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h2><i class="fas fa-clock"></i> Recent User Activity</h2>
                    <?php if (empty($recentLogins)): ?>
                        <p>No recent login activity found.</p>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Last Login</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLogins as $login): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($login['username']); ?></td>
                                            <td><span class="badge badge-<?php echo $login['role']; ?>"><?php echo ucfirst($login['role']); ?></span></td>
                                            <td><?php echo $login['last_login'] ? date('M j, Y H:i', strtotime($login['last_login'])) : 'Never'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h2><i class="fas fa-info-circle"></i> System Health</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Session Status</div>
                            <div class="info-value"><?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Max Upload Size</div>
                            <div class="info-value"><?php echo ini_get('upload_max_filesize'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Memory Limit</div>
                            <div class="info-value"><?php echo ini_get('memory_limit'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Max Execution Time</div>
                            <div class="info-value"><?php echo ini_get('max_execution_time'); ?> seconds</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

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
</html></content>
<parameter name="filePath">c:\xampp\htdocs\finalweb\admin\system.php