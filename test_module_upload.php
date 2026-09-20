<?php
session_start();
require_once 'config/database.php';

// Temporarily bypassed auth check for free testing
// require_once 'includes/auth_check.php';
// checkTeacherAuth();

$db = Database::getInstance()->getConnection();

// Fetch chapters from database
$chapters = $db->query("SELECT chapter_id, chapter_title, pdf_file_path, CHAR_LENGTH(lesson_content) AS text_len FROM chapters ORDER BY chapter_order ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module PDF Upload Test (Free Access)</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px; }
        .card { max-width: 600px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem; }
        select, input[type="file"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .btn { background: #059669; color: white; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; cursor: pointer; }
        .btn:disabled { background: #9ca3af; }
        .status { margin-top: 16px; padding: 12px; border-radius: 6px; font-size: 0.85rem; display: none; }
        .status.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .status.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body>

<div class="card">
    <h2>📄 Upload Module PDF</h2>
    <p style="font-size: 0.85rem; color: #64748b;">Select Module 1, choose your soft copy PDF file, and save it to the database.</p>

    <form id="uploadForm" onsubmit="uploadPDF(event)">
        <div class="form-group">
            <label>Select Target Chapter:</label>
            <select id="chapterSelect" name="chapter_id" required>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?php echo $ch['chapter_id']; ?>">
                        <?php echo htmlspecialchars($ch['chapter_title']); ?> 
                        <?php echo !empty($ch['pdf_file_path']) ? ' (PDF Saved: ' . $ch['text_len'] . ' chars)' : ' (No PDF yet)'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Select Module PDF File:</label>
            <input type="file" id="pdfFile" name="module_pdf" accept=".pdf" required>
        </div>

        <button type="submit" id="btnSubmit" class="btn">Upload & Extract Text</button>
    </form>

    <div id="statusBox" class="status"></div>
</div>

<script>
async function uploadPDF(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    const status = document.getElementById('statusBox');
    const form = document.getElementById('uploadForm');

    btn.disabled = true;
    btn.textContent = 'Uploading & Extracting...';
    status.style.display = 'none';

    const formData = new FormData(form);

    try {
        const response = await fetch('process_module_pdf.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        status.style.display = 'block';
        if (data.success) {
            status.className = 'status success';
            status.innerHTML = `<strong>Success!</strong> ${data.message}<br><strong>Extracted Length:</strong> ${data.text_length} characters.<br><br><em>Preview:</em> "${data.preview}"`;
        } else {
            status.className = 'status error';
            status.innerHTML = `<strong>Error:</strong> ${data.error}`;
        }
    } catch (err) {
        status.style.display = 'block';
        status.className = 'status error';
        status.innerHTML = '<strong>Error:</strong> Network request failed or invalid server response.';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Upload & Extract Text';
    }
}
</script>

</body>
</html>