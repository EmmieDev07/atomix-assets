<style>
    /* Custom Blue Scrollbar Styles */
    .sidebar {
        scrollbar-width: thin;
        scrollbar-color: #2563eb #f1f5f9;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #2563eb;
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #1d4ed8;
    }
</style>

<?php
// Load RBAC permissions for the current teacher (safe fallback: all granted if table missing)
$_sidebarPerms = [];
if (isset($db)) {
    $_sidebarPerms = getTeacherPermissions($db);
}
$_can = function(string $perm) use ($_sidebarPerms): bool {
    return in_array($perm, $_sidebarPerms, true);
};
?>
<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation menu" aria-expanded="false">
    <i class="fas fa-bars"></i>
</button>
<div class="logo" style="display: flex; flex-direction: column; align-items: center;">
    <img src="../logoatomix.png" alt="Atomix Logo" style="width: 120px; margin-bottom: 8px;">
    <span style="font-size: 22px; font-weight: bold; color: #f8f8f8;">Atomix</span>
</div>
<?php
$_questionsPages = ['questions.php','archived_questions.php','question_bank.php','chapter_pretest.php','export_questions.php'];
$_quizzesPages   = ['quizzes.php','active_quizzes.php','quiz_takers.php','exam.php','exam_manager.php'];
$_reportsPages   = ['game_progress.php'];
$_currentPage    = basename($_SERVER['PHP_SELF']);
$_questionsOpen  = in_array($_currentPage, $_questionsPages) ? ' open' : '';
$_quizzesOpen    = in_array($_currentPage, $_quizzesPages)   ? ' open' : '';
$_reportsOpen    = in_array($_currentPage, $_reportsPages)   ? ' open' : '';
?>
<nav class="nav-menu">
    <a href="dashboard.php" class="nav-item<?php if($_currentPage=='dashboard.php') echo ' active'; ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>
    <a href="chapters.php" class="nav-item<?php if($_currentPage=='chapters.php') echo ' active'; ?>">
        <i class="fas fa-book"></i>
        <span>Chapters</span>
    </a>
    <?php if ($_can('can_manage_classes')): ?>
    <a href="classes.php" class="nav-item<?php if($_currentPage=='classes.php') echo ' active'; ?>">
        <i class="fas fa-chalkboard"></i>
        <span>Classes</span>
    </a>
    <?php endif; ?>
    <?php if ($_can('can_manage_classes')): ?>
    <a href="class_students.php" class="nav-item<?php if($_currentPage=='class_students.php') echo ' active'; ?>">
        <i class="fas fa-user-graduate"></i>
        <span>Students</span>
    </a>
    <?php endif; ?>

    <?php if ($_can('can_manage_questions')): ?>
    <div class="nav-group<?php echo $_questionsOpen; ?>">
        <button class="nav-group-header" type="button">
            <span class="nav-group-left">
                <i class="fas fa-question-circle"></i>
                <span>Questions</span>
            </span>
            <i class="fas fa-chevron-down nav-group-chevron"></i>
        </button>
        <div class="nav-group-items">
            <a href="questions.php" class="nav-item nav-subitem<?php if($_currentPage=='questions.php') echo ' active'; ?>">
                <i class="fas fa-question-circle"></i>
                <span>Questions</span>
            </a>
            <a href="archived_questions.php" class="nav-item nav-subitem<?php if($_currentPage=='archived_questions.php') echo ' active'; ?>">
                <i class="fas fa-box-archive"></i>
                <span>Archived Questions</span>
            </a>
            <a href="question_bank.php" class="nav-item nav-subitem<?php if($_currentPage=='question_bank.php') echo ' active'; ?>">
                <i class="fas fa-cubes"></i>
                <span>Game Questions</span>
            </a>
            <a href="chapter_pretest.php" class="nav-item nav-subitem<?php if($_currentPage=='chapter_pretest.php') echo ' active'; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Chapter Pretests</span>
            </a>
            <a href="export_questions.php" class="nav-item nav-subitem<?php if($_currentPage=='export_questions.php') echo ' active'; ?>">
                <i class="fas fa-file-export"></i>
                <span>Export Hardcopy</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($_can('can_manage_quizzes')): ?>
    <div class="nav-group<?php echo $_quizzesOpen; ?>">
        <button class="nav-group-header" type="button">
            <span class="nav-group-left">
                <i class="fas fa-clipboard-list"></i>
                <span>Assessments</span>
            </span>
            <i class="fas fa-chevron-down nav-group-chevron"></i>
        </button>
        <div class="nav-group-items">
            <a href="quizzes.php" class="nav-item nav-subitem<?php if($_currentPage=='quizzes.php') echo ' active'; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Quizzes</span>
            </a>
            <a href="active_quizzes.php" class="nav-item nav-subitem<?php if($_currentPage=='active_quizzes.php') echo ' active'; ?>">
                <i class="fas fa-play-circle"></i>
                <span>Active Quizzes</span>
            </a>
            <a href="quiz_takers.php" class="nav-item nav-subitem<?php if($_currentPage=='quiz_takers.php') echo ' active'; ?>">
                <i class="fas fa-user-check"></i>
                <span>Quiz Takers</span>
            </a>
            <a href="exam.php" class="nav-item nav-subitem<?php if($_currentPage=='exam.php' || $_currentPage=='exam_manager.php') echo ' active'; ?>">
                <i class="fas fa-file-signature"></i>
                <span>Exams</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($_can('can_view_reports')): ?>
    <div class="nav-group<?php echo $_reportsOpen; ?>">
        <button class="nav-group-header" type="button">
            <span class="nav-group-left">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </span>
            <i class="fas fa-chevron-down nav-group-chevron"></i>
        </button>
        <div class="nav-group-items">
            <a href="game_progress.php" class="nav-item nav-subitem<?php if($_currentPage=='game_progress.php') echo ' active'; ?>">
                <i class="fas fa-gamepad"></i>
                <span>Game Progress</span>
            </a>
            <?php if ($_can('can_manage_classes')): ?>
            <a href="classes.php" class="nav-item nav-subitem<?php if($_currentPage=='classes.php') echo ' active'; ?>">
                <i class="fas fa-users"></i>
                <span>Class Reports</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <a href="logout.php" class="nav-item" style="margin-top: auto;">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</nav>

<script>
(function() {
    var sidebar = document.querySelector('.sidebar');
    var toggle = document.getElementById('sidebarToggle');

    if (sidebar && toggle) {
        if (toggle.parentElement !== document.body) {
            document.body.appendChild(toggle);
        }

        var setExpanded = function(expanded) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            document.body.classList.toggle('sidebar-open', expanded);
        };

        setExpanded(false);

        toggle.addEventListener('click', function() {
            setExpanded(!document.body.classList.contains('sidebar-open'));
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth > 768) {
                return;
            }

            if (document.body.classList.contains('sidebar-open') &&
                sidebar && !sidebar.contains(event.target) &&
                !toggle.contains(event.target)) {
                setExpanded(false);
            }
        });
    }

    document.querySelectorAll('.nav-group-header').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var group = this.closest('.nav-group');
            group.classList.toggle('open');
        });
    });
})();
</script>