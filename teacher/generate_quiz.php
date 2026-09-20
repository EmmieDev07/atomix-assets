<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';

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
    <title>AI Quiz Generator - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 18px; }
        .form-group.full-width { grid-column: 1 / -1; }

        label {
            display: block;
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            box-sizing: border-box;
            outline: none;
            transition: var(--transition);
            background: var(--white);
            color: var(--dark-color);
        }

        select:focus, textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        textarea {
            background: var(--gray-100);
            color: var(--gray-500);
            font-family: inherit;
            resize: vertical;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1.5fr;
            gap: 16px;
            align-items: end;
        }

        .btn-action {
            background: linear-gradient(135deg, var(--primary-color), #059669);
            color: white;
            border: none;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            height: 46px;
            transition: var(--transition);
        }

        .btn-action:hover { opacity: 0.95; box-shadow: var(--shadow); }
        .btn-action:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Question Cards Styling */
        .q-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-top: 16px;
            box-shadow: var(--shadow);
            position: relative;
        }

        .q-type-badge {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            background: var(--gray-100);
            color: var(--gray-500);
            display: inline-block;
            margin-bottom: 10px;
        }

        .q-num {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 14px;
            font-size: 1.05rem;
            line-height: 1.4;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .option-pill {
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            padding: 10px 14px;
            border-radius: var(--radius-md);
            font-size: 0.88rem;
            color: var(--dark-color);
        }

        .option-pill.correct {
            background: #d1fae5;
            border-color: #a7f3d0;
            color: #065f46;
            font-weight: 600;
        }

        .ans-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .quote-box {
            background: #f0fdf4;
            border-left: 4px solid var(--primary-color);
            padding: 10px 14px;
            border-radius: 0 8px 8px 0;
            font-size: 0.82rem;
            color: #166534;
            margin-top: 10px;
        }

        /* Loading Screen Modal Overlay */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(5px);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            color: white;
            text-align: center;
        }

        .loading-spinner {
            position: relative;
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
        }

        .loading-spinner i {
            font-size: 2.5rem;
            color: var(--primary-color);
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
        }

        .spinner-ring {
            width: 100%;
            height: 100%;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .loading-sub {
            font-size: 0.88rem;
            color: var(--gray-300);
            max-width: 320px;
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
                <h1>AI Quiz Generator</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>

            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current"><i class="fas fa-wand-magic-sparkles"></i> Generate AI Quiz</span>
            </nav>

            <div class="content-wrapper">
                <div class="recent-section">
                    <h2>
                        <i class="fas fa-brain"></i> Automatic Question Generator
                    </h2>
                    <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 20px;">
                        Select a module chapter to pull lesson content and automatically generate targeted questions using Gemini AI.
                    </p>

                    <div class="form-group">
                        <label for="chapterSelect"><i class="fas fa-book"></i> Select Chapter Module:</label>
                        <select id="chapterSelect" onchange="loadChapterText()">
                            <option value="">-- Choose Module --</option>
                            <?php foreach ($chapters as $ch): ?>
                                <option value="<?php echo $ch['chapter_id']; ?>" data-content="<?php echo htmlspecialchars($ch['lesson_content'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($ch['chapter_title']); ?> 
                                    <?php echo empty($ch['lesson_content']) ? ' (No text stored)' : ' (' . number_format($ch['text_len']) . ' chars)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Stored Lesson Content Context:</label>
                        <textarea id="dbContentPreview" rows="4" readonly placeholder="Select a chapter above to view stored content..."></textarea>
                    </div>

                    <div class="controls-grid">
                        <div>
                            <label for="questionCount"><i class="fas fa-list-ol"></i> Quantity:</label>
                            <select id="questionCount">
                                <option value="3" selected>3 Questions</option>
                                <option value="5">5 Questions</option>
                                <option value="10">10 Questions</option>
                            </select>
                        </div>
                        <div>
                            <label for="formatType"><i class="fas fa-sliders"></i> Format Type:</label>
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
                </div>

                <!-- Generated Questions Output Section -->
                <div id="outputContainer" style="display: none; margin-top: 24px;" class="recent-section">
                    <h2><i class="fas fa-circle-check" style="color: var(--primary-color);"></i> Generated Quiz Items</h2>
                    <div id="questionsList"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- FULLSCREEN LOADING ANIMATION OVERLAY -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner-ring"></div>
            <i class="fas fa-brain"></i>
        </div>
        <div class="loading-title">Generating Questions...</div>
        <div class="loading-sub">Analyzing lesson text and building AI evaluation items. Please hold on!</div>
    </div>

<script>
function loadChapterText() {
    const select = document.getElementById('chapterSelect');
    const selectedOption = select.options[select.selectedIndex];
    const content = selectedOption.dataset.content || '';
    document.getElementById('dbContentPreview').value = content;
}

async function generateQuestions() {
    let rawText = document.getElementById('dbContentPreview').value.trim();
    const count = document.getElementById('questionCount').value;
    const format = document.getElementById('formatType').value;
    const btn = document.getElementById('btnGenerate');
    const loadingOverlay = document.getElementById('loadingOverlay');

    if (!rawText) {
        Swal.fire({
            icon: 'warning',
            title: 'No Content Selected',
            text: 'Please select a chapter that contains stored lesson content or upload a PDF first.',
            confirmColor: '#10b981'
        });
        return;
    }

    // Limit oversized text context length so response completes quickly
    if (rawText.length > 3000) {
        rawText = rawText.substring(0, 3000);
    }

    // Hide output, disable button, and open full-screen loading animation
    document.getElementById('outputContainer').style.display = 'none';
    btn.disabled = true;
    loadingOverlay.style.display = 'flex';

    try {
        const response = await fetch('generate_ai_questions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                contextText: rawText, 
                count: parseInt(count),
                formatType: format 
            })
        });

        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonErr) {
            throw new Error("Server returned non-JSON output.");
        }

        if (data.success) {
            renderQuestions(data.questions);

            Swal.fire({
                icon: 'success',
                title: 'Questions Ready!',
                text: `Successfully generated ${data.questions.length} question(s).`,
                confirmColor: '#10b981',
                timer: 2000
            });
        } else {
            // Check for Limit / Quota Reached or Exceeded
            const errLower = (data.error || '').toLowerCase();
            if (errLower.includes('quota') || errLower.includes('limit') || errLower.includes('429') || errLower.includes('exhausted')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Daily API Limit Reached',
                    text: 'The Gemini AI daily quota or rate limit has been reached. Please try again in a few minutes or tomorrow.',
                    confirmColor: '#ef4444'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Error',
                    text: data.error || 'Failed to generate questions.',
                    confirmColor: '#ef4444'
                });
            }
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            text: err.message || 'Network connection failed.',
            confirmColor: '#ef4444'
        });
    } finally {
        btn.disabled = false;
        loadingOverlay.style.display = 'none';
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