<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();

// Fetch chapters with lesson content from database
$chapters = $db->query("SELECT chapter_id, chapter_title, lesson_content, CHAR_LENGTH(lesson_content) AS text_len FROM chapters ORDER BY chapter_order ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Quiz Generator Test Platform</title>
    <!-- FontAwesome & SweetAlert2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #10b981; --primary-dark: #059669; --bg: #f8fafc; --card-bg: #ffffff; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: var(--bg); color: #1e293b; padding: 40px 20px; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: var(--card-bg); border-radius: 16px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; }
        .header-icon { background: #d1fae5; color: var(--primary-dark); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .header h2 { margin: 0; font-size: 1.4rem; color: #0f172a; }
        .header p { margin: 4px 0 0; font-size: 0.85rem; color: #64748b; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 8px; color: #334155; }
        select, textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.9rem; box-sizing: border-box; outline: none; transition: all 0.2s; }
        select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
        textarea { background: #f8fafc; color: #475569; font-family: inherit; resize: vertical; }
        .controls { display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 16px; align-items: end; }
        .btn-action { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 12px 24px; font-weight: 600; font-size: 0.95rem; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 46px; transition: opacity 0.2s; }
        .btn-action:hover { opacity: 0.95; }
        .btn-action:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .debug-box { background: #0f172a; color: #38bdf8; border-radius: 12px; padding: 16px; font-family: 'Courier New', monospace; font-size: 0.82rem; max-height: 220px; overflow-y: auto; display: none; margin-top: 20px; border: 1px solid #1e293b; }
        .debug-title { color: #f1f5f9; font-weight: bold; margin-bottom: 8px; font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; }
        
        .q-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .q-type-badge { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; background: #e2e8f0; color: #475569; display: inline-block; margin-bottom: 8px; }
        .q-num { font-weight: 700; color: #0f172a; margin-bottom: 12px; font-size: 1rem; }
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .option-pill { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; color: #334155; }
        .option-pill.correct { background: #d1fae5; border-color: #a7f3d0; color: #065f46; font-weight: 600; }
        .ans-box { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; margin-bottom: 12px; }
        .quote-box { background: #f0fdf4; border-left: 4px solid var(--primary); padding: 10px 14px; border-radius: 0 8px 8px 0; font-size: 0.82rem; color: #166534; margin-top: 8px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <div class="header-icon"><i class="fas fa-brain"></i></div>
            <div>
                <h2>AI Question Generator Test Platform</h2>
                <p>Testing True/False, MCQ, and Short Answer via <code>teacher/generate_ai_questions.php</code>.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="chapterSelect"><i class="fas fa-book"></i> Target Chapter / Module:</label>
            <select id="chapterSelect" onchange="loadChapterText()">
                <option value="">-- Select a Chapter --</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?php echo $ch['chapter_id']; ?>" data-content="<?php echo htmlspecialchars($ch['lesson_content'] ?? ''); ?>">
                        <?php echo htmlspecialchars($ch['chapter_title']); ?> 
                        <?php echo empty($ch['lesson_content']) ? ' (No text stored)' : ' (' . number_format($ch['text_len']) . ' chars)'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label><i class="fas fa-file-alt"></i> Stored Lesson Content:</label>
            <textarea id="dbContentPreview" rows="4" readonly placeholder="Select a chapter above..."></textarea>
        </div>

        <div class="controls">
            <div>
                <label for="questionCount"><i class="fas fa-list-ol"></i> Quantity:</label>
                <select id="questionCount">
                    <option value="3" selected>3 Questions</option>
                    <option value="5">5 Questions</option>
                    <option value="10">10 Questions</option>
                </select>
            </div>
            <div>
                <label for="formatType"><i class="fas fa-sliders"></i> Format Mode:</label>
                <select id="formatType">
                    <option value="mcq">Multiple Choice</option>
                    <option value="true_false" selected>True / False</option>
                    <option value="short_answer">Short Answer</option>
                    <option value="mixed">Mixed Formats</option>
                </select>
            </div>
            <div>
                <button id="btnGenerate" class="btn-action" onclick="generateQuestions()">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate Questions
                </button>
            </div>
        </div>

        <div id="debugBox" class="debug-box">
            <div class="debug-title">
                <span><i class="fas fa-terminal"></i> Console Log</span>
                <span id="debugStatus" style="color: #a3e635;">IDLE</span>
            </div>
            <div id="debugLog"></div>
        </div>
    </div>

    <div id="outputContainer" style="display: none;">
        <h3 style="color: #0f172a; margin-bottom: 16px;"><i class="fas fa-circle-check" style="color: var(--primary);"></i> Generated Quiz Items</h3>
        <div id="questionsList"></div>
    </div>
</div>

<script>
function logDebug(msg, type = 'info') {
    const debugBox = document.getElementById('debugBox');
    const debugLog = document.getElementById('debugLog');
    debugBox.style.display = 'block';

    const timestamp = new Date().toLocaleTimeString();
    let color = '#38bdf8';
    if (type === 'success') color = '#4ade80';
    if (type === 'error') color = '#f87171';

    debugLog.innerHTML += `<div style="color: ${color}; margin-bottom: 4px;">[${timestamp}] ${msg}</div>`;
    debugBox.scrollTop = debugBox.scrollHeight;
}

function loadChapterText() {
    const select = document.getElementById('chapterSelect');
    const selectedOption = select.options[select.selectedIndex];
    const content = selectedOption.dataset.content || '';
    
    document.getElementById('dbContentPreview').value = content;
    if (content) {
        logDebug(`Loaded ${content.length} characters from selection.`, 'info');
    }
}

async function generateQuestions() {
    let rawText = document.getElementById('dbContentPreview').value.trim();
    const count = document.getElementById('questionCount').value;
    const format = document.getElementById('formatType').value;
    const btn = document.getElementById('btnGenerate');

    if (!rawText) {
        Swal.fire({
            icon: 'warning',
            title: 'No Content Selected',
            text: 'Please select a chapter that contains stored lesson content.',
            confirmColor: '#10b981'
        });
        return;
    }

    // Limit oversized text context length so cURL completes quickly without timing out
    if (rawText.length > 3000) {
        rawText = rawText.substring(0, 3000);
    }

    document.getElementById('debugLog').innerHTML = '';
    document.getElementById('debugStatus').innerText = 'PROCESSING...';
    document.getElementById('debugStatus').style.color = '#facc15';
    document.getElementById('outputContainer').style.display = 'none';

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Contacting AI...';

    logDebug(`Posting payload to teacher/generate_ai_questions.php...`);
    logDebug(`Parameters -> count: ${count}, formatType: ${format}, textLength: ${rawText.length} chars`);

    try {
        const response = await fetch('teacher/generate_ai_questions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                contextText: rawText, 
                count: parseInt(count),
                formatType: format 
            })
        });

        logDebug(`HTTP Response Code: ${response.status}`);
        
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonErr) {
            logDebug(`Non-JSON Response Detected.`, 'error');
            logDebug(`Server Response: ${responseText.substring(0, 200)}...`, 'error');
            throw new Error("Server returned non-JSON output.");
        }

        if (data.success) {
            logDebug(`Received ${data.questions.length} formatted questions!`, 'success');
            document.getElementById('debugStatus').innerText = 'SUCCESS';
            document.getElementById('debugStatus').style.color = '#4ade80';

            renderQuestions(data.questions);

            Swal.fire({
                icon: 'success',
                title: 'Questions Ready!',
                text: `Successfully generated ${data.questions.length} question(s).`,
                confirmColor: '#10b981',
                timer: 2000
            });
        } else {
            logDebug(`Endpoint Error: ${data.error}`, 'error');
            document.getElementById('debugStatus').innerText = 'FAILED';
            document.getElementById('debugStatus').style.color = '#f87171';

            Swal.fire({
                icon: 'error',
                title: 'Generation Error',
                text: data.error || 'Failed to generate questions.',
                confirmColor: '#ef4444'
            });
        }
    } catch (err) {
        logDebug(`Client Exception: ${err.message}`, 'error');
        document.getElementById('debugStatus').innerText = 'ERROR';
        document.getElementById('debugStatus').style.color = '#f87171';

        Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            text: err.message,
            confirmColor: '#ef4444'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate Questions';
    }
}

function renderQuestions(questions) {
    const container = document.getElementById('outputContainer');
    const list = document.getElementById('questionsList');

    container.style.display = 'block';
    list.innerHTML = '';

    questions.forEach((q, idx) => {
        const qCard = document.createElement('div');
        qCard.className = 'q-card';

        const qType = (q.question_type || 'mcq').toLowerCase();
        let bodyHtml = '';

        if (qType === 'mcq') {
            const optionsHtml = [q.answer_0, q.answer_1, q.answer_2, q.answer_3].map((ans, i) => {
                const isCorrect = i === parseInt(q.correct_answer_index);
                const letter = String.fromCharCode(65 + i);
                return `<div class="option-pill ${isCorrect ? 'correct' : ''}">
                    <strong>${letter}:</strong> ${esc(ans)} ${isCorrect ? ' <i class="fas fa-check-circle"></i>' : ''}
                </div>`;
            }).join('');

            bodyHtml = `<div class="options-grid">${optionsHtml}</div>`;
        } else if (qType === 'true_false') {
            const isTrue = String(q.correct_answer).toLowerCase() === 'true';
            bodyHtml = `<div class="ans-box">
                <i class="fas fa-toggle-on"></i> Correct Answer: <strong>${isTrue ? 'TRUE' : 'FALSE'}</strong>
            </div>`;
        } else if (qType === 'short_answer') {
            bodyHtml = `<div class="ans-box">
                <i class="fas fa-key"></i> Expected Answer: <strong>${esc(q.correct_answer)}</strong>
            </div>`;
        }

        qCard.innerHTML = `
            <span class="q-type-badge">${qType.replace('_', ' ')}</span>
            <div class="q-num">Q${idx + 1}: ${esc(q.question_text)}</div>
            ${bodyHtml}
            <div class="quote-box">
                <i class="fas fa-quote-left"></i> <strong>Source Context:</strong> "${esc(q.source_quote)}"
            </div>
        `;
        list.appendChild(qCard);
    });
}

function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>