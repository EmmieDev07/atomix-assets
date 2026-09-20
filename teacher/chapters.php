<?php
session_start();
/**
 * Chapter Management Page - Teacher Side
 * Create, Edit, Delete Chapters, Lessons, and PDF Modules
 */

require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Include Composer Autoloader for Smalot PDFParser
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

checkTeacherAuth();

$db = Database::getInstance()->getConnection();

// --- DATABASE ACCESS TABLE HELPERS ---
function ensureClassChapterAccessTable($db) {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS class_chapter_access (
            class_id INT NOT NULL,
            chapter_id INT NOT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, chapter_id),
            KEY idx_class_chapter_access_chapter (chapter_id),
            CONSTRAINT fk_class_chapter_access_class FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_class_chapter_access_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensureClassExamAccessTable($db) {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS class_exam_access (
            class_id INT NOT NULL,
            exam_key VARCHAR(64) NOT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, exam_key),
            KEY idx_class_exam_access_key (exam_key),
            CONSTRAINT fk_class_exam_access_class FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

ensureClassChapterAccessTable($db);
ensureClassExamAccessTable($db);

// --- PHP MODULE PDF UPLOAD & PARSING ACTIONS ---
$flashMessage = '';
$flashType = '';
$uploadDir = '../uploads/modules/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $chapterId = (int)($_POST['chapter_id'] ?? 0);

    // 1. Upload or Replace PDF File with Text Extraction
    if (($action === 'upload_pdf' || $action === 'replace_pdf') && $chapterId > 0) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (isset($_FILES['module_pdf']) && $_FILES['module_pdf']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['module_pdf'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($fileExt === 'pdf') {
                // Delete existing old PDF file from disk if present
                $stmt = $db->prepare("SELECT pdf_file_path FROM chapters WHERE chapter_id = ?");
                $stmt->execute([$chapterId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing && !empty($existing['pdf_file_path'])) {
                    $oldPath = '../' . $existing['pdf_file_path'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                // Move new uploaded file
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
                            $flashMessage = 'Smalot PDFParser is not loaded. Ensure vendor/autoload.php exists.';
                            $flashType = 'danger';
                        }
                    } catch (Exception $e) {
                        $flashMessage = 'PDF Parsing Error: ' . $e->getMessage();
                        $flashType = 'danger';
                    }

                    // Check extracted text validity
                    if (empty($flashMessage) && strlen($extractedText) < 50) {
                        $flashMessage = 'Could not extract readable text. The PDF might be scanned/image-based or password-protected.';
                        $flashType = 'danger';
                    } elseif (empty($flashMessage)) {
                        // Update Database with both PDF path AND extracted lesson_content
                        try {
                            $stmt = $db->prepare("UPDATE chapters SET pdf_file_path = ?, lesson_content = ? WHERE chapter_id = ?");
                            $stmt->execute([$dbRelativePath, $extractedText, $chapterId]);

                            $flashMessage = 'Module PDF uploaded & ' . number_format(strlen($extractedText)) . ' characters extracted into lesson content!';
                            $flashType = 'success';
                        } catch (PDOException $e) {
                            $flashMessage = 'Database update error: ' . $e->getMessage();
                            $flashType = 'danger';
                        }
                    }
                } else {
                    $flashMessage = 'Could not save PDF file to disk.';
                    $flashType = 'danger';
                }
            } else {
                $flashMessage = 'File must be a .pdf document.';
                $flashType = 'danger';
            }
        } else {
            $flashMessage = 'PDF upload failed or no file selected.';
            $flashType = 'danger';
        }
    }

    // 2. Remove PDF File and Clear Extracted Text
    elseif ($action === 'remove_pdf' && $chapterId > 0) {
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

        $flashMessage = "PDF file and extracted text removed from chapter!";
        $flashType = "success";
    }
}

// Fetch Sections / Classes
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$classes_stmt = $db->prepare(
    "SELECT c.class_id, c.class_name
     FROM classes c
     JOIN school_year sy ON sy.sy_id = c.sy_id
     WHERE c.teacher_id = ? AND sy.is_active = 1
     ORDER BY c.class_name ASC"
);
$classes_stmt->execute([$teacher_id]);
$classes = $classes_stmt->fetchAll();

$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$valid_class_ids = array_map(function ($c) {
    return (int)$c['class_id'];
}, $classes);

if ($selected_class_id <= 0 || !in_array($selected_class_id, $valid_class_ids, true)) {
    $selected_class_id = count($classes) > 0 ? (int)$classes[0]['class_id'] : 0;
}

$exam_key = 'post_chapter_4_exam';
$chapter4_exam_is_locked = 1;
if ($selected_class_id > 0) {
    $exam_stmt = $db->prepare(
        "SELECT is_locked
         FROM class_exam_access
         WHERE class_id = ? AND exam_key = ?
         LIMIT 1"
    );
    $exam_stmt->execute([$selected_class_id, $exam_key]);
    $exam_row = $exam_stmt->fetch();
    if ($exam_row) {
        $chapter4_exam_is_locked = (int)($exam_row['is_locked'] ?? 1);
    }
}

// Fetch Chapters with Lock status & PDF metadata
$stmt = $db->prepare(
    "SELECT c.*, 
            CHAR_LENGTH(c.lesson_content) AS text_len,
            COALESCE(cca.is_locked, CASE WHEN c.chapter_order = 1 THEN 0 ELSE 1 END) AS is_locked
     FROM chapters c
     LEFT JOIN class_chapter_access cca
        ON cca.chapter_id = c.chapter_id
       AND cca.class_id = ?
     ORDER BY c.chapter_order"
);
$stmt->execute([$selected_class_id]);
$chapters = $stmt->fetchAll();

// Get all lessons
$lessons = $db->query("
    SELECT l.*, c.chapter_title
    FROM lessons l
    JOIN chapters c ON l.chapter_id = c.chapter_id
    ORDER BY c.chapter_order, l.lesson_order
")->fetchAll();

// Group lessons by chapters
$grouped_lessons = [];
$next_order_by_chapter = [];
foreach ($chapters as $chapter) {
    $next_order_by_chapter[$chapter['chapter_id']] = 1;
}

foreach ($lessons as $lesson) {
    $chapter_id = $lesson['chapter_id'];
    if (!isset($grouped_lessons[$chapter_id])) {
        $grouped_lessons[$chapter_id] = [
            'chapter_title' => $lesson['chapter_title'],
            'lessons' => []
        ];
    }
    $grouped_lessons[$chapter_id]['lessons'][] = $lesson;
    
    if ($lesson['lesson_order'] >= $next_order_by_chapter[$chapter_id]) {
        $next_order_by_chapter[$chapter_id] = $lesson['lesson_order'] + 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter & Module Management - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Tabs Styling */
        .tab-navigation {
            display: flex;
            align-items: center;
            gap: 0;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0;
            margin-bottom: 1.25rem;
            overflow: hidden;
        }

        .tab-btn {
            appearance: none;
            border: none;
            background: transparent;
            color: #4b5563;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.8rem 1.2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        }

        .tab-btn i { font-size: 0.88rem; color: #6b7280; }
        .tab-btn:hover { background: #eef2ff; color: #374151; }
        .tab-btn.active { color: #4f46e5; background: #ffffff; border-bottom-color: #4f46e5; }
        .tab-btn.active i { color: #4f46e5; }

        /* Lock & Info Cards */
        .chapter-access-note {
            margin-bottom: 1rem;
            padding: 0.9rem 1rem;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
            background: #eff6ff;
            color: #1e3a8a;
        }

        .chapter-lock-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .chapter-lock-badge.locked { background: #fee2e2; color: #991b1b; }
        .chapter-lock-badge.unlocked { background: #dcfce7; color: #166534; }

        .chapter-section-select {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .chapter-section-select select {
            min-width: 260px;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
        }

        .exam-schedule-card {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .exam-schedule-title { font-weight: 700; color: #1e3a8a; margin-bottom: 0.25rem; }
        .exam-schedule-subtitle { color: #334155; font-size: 0.92rem; }

        .exam-lock-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .exam-lock-badge.locked { background: #fee2e2; color: #991b1b; }
        .exam-lock-badge.unlocked { background: #dcfce7; color: #166534; }

        /* --- MODULE PDF GRID STYLING --- */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .module-card {
            background: var(--white, #ffffff);
            border: 1px solid var(--gray-200, #e2e8f0);
            border-radius: 12px;
            padding: 22px;
            position: relative;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .module-card:hover {
            border-color: #10b981;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }

        .card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }

        .pdf-icon-box {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }

        .pdf-icon-box.has-pdf { background: #fef2f2; color: #ef4444; }
        .pdf-icon-box.no-pdf { background: #f1f5f9; color: #94a3b8; }

        .module-title { font-weight: 700; color: #0f172a; font-size: 1rem; margin-bottom: 6px; line-height: 1.4; }

        .badge-mod {
            font-size: 11px; font-weight: 700;
            padding: 4px 10px; border-radius: 6px;
            display: inline-block; margin-bottom: 8px;
        }

        .badge-mod-success { background: #d1fae5; color: #065f46; }
        .badge-mod-warning { background: #fef3c7; color: #92400e; }

        /* 3-Dot Options Dropdown Menu */
        .menu-btn {
            background: transparent; border: none;
            font-size: 18px; color: #94a3b8;
            cursor: pointer; padding: 4px 8px; border-radius: 6px;
        }

        .menu-btn:hover { background: #f1f5f9; color: #334155; }

        .dropdown-menu-mod {
            position: absolute; top: 46px; right: 20px;
            background: #ffffff; border: 1px solid #e2e8f0;
            border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: none; flex-direction: column; width: 160px;
            z-index: 20; overflow: hidden;
        }

        .dropdown-item-mod {
            padding: 10px 14px; font-size: 13px; color: #334155;
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; border: none; background: transparent;
            text-align: left; width: 100%; transition: background 0.15s;
        }

        .dropdown-item-mod:hover { background: #f8fafc; }
        .dropdown-item-mod.danger { color: #dc2626; }
        .dropdown-item-mod.danger:hover { background: #fef2f2; }

        /* Modal Overlays */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none; align-items: center; justify-content: center;
            z-index: 1000; backdrop-filter: blur(4px);
        }

        .modal-body-mod {
            background: #ffffff; width: 100%; max-width: 850px;
            border-radius: 16px; padding: 24px; height: 85vh;
            display: flex; flex-direction: column;
        }

        iframe { width: 100%; height: 100%; border: 1px solid #e2e8f0; border-radius: 10px; margin-top: 14px; }
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
            <div class="content-area">
                <!-- Tab Navigation Header -->
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="chapters">
                        <i class="fas fa-book-open"></i> Chapters
                    </button>
                    <button class="tab-btn" data-tab="lessons">
                        <i class="fas fa-file-alt"></i> Lessons
                    </button>
                    <button class="tab-btn" data-tab="modules">
                        <i class="fas fa-file-pdf"></i> PDF Modules
                    </button>
                </div>

                <!-- TAB 1: CHAPTERS -->
                <div class="tab-content active" id="chapters-tab">
                    <div class="section-header">
                        <h2>Manage Chapters</h2>
                    </div>
                    <div class="chapter-access-note">
                        Chapter locks are section-based. Choose a section below; all students in that section will follow these chapter locks.
                    </div>
                    <div class="chapter-section-select">
                        <label for="classSectionSelect"><strong>Section</strong></label>
                        <select id="classSectionSelect">
                            <?php if (empty($classes)): ?>
                                <option value="0">No section found</option>
                            <?php else: ?>
                                <?php foreach ($classes as $class_row): ?>
                                    <option value="<?php echo (int)$class_row['class_id']; ?>" <?php echo ((int)$class_row['class_id'] === $selected_class_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class_row['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="exam-schedule-card">
                        <div>
                            <div class="exam-schedule-title">Chapter 4 Exam Schedule</div>
                            <div class="exam-schedule-subtitle">This is separate from chapter locks and controls the Chapter scene exam button.</div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                            <span class="exam-lock-badge <?php echo $chapter4_exam_is_locked === 1 ? 'locked' : 'unlocked'; ?>">
                                <i class="fas <?php echo $chapter4_exam_is_locked === 1 ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                                <?php echo $chapter4_exam_is_locked === 1 ? 'Locked' : 'Unlocked'; ?>
                            </span>
                            <button class="btn btn-sm <?php echo $chapter4_exam_is_locked === 1 ? 'btn-primary' : 'btn-secondary'; ?>" onclick="toggleExamLock('<?php echo $exam_key; ?>', <?php echo $chapter4_exam_is_locked === 1 ? 0 : 1; ?>, <?php echo (int)$selected_class_id; ?>)" <?php echo $selected_class_id <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas <?php echo $chapter4_exam_is_locked === 1 ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                <?php echo $chapter4_exam_is_locked === 1 ? 'Unlock Exam' : 'Lock Exam'; ?>
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Chapter Title</th>
                                    <th>Student Access</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="chaptersTableBody">
                                <?php if (empty($chapters)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No chapters found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($chapters as $chapter): ?>
                                <tr data-id="<?php echo $chapter['chapter_id']; ?>">
                                    <td><?php echo $chapter['chapter_order']; ?></td>
                                    <td><?php echo htmlspecialchars($chapter['chapter_title']); ?></td>
                                    <td>
                                        <span class="chapter-lock-badge <?php echo ((int)$chapter['is_locked'] === 1) ? 'locked' : 'unlocked'; ?>">
                                            <i class="fas <?php echo ((int)$chapter['is_locked'] === 1) ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                                            <?php echo ((int)$chapter['is_locked'] === 1) ? 'Locked' : 'Unlocked'; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-sm <?php echo ((int)$chapter['is_locked'] === 1) ? 'btn-primary' : 'btn-secondary'; ?>" onclick="toggleChapterLock(<?php echo $chapter['chapter_id']; ?>, <?php echo ((int)$chapter['is_locked'] === 1) ? 0 : 1; ?>, <?php echo (int)$selected_class_id; ?>)" <?php echo $selected_class_id <= 0 ? 'disabled' : ''; ?>>
                                            <i class="fas <?php echo ((int)$chapter['is_locked'] === 1) ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                            <?php echo ((int)$chapter['is_locked'] === 1) ? 'Unlock' : 'Lock'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: LESSONS -->
                <div class="tab-content" id="lessons-tab">
                    <div class="section-header">
                        <h2>Manage Lessons</h2>
                        <div class="header-actions">
                            <select id="chapterFilter" class="filter-select">
                                <option value="">All Chapters</option>
                                <?php foreach ($chapters as $chapter): ?>
                                <option value="<?php echo $chapter['chapter_id']; ?>">
                                    <?php echo htmlspecialchars($chapter['chapter_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="lessons-container">
                        <?php if (empty($grouped_lessons)): ?>
                        <div class="no-data-message">
                            <p>No lessons found.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($grouped_lessons as $chapter_id => $chapter_data): ?>
                        <div class="chapter-section" data-chapter-id="<?php echo $chapter_id; ?>">
                            <div class="chapter-header">
                                <h3><?php echo htmlspecialchars($chapter_data['chapter_title']); ?></h3>
                                <span class="lesson-count"><?php echo count($chapter_data['lessons']); ?> lessons</span>
                            </div>
                            <div class="table-container">
                                <table class="data-table lessons-table">
                                    <thead>
                                        <tr>
                                            <th class="order-col">Order</th>
                                            <th class="title-col">Lesson Title</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($chapter_data['lessons'] as $lesson): ?>
                                        <tr data-id="<?php echo $lesson['lesson_id']; ?>">
                                            <td class="order-col"><?php echo $lesson['lesson_order']; ?></td>
                                            <td class="title-col"><?php echo htmlspecialchars($lesson['lesson_title']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB 3: MODULE PDF UPLOADER -->
                <div class="tab-content" id="modules-tab">
                    <div class="section-header">
                        <h2><i class="fas fa-folder-open" style="color: #10b981;"></i> Module PDF Management</h2>
                    </div>
                    <div class="chapter-access-note">
                        Upload PDF files or soft copies for existing chapters to automatically import and update lesson content.
                    </div>

                    <div class="modules-grid">
                        <?php if (empty($chapters)): ?>
                            <p style="color: #94a3b8; grid-column: 1/-1; text-align: center; padding: 40px 0;">No chapters found in database.</p>
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
                                                <button class="menu-btn" onclick="togglePdfDropdown(event, <?php echo $ch['chapter_id']; ?>)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                
                                                <!-- 3-Dot Options Menu -->
                                                <div id="dropdown-pdf-<?php echo $ch['chapter_id']; ?>" class="dropdown-menu-mod">
                                                    <button class="dropdown-item-mod" onclick="openUploadPdfModal(<?php echo $ch['chapter_id']; ?>, '<?php echo htmlspecialchars($ch['chapter_title'], ENT_QUOTES); ?>', true)">
                                                        <i class="fas fa-sync-alt" style="color:#0284c7;"></i> Replace PDF
                                                    </button>
                                                    <form method="POST" onsubmit="return confirmPdfRemove(event)">
                                                        <input type="hidden" name="action" value="remove_pdf">
                                                        <input type="hidden" name="chapter_id" value="<?php echo $ch['chapter_id']; ?>">
                                                        <button type="submit" class="dropdown-item-mod danger">
                                                            <i class="fas fa-trash-alt"></i> Remove PDF
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <span class="badge-mod <?php echo $hasPdf ? 'badge-mod-success' : 'badge-mod-warning'; ?>">
                                            Chapter <?php echo $ch['chapter_order']; ?> • <?php echo $hasPdf ? number_format($ch['text_len'] ?? 0) . ' chars extracted' : 'Missing PDF'; ?>
                                        </span>
                                        <div class="module-title"><?php echo htmlspecialchars($ch['chapter_title']); ?></div>
                                    </div>

                                    <div style="margin-top: 18px;">
                                        <?php if ($hasPdf): ?>
                                            <button class="btn btn-secondary" style="width:100%; justify-content:center;" onclick="viewPdfModal('../<?php echo $ch['pdf_file_path']; ?>', '<?php echo htmlspecialchars($ch['chapter_title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-eye"></i> View PDF
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary" style="width:100%; justify-content:center;" onclick="openUploadPdfModal(<?php echo $ch['chapter_id']; ?>, '<?php echo htmlspecialchars($ch['chapter_title'], ENT_QUOTES); ?>', false)">
                                                <i class="fas fa-cloud-upload-alt"></i> Upload PDF
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div> <!-- end content-area -->
        </main>
    </div> <!-- end dashboard-container -->

    <!-- UPLOAD / REPLACE PDF MODAL -->
    <div id="uploadPdfModal" class="modal-overlay">
        <div class="modal-body-mod" style="height: auto; max-width: 480px;">
            <h3 id="pdf_modal_heading" style="margin-top:0; color: #0f172a; font-size:1.15rem;">
                <i class="fas fa-cloud-upload-alt" style="color: #10b981;"></i> Upload PDF File
            </h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 16px;">
                Target Chapter: <strong id="pdf_modal_chapter_title" style="color: #0f172a;"></strong>
            </p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" id="pdf_modal_action" name="action" value="upload_pdf">
                <input type="hidden" id="pdf_modal_chapter_id" name="chapter_id">

                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:8px; color: #334155;">Select PDF Module File:</label>
                    <input type="file" name="module_pdf" accept=".pdf" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; box-sizing:border-box;" required>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closePdfModal('uploadPdfModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Process & Extract Text</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF PREVIEW MODAL -->
    <div id="viewPdfModal" class="modal-overlay">
        <div class="modal-body-mod">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 id="pdf_viewer_title" style="margin:0; color: #0f172a; font-size:1.1rem;">PDF Preview</h3>
                <button class="btn btn-secondary btn-sm" onclick="closePdfModal('viewPdfModal')"><i class="fas fa-times"></i> Close</button>
            </div>
            <iframe id="pdfFrame" src=""></iframe>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const nextOrderByChapter = <?php echo json_encode($next_order_by_chapter); ?>;
        const selectedClassId = <?php echo (int)$selected_class_id; ?>;

        // Show SweetAlert for Flash Messages
        <?php if (!empty($flashMessage)): ?>
            Swal.fire({
                icon: '<?php echo $flashType === "success" ? "success" : "error"; ?>',
                title: '<?php echo $flashType === "success" ? "Success!" : "Notice"; ?>',
                text: '<?php echo $flashMessage; ?>',
                confirmColor: '#10b981'
            });
        <?php endif; ?>

        // PDF Options Menu Dropdown Toggle
        function togglePdfDropdown(e, id) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu-mod').forEach(el => el.style.display = 'none');
            const menu = document.getElementById('dropdown-pdf-' + id);
            if (menu) menu.style.display = 'flex';
        }

        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu-mod').forEach(el => el.style.display = 'none');
        });

        // Open Upload / Replace Modal
        function openUploadPdfModal(id, title, isReplace = false) {
            document.getElementById('pdf_modal_chapter_id').value = id;
            document.getElementById('pdf_modal_chapter_title').innerText = title;
            document.getElementById('pdf_modal_action').value = isReplace ? 'replace_pdf' : 'upload_pdf';
            document.getElementById('pdf_modal_heading').innerHTML = isReplace ? 
                '<i class="fas fa-sync-alt" style="color:#0284c7;"></i> Replace PDF File' : 
                '<i class="fas fa-cloud-upload-alt" style="color:#10b981;"></i> Upload PDF Module';
            
            document.getElementById('uploadPdfModal').style.display = 'flex';
        }

        // View PDF Preview Modal
        function viewPdfModal(filePath, title) {
            document.getElementById('pdf_viewer_title').innerText = title;
            document.getElementById('pdfFrame').src = filePath;
            document.getElementById('viewPdfModal').style.display = 'flex';
        }

        // Close PDF Modal Overlay
        function closePdfModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'viewPdfModal') {
                document.getElementById('pdfFrame').src = '';
            }
        }

        // Confirm PDF Removal
        function confirmPdfRemove(e) {
            e.preventDefault();
            const form = e.target;
            Swal.fire({
                title: 'Remove PDF from chapter?',
                text: "The stored PDF file and its extracted lesson text will be cleared.",
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
    <script src="../assets/js/chapters.js"></script>
</body>
</html>