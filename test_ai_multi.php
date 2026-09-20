<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();

// Fetch chapters with lesson content for selection
$chapters = $db->query("
    SELECT chapter_id, chapter_title, lesson_content, CHAR_LENGTH(lesson_content) AS text_len 
    FROM chapters 
    ORDER BY chapter_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Multi-Chapter Test Platform</title>
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
        
        .form-group { margin-bottom: 20px; position: relative; }
        label { display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 8px; color: #334155; }
        
        .dropdown-box {
            border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; padding: 12px; cursor: pointer;
            display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;
        }
        .dropdown-options {
            display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff;
            border: 1px solid #cbd5e1; border-radius: 10px; max-height: 220px; overflow-y: auto; z-index: 10;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08); margin-top: 4px;
        }
        .dropdown-options.open { display: block; }
        .option-item { padding: 10px 14px; display: flex; align-items: center; gap: 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
        .option-item:hover { background: #f0fdf4; }
        .option-item input { accent-color: var(--primary); width: 16px; height: 16px; }

        .controls { display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 16px; align-items: end; margin-top: 20px; }
        select { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.9rem; outline: none; }
        .btn-action { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 12px 24px; font-weight: 600; font-size: 0.95rem; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 46px; }
        .btn-action:disabled { opacity: 0.6; cursor: not-allowed; }

        .q-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .badge-bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
        .q-type-badge { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; background: #e2e8f0; color: #475569; }
        .chapter-tag-badge { font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 4px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .q-num { font-weight: 700; color: #0f172a; margin-bottom: 12px; font-size: 1rem; }
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .option-pill { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; color: #334155; }
        .option-pill.correct { background: #d1fae5; border-color: #a7f3d0; color: #065f46; font-weight: 600; }
        .ans-box { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; margin-bottom: 12px; }
        .quote-box { background: #f0fdf4; border-left: 4px solid var(--primary); padding: 10px 14px; border-radius: 0 8px 8px 0; font-size: 0.82rem; color: #166534; margin-top: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <div class="header-icon"><i class="fas fa-tags"></i></div>
            <div>
                <h2>AI Multi-Chapter Question Generator</h2>
                <p>Questions are distributed equally across all selected chapters.</p>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-book"></i> Target Chapter(s):</label>
            <div class="dropdown-box" onclick="toggleDropdown()">
                <span id="dropdownLabel">-- Select Chapters --</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="dropdown-options" id="dropdownMenu">
                <?php foreach ($chapters as $ch): ?>
                    <label class="option-item" onclick="event.stopPropagation();">
                        <input type="checkbox" class="ch-check" value="<?php echo $ch['chapter_id']; ?>" data-content="<?php echo htmlspecialchars($ch['lesson_content'] ?? ''); ?>" data-title="<?php echo htmlspecialchars($ch['chapter_title']); ?>" onchange="updateSelection()">
                        <span>
                            <?php echo htmlspecialchars($ch['chapter_title']); ?>
                            <small style="color: #64748b;"><?php echo empty($ch['lesson_content']) ? ' (No text stored)' : ' (' . number_format($ch['text_len']) . ' chars)'; ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="controls">
            <div>
                <label for="questionCount"><i class="fas fa-list-ol"></i> Quantity:</label>
                <select id="questionCount">
                    <option value="3">3 Questions</option>
                    <option value="5">5 Questions</option>
                    <option value="10" selected>10 Questions</option>
                    <option value="15">15 Questions</option>
                    <option value="20">20 Questions</option>
                    <option value="25">25 Questions</option>
                    <option value="30">30 Questions</option>
                </select>
            </div>
            <div>
                <label for="formatType"><i class="fas fa-sliders"></i> Format Mode:</label>
                <select id="formatType">
                    <option value="mixed" selected>Mixed Formats</option>
                    <option value="mcq">Multiple Choice</option>
                    <option value="true_false">True / False</option>
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>
            <div>
                <button id="btnGenerate" class="btn-action" onclick="generateQuestions()">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate Questions
                </button>
            </div>
        </div>

    </div>

    <div id="outputContainer" style="display: none;">
        <h3 style="color: #0f172a; margin-bottom: 16px;"><i class="fas fa-circle-check" style="color: var(--primary);"></i> Generated Questions</h3>
        <div id="questionsList"></div>
    </div>
</div>

<!-- Loading overlay -->
<div id="loadingOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.75);z-index:9999;flex-direction:column;align-items:center;justify-content:center;gap:18px;">
    <div style="width:54px;height:54px;border:5px solid rgba(255,255,255,.25);border-top-color:#10b981;border-radius:50%;animation:spin .8s linear infinite;"></div>
    <div id="loadingText" style="color:#fff;font-size:1rem;font-weight:600;text-align:center;max-width:340px;padding:0 20px;line-height:1.5;"></div>
</div>

<script>
const selectedChapters = [];

function toggleDropdown() {
    document.getElementById('dropdownMenu').classList.toggle('open');
}

document.addEventListener('click', (e) => {
    const menu = document.getElementById('dropdownMenu');
    if (!menu.contains(e.target) && !e.target.closest('.dropdown-box')) {
        menu.classList.remove('open');
    }
});

function updateSelection() {
    const checkboxes = document.querySelectorAll('.ch-check:checked');
    const label = document.getElementById('dropdownLabel');
    selectedChapters.length = 0;

    if (checkboxes.length === 0) {
        label.innerText = '-- Select Chapters --';
    } else {
        label.innerText = `${checkboxes.length} Chapter(s) Selected`;
        checkboxes.forEach(cb => {
            if (cb.dataset.content) {
                selectedChapters.push({ title: cb.dataset.title, text: cb.dataset.content });
            }
        });
    }
}

async function generateQuestions() {
    const totalCount = parseInt(document.getElementById('questionCount').value);
    const format = document.getElementById('formatType').value;
    const btn = document.getElementById('btnGenerate');

    if (selectedChapters.length === 0) {
        Swal.fire({ icon: 'warning', title: 'No Chapter Selected', text: 'Please select at least one chapter containing stored lesson content.', confirmColor: '#10b981' });
        return;
    }

    const numChapters = selectedChapters.length;
    const basePerChapter = Math.floor(totalCount / numChapters);
    const remainder = totalCount - (basePerChapter * numChapters);
    const CHAR_BUDGET = 4000;

    showLoading(true, 'Preparing...');
    btn.disabled = true;
    document.getElementById('outputContainer').style.display = 'none';

    const allQuestions = [];

    try {
        for (let i = 0; i < numChapters; i++) {
            const ch = selectedChapters[i];
            const chCount = basePerChapter + (i < remainder ? 1 : 0);
            if (chCount === 0) continue;

            updateLoadingText(`Generating questions... (${i + 1}/${numChapters})\n${ch.title}`);

            const aiResponse = await fetch('teacher/generate_ai_questions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contextText: ch.text.trim().substring(0, CHAR_BUDGET), count: chCount, formatType: format })
            });

            const aiData = await aiResponse.json();
            if (!aiData.success) throw new Error(`"${ch.title}": ${aiData.error}`);

            aiData.questions.forEach(q => { q.chapter_title = ch.title; allQuestions.push(q); });
        }

        renderQuestions(allQuestions);

        Swal.fire({ icon: 'success', title: 'Questions Ready!', text: `Generated ${allQuestions.length} question(s) across ${numChapters} chapter(s).`, confirmColor: '#10b981', timer: 2000 });

    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Generation Error', text: err.message, confirmColor: '#ef4444' });
    } finally {
        showLoading(false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate Questions';
    }
}

function showLoading(show, msg) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = show ? 'flex' : 'none';
    if (msg) updateLoadingText(msg);
}

function updateLoadingText(msg) {
    document.getElementById('loadingText').textContent = msg;
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
                return `<div class="option-pill ${isCorrect ? 'correct' : ''}"><strong>${letter}:</strong> ${esc(ans)} ${isCorrect ? '<i class="fas fa-check-circle"></i>' : ''}</div>`;
            }).join('');
            bodyHtml = `<div class="options-grid">${optionsHtml}</div>`;
        } else if (qType === 'true_false') {
            const isTrue = String(q.correct_answer).toLowerCase() === 'true';
            bodyHtml = `<div class="ans-box"><i class="fas fa-toggle-on"></i> Correct Answer: <strong>${isTrue ? 'TRUE' : 'FALSE'}</strong></div>`;
        } else if (qType === 'short_answer') {
            bodyHtml = `<div class="ans-box"><i class="fas fa-key"></i> Expected Answer: <strong>${esc(q.correct_answer)}</strong></div>`;
        }

        qCard.innerHTML = `
            <div class="badge-bar">
                <span class="q-type-badge">${qType.replace('_', ' ')}</span>
                <span class="chapter-tag-badge"><i class="fas fa-book"></i> ${esc(q.chapter_title || 'Unknown Chapter')}</span>
            </div>
            <div class="q-num">Q${idx + 1}: ${esc(q.question_text)}</div>
            ${bodyHtml}
            <div class="quote-box"><i class="fas fa-quote-left"></i> <strong>Source Quote:</strong> "${esc(q.source_quote)}"</div>
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