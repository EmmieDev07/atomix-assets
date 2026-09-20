<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Include Composer Autoloader for Smalot PDFParser
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

checkTeacherAuth();

$db = Database::getInstance()->getConnection();
$message = '';
$messageType = '';

// Target Folder Setup (relative to teacher/ directory)
$uploadDir = '../uploads/modules/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $chapterId = (int)($_POST['chapter_id'] ?? 0);

    // --- 1. UPLOAD OR REPLACE PDF WITH TEXT EXTRACTION ---
    if (($action === 'upload' || $action === 'replace') && $chapterId > 0) {
        if (isset($_FILES['module_pdf']) && $_FILES['module_pdf']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['module_pdf'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($fileExt === 'pdf') {
                // Delete old PDF file from disk if present
                $stmt = $db->prepare("SELECT pdf_file_path FROM chapters WHERE chapter_id = ?");
                $stmt->execute([$chapterId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing && !empty($existing['pdf_file_path'])) {
                    $oldPath = '../' . $existing['pdf_file_path'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                // Save PDF to uploads/modules/
                $newFileName    = 'module_ch' . $chapterId . '_' . time() . '.pdf';
                $targetFilePath = $uploadDir . $newFileName;
                $dbRelativePath = 'uploads/modules/' . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                    $extractedText = '';

                    // Extract text using Smalot PDFParser
                    try {
                        if (class_exists('\Smalot\PdfParser\Parser')) {
                            $parser = new \Smalot\PdfParser\Parser();
                            $pdf = $parser->parseFile($targetFilePath);
                            $extractedText = $pdf->getText();

                            // Clean excessive whitespace & keep readable ASCII characters
                            $extractedText = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $extractedText);
                            $extractedText = trim(preg_replace('/\s+/', ' ', $extractedText));
                        } else {
                            $message = 'Smalot PDFParser is not loaded. Ensure vendor/autoload.php exists.';
                            $messageType = 'danger';
                        }
                    } catch (Exception $e) {
                        $message = 'PDF Parsing Error: ' . $e->getMessage();
                        $messageType = 'danger';
                    }

                    // Check extracted text validity
                    if (empty($message) && strlen($extractedText) < 50) {
                        $message = 'Could not extract readable text. The PDF might be scanned/image-based or password-protected.';
                        $messageType = 'danger';
                    } elseif (empty($message)) {
                        // Update Database with both pdf_file_path AND lesson_content
                        try {
                            $stmt = $db->prepare("UPDATE chapters SET pdf_file_path = ?, lesson_content = ? WHERE chapter_id = ?");
                            $stmt->execute([$dbRelativePath, $extractedText, $chapterId]);

                            $message = 'Module PDF uploaded & ' . number_format(strlen($extractedText)) . ' characters extracted into lesson content!';
                            $messageType = 'success';
                        } catch (PDOException $e) {
                            $message = 'Database update error: ' . $e->getMessage();
                            $messageType = 'danger';
                        }
                    }
                } else {
                    $message = 'Could not save PDF file to disk.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'File must be a .pdf document.';
                $messageType = 'danger';
            }
        } else {
            $message = 'PDF upload failed or no file selected.';
            $messageType = 'danger';
        }
    }

    // --- 2. REMOVE PDF AND CLEAR LESSON CONTENT ---
    elseif ($action === 'remove' && $chapterId > 0) {
        $stmt = $db->prepare("SELECT pdf_file_path FROM chapters WHERE chapter_id = ?");
        $stmt->execute([$chapterId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($module && !empty($module['pdf_file_path'])) {
            $fullPath = '../' . $module['pdf_file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $stmt = $db->prepare("UPDATE chapters SET pdf_file_path = NULL, lesson_content = NULL WHERE chapter_id = ?");
        $stmt->execute([$chapterId]);

        $message = "PDF file and extracted text removed from chapter!";
        $messageType = "success";
    }
}

// Fetch all chapters
$chapters = $db->query("SELECT chapter_id, chapter_title, chapter_order, pdf_file_path, CHAR_LENGTH(lesson_content) AS text_len FROM chapters ORDER BY chapter_order ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage PDF Modules - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Modern Grid Layout */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .module-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 22px;
            position: relative;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .module-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .pdf-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .pdf-icon-box.has-pdf { background: #fef2f2; color: #ef4444; }
        .pdf-icon-box.no-pdf { background: var(--gray-100); color: var(--gray-500); }

        .module-title {
            font-weight: 700;
            color: var(--dark-color);
            font-size: 1rem;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 8px;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }

        /* Modern Dot Menu (⋮) */
        .menu-btn {
            background: transparent;
            border: none;
            font-size: 18px;
            color: var(--gray-500);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .menu-btn:hover {
            background: var(--gray-100);
            color: var(--dark-color);
        }

        .dropdown-menu {
            position: absolute;
            top: 46px;
            right: 20px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            display: none;
            flex-direction: column;
            width: 150px;
            z-index: 10;
            overflow: hidden;
        }

        .dropdown-item {
            padding: 10px 14px;
            font-size: 13px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            border: none;
            background: transparent;
            text-align: left;
            width: 100%;
            transition: background 0.15s;
        }

        .dropdown-item:hover { background: var(--gray-100); }
        .dropdown-item.danger { color: #dc2626; }
        .dropdown-item.danger:hover { background: #fef2f2; }

        /* Buttons & Forms */
        .btn-full { width: 100%; justify-content: center; }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal-body {
            background: var(--white);
            width: 100%;
            max-width: 850px;
            border-radius: var(--radius-lg);
            padding: 24px;
            height: 85vh;
            display: flex;
            flex-direction: column;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            margin-top: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Module PDF Manager</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>

            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current"><i class="fas fa-file-pdf"></i> Manage Modules</span>
            </nav>

            <div class="content-wrapper">
                <div class="recent-section" style="margin-bottom: 24px;">
                    <h2>
                        <i class="fas fa-folder-open"></i> Pre-created Course Chapters
                    </h2>
                    <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 20px;">
                        Upload PDF documents to existing chapter slots. Text will automatically be parsed into <code>lesson_content</code> for AI question generation.
                    </p>

                    <!-- MODULE CARDS GRID -->
                    <div class="modules-grid">
                        <?php if (empty($chapters)): ?>
                            <p style="color: var(--gray-500); grid-column: 1/-1; text-align: center; padding: 40px 0;">No chapters configured in database yet.</p>
                        <?php else: ?>
                            <?php foreach ($chapters as $ch): ?>
                                <?php $hasPdf = !empty($ch['pdf_file_path']); ?>
                                <div class="module-card">
                                    <div>
                                        <div class="card-top">
                                            <div class="pdf-icon-box <?php echo $hasPdf ? 'has-pdf' : 'no-pdf'; ?>">
                                                <i class="fas <?php echo $hasPdf ? 'fa-file-pdf' : 'fa-file-circle-plus'; ?>"></i>
                                            </div>

                                            <?php if ($hasPdf): ?>
                                                <button class="menu-btn" onclick="toggleDropdown(event, <?php echo $ch['chapter_id']; ?>)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                
                                                <!-- 3-Dot Dropdown Menu -->
                                                <div id="dropdown-<?php echo $ch['chapter_id']; ?>" class="dropdown-menu">
                                                    <button class="dropdown-item" onclick="openUploadModal(<?php echo $ch['chapter_id']; ?>, '<?php echo htmlspecialchars($ch['chapter_title'], ENT_QUOTES); ?>', true)">
                                                        <i class="fas fa-sync-alt" style="color:#0284c7;"></i> Replace PDF
                                                    </button>
                                                    <form method="POST" onsubmit="return confirmRemove(event)">
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="chapter_id" value="<?php echo $ch['chapter_id']; ?>">
                                                        <button type="submit" class="dropdown-item danger">
                                                            <i class="fas fa-trash-alt"></i> Remove PDF
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <span class="badge <?php echo $hasPdf ? 'badge-success' : 'badge-warning'; ?>">
                                            Chapter <?php echo $ch['chapter_order']; ?> • <?php echo $hasPdf ? number_format($ch['text_len']) . ' chars extracted' : 'Missing PDF'; ?>
                                        </span>
                                        <div class="module-title"><?php echo htmlspecialchars($ch['chapter_title']); ?></div>
                                    </div>

                                    <div style="margin-top: 18px;">
                                        <?php if ($hasPdf): ?>
                                            <button class="btn btn-secondary btn-full" onclick="viewPDF('../<?php echo $ch['pdf_file_path']; ?>', '<?php echo htmlspecialchars($ch['chapter_title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-eye"></i> View PDF
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-full" onclick="openUploadModal(<?php echo $ch['chapter_id']; ?>, '<?php echo htmlspecialchars($ch['chapter_title'], ENT_QUOTES); ?>', false)">
                                                <i class="fas fa-cloud-upload-alt"></i> Upload PDF
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- UPLOAD / REPLACE PDF MODAL -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-body" style="height: auto; max-width: 480px;">
            <h3 id="modal_heading" style="margin-top:0; color: var(--dark-color); font-size: 18px;">
                <i class="fas fa-cloud-upload-alt" style="color: var(--primary-color);"></i> Upload PDF File
            </h3>
            <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">
                Target Chapter: <strong id="modal_chapter_title" style="color: var(--dark-color);"></strong>
            </p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" id="modal_action" name="action" value="upload">
                <input type="hidden" id="modal_chapter_id" name="chapter_id">

                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-weight:600; font-size:13px; margin-bottom:8px; color: var(--dark-color);">Select PDF Document:</label>
                    <input type="file" name="module_pdf" accept=".pdf" style="width:100%; padding:10px; border:1px solid var(--gray-200); border-radius:8px; box-sizing:border-box;" required>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Process & Extract Text</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF PREVIEW MODAL -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 id="pdf_viewer_title" style="margin:0; color: var(--dark-color); font-size: 18px;">PDF Preview</h3>
                <button class="btn btn-secondary btn-sm" onclick="closeModal('viewModal')"><i class="fas fa-times"></i> Close</button>
            </div>
            <iframe id="pdfFrame" src=""></iframe>
        </div>
    </div>

    <script>
    <?php if (!empty($message)): ?>
        Swal.fire({
            icon: '<?php echo $messageType === "success" ? "success" : "error"; ?>',
            title: '<?php echo $messageType === "success" ? "Success!" : "Notice"; ?>',
            text: '<?php echo $message; ?>',
            confirmColor: '#10b981'
        });
    <?php endif; ?>

    function toggleDropdown(e, id) {
        e.stopPropagation();
        document.querySelectorAll('.dropdown-menu').forEach(el => el.style.display = 'none');
        const menu = document.getElementById('dropdown-' + id);
        menu.style.display = 'flex';
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu').forEach(el => el.style.display = 'none');
    });

    function openUploadModal(id, title, isReplace = false) {
        document.getElementById('modal_chapter_id').value = id;
        document.getElementById('modal_chapter_title').innerText = title;
        document.getElementById('modal_action').value = isReplace ? 'replace' : 'upload';
        document.getElementById('modal_heading').innerHTML = isReplace ? 
            '<i class="fas fa-sync-alt" style="color:#0284c7;"></i> Replace PDF File' : 
            '<i class="fas fa-cloud-upload-alt" style="color:var(--primary-color);"></i> Upload PDF Module';
        
        document.getElementById('uploadModal').style.display = 'flex';
    }

    function viewPDF(filePath, title) {
        document.getElementById('pdf_viewer_title').innerText = title;
        document.getElementById('pdfFrame').src = filePath;
        document.getElementById('viewModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        if (modalId === 'viewModal') {
            document.getElementById('pdfFrame').src = '';
        }
    }

    function confirmRemove(e) {
        e.preventDefault();
        const form = e.target;
        Swal.fire({
            title: 'Remove PDF from this chapter?',
            text: "The uploaded PDF file and its extracted lesson text will be cleared.",
            icon: 'warning',
            showCancelButton: true,
            confirmColor: '#ef4444',
            cancelColor: '#64748b',
            confirmButtonText: 'Yes, remove file'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }
    </script>
</body>
</html>