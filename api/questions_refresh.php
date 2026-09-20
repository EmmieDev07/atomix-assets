<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();
$db = Database::getInstance()->getConnection();
$teacher_id = getTeacherId();

$lessons = $db->query("
    SELECT l.*, c.chapter_title
    FROM lessons l
    JOIN chapters c ON l.chapter_id = c.chapter_id
    ORDER BY c.chapter_order, l.lesson_order
")->fetchAll();

$questions = $db->query("
    SELECT q.*, l.lesson_title, c.chapter_title,
           (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count
    FROM questions_master q 
    JOIN lessons l ON q.lesson_id = l.lesson_id 
    JOIN chapters c ON l.chapter_id = c.chapter_id 
    WHERE q.created_by_teacher_id = $teacher_id
    ORDER BY q.question_id DESC
")->fetchAll();

$allChoices = [];
$choicesStmt = $db->query("SELECT * FROM quiz_choices ORDER BY choice_id");
while ($choice = $choicesStmt->fetch()) {
    $allChoices[$choice['question_id']][] = $choice;
}

ob_start();
?>
<?php if (empty($questions)): ?>
<div class="empty-state">
    <i class="fas fa-question-circle"></i>
    <h3>No questions yet</h3>
    <p>Create your first question by clicking the button above!</p>
</div>
<?php else: ?>
<?php foreach ($questions as $question): ?>
<div class="question-card" data-id="<?php echo $question['question_id']; ?>" 
     data-lesson="<?php echo $question['lesson_id']; ?>"
     data-type="<?php echo $question['question_type']; ?>"
     data-visibility="<?php echo $question['visibility']; ?>">
    <div class="question-header">
        <div class="question-meta">
            <span class="badge badge-<?php echo $question['question_type']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $question['question_type'] ?? 'mcq')); ?>
            </span>
            <span class="badge badge-<?php echo $question['visibility']; ?>">
                <?php echo ucfirst($question['visibility']); ?>
            </span>
            <span class="lesson-tag">
                <i class="fas fa-book"></i> <?php echo htmlspecialchars($question['lesson_title']); ?>
            </span>
        </div>
        <div class="question-actions">
            <button class="btn btn-sm btn-info" onclick="editQuestion(<?php echo $question['question_id']; ?>)">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteQuestion(<?php echo $question['question_id']; ?>)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <div class="question-text">
        <?php echo htmlspecialchars($question['question_text']); ?>
    </div>
    <?php if (isset($allChoices[$question['question_id']])): ?>
    <div class="choices-preview">
        <?php foreach ($allChoices[$question['question_id']] as $choice): ?>
        <div class="choice-item <?php echo $choice['is_correct'] ? 'correct' : ''; ?>">
            <?php if ($choice['is_correct']): ?>
            <i class="fas fa-check-circle"></i>
            <?php else: ?>
            <i class="far fa-circle"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($choice['choice_text']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php
$html = ob_get_clean();
echo $html;
