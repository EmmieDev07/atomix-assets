<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkAdminPage();

$db = Database::getInstance()->getConnection();

$dbError = null;
$chapters = [];
$lessons = [];
$grouped_lessons = [];
$next_order_by_chapter = [];

try {
    $chapters = $db->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

    foreach ($chapters as $chapter) {
        $next_order_by_chapter[(int)$chapter['chapter_id']] = 1;
    }

    $lessons = $db->query(
        "SELECT l.*, c.chapter_title
         FROM lessons l
         JOIN chapters c ON l.chapter_id = c.chapter_id
         ORDER BY c.chapter_order, l.lesson_order"
    )->fetchAll();

    foreach ($lessons as $lesson) {
        $chapter_id = (int)$lesson['chapter_id'];
        if (!isset($grouped_lessons[$chapter_id])) {
            $grouped_lessons[$chapter_id] = [
                'chapter_title' => $lesson['chapter_title'],
                'lessons' => []
            ];
        }

        $grouped_lessons[$chapter_id]['lessons'][] = $lesson;

        if ((int)$lesson['lesson_order'] >= (int)$next_order_by_chapter[$chapter_id]) {
            $next_order_by_chapter[$chapter_id] = (int)$lesson['lesson_order'] + 1;
        }
    }
} catch (Exception $e) {
    error_log('Admin chapters page error: ' . $e->getMessage());
    $dbError = 'Failed to load chapter and lesson data. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Management - Atomix Admin</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="chapters.php" class="nav-item active"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <h1>Chapter and Lesson Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">Chapter Management</span>
                </div>
            </nav>

            <div class="content-area">
                <?php if (!empty($dbError)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>

                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="chapters">
                        <i class="fas fa-book-open"></i> Chapters
                    </button>
                    <button class="tab-btn" data-tab="lessons">
                        <i class="fas fa-file-alt"></i> Lessons
                    </button>
                </div>

                <div class="tab-content active" id="chapters-tab">
                    <div class="section-header">
                        <h2>Manage Chapters</h2>
                        <button class="btn btn-primary" onclick="openModal('addChapterModal')">
                            <i class="fas fa-plus"></i> Add Chapter
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Chapter Title</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="chaptersTableBody">
                                <?php if (empty($chapters)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No chapters found. Create your first chapter!</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($chapters as $chapter): ?>
                                <tr data-id="<?php echo (int)$chapter['chapter_id']; ?>">
                                    <td><?php echo (int)$chapter['chapter_order']; ?></td>
                                    <td><?php echo htmlspecialchars($chapter['chapter_title']); ?></td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-info" title="Edit Chapter" onclick="editChapter(<?php echo (int)$chapter['chapter_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-content" id="lessons-tab">
                    <div class="section-header">
                        <h2>Manage Lessons</h2>
                        <div class="header-actions">
                            <select id="chapterFilter" class="filter-select">
                                <option value="">All Chapters</option>
                                <?php foreach ($chapters as $chapter): ?>
                                <option value="<?php echo (int)$chapter['chapter_id']; ?>">
                                    <?php echo htmlspecialchars($chapter['chapter_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" onclick="openModal('addLessonModal')">
                                <i class="fas fa-plus"></i> Add Lesson
                            </button>
                        </div>
                    </div>

                    <div class="lessons-container">
                        <?php if (empty($grouped_lessons)): ?>
                        <div class="no-data-message">
                            <p>No lessons found. Create chapters first, then add lessons!</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($grouped_lessons as $chapter_id => $chapter_data): ?>
                        <div class="chapter-section" data-chapter-id="<?php echo (int)$chapter_id; ?>">
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
                                            <th class="actions-col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($chapter_data['lessons'] as $lesson): ?>
                                        <tr data-id="<?php echo (int)$lesson['lesson_id']; ?>">
                                            <td class="order-col"><?php echo (int)$lesson['lesson_order']; ?></td>
                                            <td class="title-col"><?php echo htmlspecialchars($lesson['lesson_title']); ?></td>
                                            <td class="actions-col actions">
                                                <button class="btn btn-sm btn-info" onclick="editLesson(<?php echo (int)$lesson['lesson_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
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
            </div>
        </main>
    </div>

    <div class="modal" id="addChapterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-book-open"></i> Add New Chapter</h3>
                <button class="close-btn" onclick="closeModal('addChapterModal')">&times;</button>
            </div>
            <form id="addChapterForm" onsubmit="submitChapter(event)">
                <div class="form-group">
                    <label for="chapterTitle">Chapter Title *</label>
                    <input type="text" id="chapterTitle" name="chapter_title" required placeholder="Enter chapter title">
                </div>
                <div class="form-group">
                    <label for="chapterOrder">Chapter Order</label>
                    <input type="number" id="chapterOrder" name="chapter_order" value="1" min="1">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addChapterModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Chapter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="editChapterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Chapter</h3>
                <button class="close-btn" onclick="closeModal('editChapterModal')">&times;</button>
            </div>
            <form id="editChapterForm" onsubmit="updateChapter(event)">
                <input type="hidden" id="editChapterId" name="chapter_id">
                <div class="form-group">
                    <label for="editChapterTitle">Chapter Title *</label>
                    <input type="text" id="editChapterTitle" name="chapter_title" required>
                </div>
                <div class="form-group">
                    <label for="editChapterOrder">Chapter Order</label>
                    <input type="number" id="editChapterOrder" name="chapter_order" value="1" min="1">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editChapterModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Chapter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="addLessonModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Add New Lesson</h3>
                <button class="close-btn" onclick="closeModal('addLessonModal')">&times;</button>
            </div>
            <form id="addLessonForm" onsubmit="submitLesson(event)">
                <div class="form-group">
                    <label for="lessonChapter">Chapter *</label>
                    <select id="lessonChapter" name="chapter_id" required>
                        <option value="">Select a Chapter</option>
                        <?php foreach ($chapters as $chapter): ?>
                        <option value="<?php echo (int)$chapter['chapter_id']; ?>">
                            <?php echo htmlspecialchars($chapter['chapter_title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="lessonTitle">Lesson Title *</label>
                    <input type="text" id="lessonTitle" name="lesson_title" required placeholder="Enter lesson title">
                </div>
                <div class="form-group">
                    <label for="lessonOrder">Lesson Order</label>
                    <input type="number" id="lessonOrder" name="lesson_order" value="1" min="1">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addLessonModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Lesson</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast" id="toast">
        <span class="toast-message"></span>
    </div>

    <script>
        const nextOrderByChapter = <?php echo json_encode($next_order_by_chapter); ?>;
        const selectedClassId = 0;
    </script>
    <script src="../assets/js/chapters.js"></script>
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