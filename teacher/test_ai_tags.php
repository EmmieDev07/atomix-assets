<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Fetch questions including the is_ai_generated column
    $stmt = $db->query("
        SELECT q.question_id, q.question_text, q.question_type, q.visibility, q.is_ai_generated, 
               l.lesson_title, t.first_name, t.last_name
        FROM questions_master q
        LEFT JOIN lessons l ON q.lesson_id = l.lesson_id
        LEFT JOIN teachers t ON q.created_by_teacher_id = t.teacher_id
        ORDER BY q.question_id DESC
        LIMIT 10
    ");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch choices for rendering True/False options
    $choicesStmt = $db->query("SELECT * FROM quiz_choices ORDER BY choice_id");
    $choices = [];
    while ($row = $choicesStmt->fetch(PDO::FETCH_ASSOC)) {
        $choices[$row['question_id']][] = $row;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Tag Preview Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; padding: 30px; }
        .card { background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px 24px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .tags { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; }
        
        /* Badges */
        .badge-type { background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 12px; font-weight: 700; text-transform: capitalize; }
        .badge-vis { background: #e2e8f0; color: #475569; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
        .meta { color: #64748b; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; }
        
        /* AI Colored Tag */
        .badge-ai { background: linear-gradient(135deg, #a855f7, #ec4899); color: #ffffff; padding: 4px 10px; border-radius: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 5px rgba(168, 85, 247, 0.3); }
        .badge-manual { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }

        .question-title { font-size: 1rem; color: #0f172a; margin-bottom: 14px; font-weight: 500; }
        .choices { display: flex; gap: 12px; }
        .choice-btn { border-radius: 20px; padding: 8px 18px; font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .choice-btn.correct { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
        .choice-btn.incorrect { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        
        .action-btns { display: flex; gap: 8px; }
        .btn-icon { border: none; width: 32px; height: 32px; border-radius: 8px; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-edit { background: #3b82f6; }
        .btn-delete { background: #ef4444; }
    </style>
</head>
<body>

    <h2 style="color: #0f172a; margin-bottom: 20px;">AI Tags Visual Test Output</h2>

    <?php foreach ($questions as $q): ?>
        <?php 
            $isAi = (isset($q['is_ai_generated']) && (int)$q['is_ai_generated'] === 1); 
            $qChoices = $choices[$q['question_id']] ?? [];
        ?>
        <div class="card">
            <div class="header">
                <div class="tags">
                    <span class="badge-type"><?php echo htmlspecialchars(str_replace('_', ' ', $q['question_type'])); ?></span>
                    <span class="badge-vis"><?php echo ucfirst(htmlspecialchars($q['visibility'])); ?></span>
                    
                    <!-- AI TAG DISPLAY -->
                    <?php if ($isAi): ?>
                        <span class="badge-ai"><i class="fas fa-wand-magic-sparkles"></i> AI Generated</span>
                    <?php else: ?>
                        <span class="badge-manual"><i class="fas fa-user-pen"></i> Manual</span>
                    <?php endif; ?>

                    <span class="meta"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($q['lesson_title'] ?? 'No Lesson'); ?></span>
                    <span class="meta">By: <?php echo htmlspecialchars(($q['first_name'] ?? 'John') . ' ' . ($q['last_name'] ?? 'Doe')); ?></span>
                </div>

                <div class="action-btns">
                    <button class="btn-icon btn-edit"><i class="fas fa-pen-to-square"></i></button>
                    <button class="btn-icon btn-delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>

            <div class="question-title"><?php echo htmlspecialchars($q['question_text']); ?></div>

            <?php if (!empty($qChoices)): ?>
                <div class="choices">
                    <?php foreach ($qChoices as $c): ?>
                        <div class="choice-btn <?php echo $c['is_correct'] ? 'correct' : 'incorrect'; ?>">
                            <i class="far fa-circle<?php echo $c['is_correct'] ? '-check' : ''; ?>"></i>
                            <?php echo htmlspecialchars($c['choice_text']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</body>
</html>