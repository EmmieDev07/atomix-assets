<?php
/**
 * Database Backup - Admin Side
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$message = '';
$backups = [];

// Get list of existing backups
$backupDir = '../backups/';
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filePath = $backupDir . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath)
            ];
        }
    }
    // Sort by modification time (newest first)
    usort($backups, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $db = Database::getInstance()->getConnection();

        // Generate filename with timestamp
        $timestamp = date('Ymd_His');
        $filename = "atomix_db_backup_{$timestamp}.sql";
        $filepath = $backupDir . $filename;

        // Ensure backup directory exists
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Get all tables
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $sql = "-- Atomix Database Backup\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "START TRANSACTION;\n";
        $sql .= "SET time_zone = \"+00:00\";\n\n";

        foreach ($tables as $table) {
            // Get table structure
            $stmt = $db->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            $sql .= "-- Table structure for table `$table`\n";
            $sql .= $createTable['Create Table'] . ";\n\n";

            // Get table data
            $stmt = $db->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $sql .= "-- Dumping data for table `$table`\n";
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } else {
                            $rowValues[] = $db->quote($value);
                        }
                    }
                    $values[] = "(" . implode(', ', $rowValues) . ")";
                }
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        $sql .= "COMMIT;\n";

        // Write to file
        if (file_put_contents($filepath, $sql)) {
            $message = "Backup created successfully: $filename";
            // Refresh backups list
            $backups = array_merge([[
                'filename' => $filename,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath)
            ]], $backups);
        } else {
            $message = "Error: Could not write backup file.";
        }

    } catch (Exception $e) {
        $message = "Error creating backup: " . $e->getMessage();
    }
}

// Handle backup download
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backupDir . $filename;

    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $message = "Error: Backup file not found.";
    }
}

// Handle backup restoration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $filename = basename($_POST['restore_backup']);
    $filepath = $backupDir . $filename;

    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        try {
            $db = Database::getInstance()->getConnection();

            // Read the SQL file
            $sql = file_get_contents($filepath);

            if ($sql === false) {
                throw new Exception("Could not read backup file.");
            }

            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            // Begin transaction
            $db->beginTransaction();

            // Disable foreign key checks temporarily
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');

            // Clear existing data (optional - you might want to make this configurable)
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $db->exec("DROP TABLE IF EXISTS `$table`");
            }

            // Execute each statement
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    $db->exec($statement);
                }
            }

            // Re-enable foreign key checks
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');

            // Commit transaction
            $db->commit();

            $message = "Database restored successfully from: $filename";

        } catch (Exception $e) {
            // Rollback on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = "Error restoring database: " . $e->getMessage();
        }
    } else {
        $message = "Error: Backup file not found.";
    }
}

// Handle backup deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $filename = basename($_POST['delete_backup']);
    $filepath = $backupDir . $filename;

    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        if (unlink($filepath)) {
            $message = "Backup deleted: $filename";
            // Remove from backups list if present
            foreach ($backups as $k => $b) {
                if ($b['filename'] === $filename) {
                    unset($backups[$k]);
                }
            }
            $backups = array_values($backups);
        } else {
            $message = "Error: Could not delete backup file.";
        }
    } else {
        $message = "Error: Backup file not found.";
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Admin</title>
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
        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #1e293b;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .backup-stats {
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
        .backup-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .backup-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }
        .backup-card h2 {
            color: #1e40af;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .backup-card h2 i {
            color: #1e40af;
        }
        .backup-card p {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.7;
            font-size: 1.1rem;
        }
        .backup-grid {
            display: grid;
            gap: 1.5rem;
        }
        .backup-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .backup-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateX(4px);
        }
        .backup-info h4 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .backup-meta {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .backup-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .backup-actions {
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
        .info-list {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            padding: 2rem;
            border: 2px solid #e2e8f0;
            border-left: 4px solid #1e40af;
        }
        .info-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .info-list li {
            color: #475569;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .info-list li:last-child {
            margin-bottom: 0;
        }
        .info-list li i {
            color: #1e40af;
            margin-top: 0.125rem;
            flex-shrink: 0;
        }
        .info-list strong {
            color: #1e293b;
        }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 1.5rem 0.5rem; }
            .sidebar { position: static; width: 100%; flex-direction: row; padding: 1rem; }
            .sidebar h2 { display: none; }
            .backup-stats { grid-template-columns: 1fr; }
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
                <a href="backup.php" class="nav-item active"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="page-title">Database Backup</div>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">Backup</span>
                </div>
            </nav>

            <div class="content-area">
                <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, 'Error') === 0 ? 'error' : 'success'; ?>">
                        <i class="fas <?php echo strpos($message, 'Error') === 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="backup-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($backups); ?></div>
                        <div class="stat-label">Total Backups</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($backups) > 0 ? formatBytes(array_sum(array_column($backups, 'size'))) : '0 B'; ?></div>
                        <div class="stat-label">Total Size</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($backups) > 0 ? date('M j', $backups[0]['modified']) : '--'; ?></div>
                        <div class="stat-label">Last Backup</div>
                    </div>
                </div>

                <!-- Create Backup Card -->
                <div class="backup-card">
                    <h2><i class="fas fa-shield-alt"></i> Create Database Backup</h2>
                    <p>Generate a complete backup of your Atomix database. This includes all tables, data, and relationships. Backups are automatically timestamped and stored securely.</p>
                    <form method="post" style="display: inline;">
                        <button type="submit" name="create_backup" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create New Backup
                        </button>
                    </form>
                </div>

                <!-- Existing Backups Card -->
                <div class="backup-card">
                    <h2><i class="fas fa-history"></i> Backup History</h2>
                    <p>Manage your existing database backups. Download copies for safekeeping or restore previous states when needed.</p>

                    <?php if (empty($backups)): ?>
                        <div class="empty-state">
                            <i class="fas fa-database"></i>
                            <h3>No Backups Found</h3>
                            <p>Create your first backup to get started with data protection.</p>
                        </div>
                    <?php else: ?>
                        <div class="backup-grid">
                            <?php foreach ($backups as $backup): ?>
                                <div class="backup-item">
                                    <div class="backup-info">
                                        <h4><?php echo htmlspecialchars($backup['filename']); ?></h4>
                                        <div class="backup-meta">
                                            <span><i class="fas fa-weight-hanging"></i> <?php echo formatBytes($backup['size']); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y \a\t H:i', $backup['modified']); ?></span>
                                        </div>
                                    </div>
                                    <div class="backup-actions">
                                        <a href="?download=<?php echo urlencode($backup['filename']); ?>" class="btn btn-sm btn-success" title="Download Backup">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This will completely replace the current database with this backup. All current data will be lost. Are you absolutely sure?')">
                                            <input type="hidden" name="restore_backup" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Restore Database">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this backup file permanently?')">
                                            <input type="hidden" name="delete_backup" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Backup">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Information Card -->
                <div class="backup-card">
                    <h2><i class="fas fa-info-circle"></i> Backup Best Practices</h2>
                    <div class="info-list">
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Regular Backups:</strong> Create backups regularly to prevent data loss</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Download Copies:</strong> Keep backup files in multiple secure locations</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Test Restores:</strong> Verify backup integrity by testing restore operations</li>
                            <li><i class="fas fa-exclamation-triangle"></i> <strong>Restore Warning:</strong> Restoring will completely replace current data</li>
                        </ul>
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
</html>
