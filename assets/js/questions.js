// ============ FILE CLEAR FUNCTION FOR UPLOAD MODAL ============
function clearFile() {
    const fileInput = document.getElementById('excelFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    if (fileInput) fileInput.value = '';
    if (fileInfo) fileInfo.style.display = 'none';
    if (fileName) fileName.textContent = '';
    // Show the upload area again
    const fileUploadArea = document.getElementById('fileUploadArea');
    if (fileUploadArea) {
        const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');
        if (fileUploadContent) fileUploadContent.style.display = '';
    }
    // Also remove any validation message
    const validationMsg = document.getElementById('validationMessage');
    if (validationMsg) {
        validationMsg.remove();
    }
}

function resolveMCQCorrectChoice(choices, correct) {
    const normalizedChoices = choices.map(choice => String(choice ?? '').trim());
    const duplicateChoices = normalizedChoices.some((choice, index) =>
        choice && normalizedChoices.slice(0, index).some(previous => previous.toLowerCase() === choice.toLowerCase())
    );

    if (duplicateChoices) {
        return { index: -1, error: 'MCQ choices must not be repeated' };
    }

    const correctValue = String(correct ?? '').trim();
    const numericIndex = /^\d+$/.test(correctValue) ? Number(correctValue) : -1;
    if (numericIndex >= 0 && numericIndex < normalizedChoices.length) {
        return { index: numericIndex, error: '' };
    }

    const textIndex = normalizedChoices.findIndex(choice => choice.toLowerCase() === correctValue.toLowerCase());
    if (textIndex !== -1) {
        return { index: textIndex, error: '' };
    }

    return {
        index: -1,
        error: `The answer '${correctValue}' must match one of the choices or be a valid index (0-${normalizedChoices.length - 1})`
    };
}

async function readQuestionSpreadsheetRows(file) {
    if (typeof XLSX === 'undefined') {
        throw new Error('XLSX library is not available. Refresh the page and try again.');
    }

    const data = await file.arrayBuffer();
    const workbook = XLSX.read(new Uint8Array(data), { type: 'array' });
    const sheetName = workbook.SheetNames[0];
    if (!sheetName) throw new Error('The workbook does not contain a worksheet.');
    return XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { header: 1, blankrows: false });
}

/**
 * Question Management JavaScript
 * Handles CRUD operations for questions and choices
 */

// API Base URL
const API_URL = '../api/question_api.php';
let choiceIndex = 4;

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    // Ensure correct fields are shown in Add Question modal on load
    if (document.getElementById('questionType')) {
        toggleChoicesSection();
    }

    // Render questionsData into the questionsContainer on page load
    if (typeof questionsData !== 'undefined' && Array.isArray(questionsData)) {
        const container = document.getElementById('questionsContainer');
        if (container && !container.querySelector('.question-card')) {
            renderQuestionsList(questionsData, typeof choicesData !== 'undefined' ? choicesData : {});
        }
    }
    filterQuestions();

    // Initialize file upload area
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('excelFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileUploadLink = document.querySelector('.file-upload-link');

    if (fileUploadArea && fileInput) {
        // Click to browse
        fileUploadArea.addEventListener('click', function(e) {
            const clickedContent = e.target instanceof Element ? e.target.closest('.file-upload-content') : null;
            if (e.target === fileUploadLink || clickedContent) {
                fileInput.click();
            }
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls') || file.name.endsWith('.csv'))) {
                // Create a new FileList with the selected file
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;

                fileName.textContent = file.name;
                fileInfo.style.display = 'flex';
                const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');
                if (fileUploadContent) fileUploadContent.style.display = 'none';

                // Immediately validate the Excel file content
                validateExcelFile(file);
            } else {
                showToast('Please select a valid Excel file (.xlsx or .xls)', 'error');
            }
        }
    }
});

// ============ EXCEL FILE VALIDATION ON SELECT ============
async function validateExcelFile(file) {
    try {
        let jsonData = [];
        if (file.name.endsWith('.csv')) {
            // Read CSV text and parse to array of rows
            const text = await file.text();
            jsonData = parseCSV(text);
        } else {
            // Attempt to read XLSX/XLS using XLSX lib if available
            if (typeof XLSX === 'undefined') {
                showToast('XLSX library not available. Please upload a CSV file instead.', 'error');
                return;
            }
            jsonData = await readQuestionSpreadsheetRows(file);
        }

        if (jsonData.length < 2) {
            showValidationError(['Excel file must contain at least a header row and one data row']);
            return;
        }

        // Detect header format
        const header = jsonData[0].map(h => String(h ?? '').toLowerCase().trim());
        let formatType = null;
        if (
            header.includes('question type') &&
            header.includes('question text') &&
            header.includes('choice1') &&
            header.includes('choice2') &&
            header.includes('choice3') &&
            header.includes('choice4') &&
            header.includes('correct choice')
        ) {
            formatType = 'expected';
        } else if (
            header.includes('question') &&
            header.includes('question type') &&
            header.includes('option a') &&
            header.includes('option b') &&
            header.includes('option c') &&
            header.includes('option d') &&
            header.includes('answer')
        ) {
            formatType = 'custom';
        } else {
            showValidationError(['Excel file headers do not match any supported format. Please check the template.']);
            return;
        }

        // Remove header row
        jsonData.shift();

        // Validate and collect errors
        const validationErrors = [];
        for (let i = 0; i < jsonData.length; i++) {
            const row = jsonData[i];
            if (!row || row.every(value => String(value ?? '').trim() === '')) {
                continue;
            }
            let debugReason = '';
            if (formatType === 'expected') {
                const [type, text, choice1, choice2, choice3, choice4, correct] = row;
                if (!type || !text) { debugReason = 'Missing question type or text'; }
                if (type === 'mcq') {
                    if (!choice1 || !choice2 || !choice3 || !choice4 || !correct) { 
                        debugReason = 'MCQ missing choices or correct answer'; 
                    } else {
                        const choices = [String(choice1).trim(), String(choice2).trim(), String(choice3).trim(), String(choice4).trim()];
                        const emptyChoices = choices.filter(c => c === '');
                        if (emptyChoices.length > 0) {
                            debugReason = 'MCQ cannot have empty choices';
                        } else {
                            debugReason = resolveMCQCorrectChoice(choices, correct).error;
                        }
                    }
                } else if (type === 'true_false') {
                    const tfVal = String(correct).trim().toLowerCase();
                    if (tfVal !== 'true' && tfVal !== 'false' && tfVal !== 't' && tfVal !== 'f') {
                        debugReason = 'True/False answer must be true/false';
                    }
                } else if (type === 'short_answer') {
                    if (!correct) { debugReason = 'Short answer missing correct answer'; }
                } else if (!type) {
                    debugReason = 'Invalid question type';
                }
            } else if (formatType === 'custom') {
                const col = {};
                header.forEach((h, idx) => { col[h] = idx; });
                const type = row[col['question type']];
                const text = row[col['question']];
                const choice1 = row[col['option a']];
                const choice2 = row[col['option b']];
                const choice3 = row[col['option c']];
                const choice4 = row[col['option d']];
                const correct = row[col['answer']];
                if (!type || !text) { debugReason = 'Missing question type or text'; }
                if (String(type).trim().toLowerCase() === 'mcq') {
                    if (!choice1 || !choice2 || !choice3 || !choice4 || !correct) { 
                        debugReason = 'MCQ missing choices or correct answer'; 
                    } else {
                        const choices = [String(choice1).trim(), String(choice2).trim(), String(choice3).trim(), String(choice4).trim()];
                        const emptyChoices = choices.filter(c => c === '');
                        if (emptyChoices.length > 0) {
                            debugReason = 'MCQ cannot have empty choices';
                        } else {
                            debugReason = resolveMCQCorrectChoice(choices, correct).error;
                        }
                    }
                } else if (String(type).trim().toLowerCase() === 'true_false') {
                    const tfVal = String(correct).trim().toLowerCase();
                    if (tfVal !== 'true' && tfVal !== 'false' && tfVal !== 't' && tfVal !== 'f') {
                        debugReason = 'True/False answer must be true/false';
                    }
                } else if (String(type).trim().toLowerCase() === 'short_answer') {
                    if (!correct) { debugReason = 'Short answer missing correct answer'; }
                } else if (!type) {
                    debugReason = 'Invalid question type';
                }
            }
            if (debugReason) {
                validationErrors.push(`Row ${i + 2}: ${debugReason}`);
            }
        }

        if (validationErrors.length > 0) {
            showValidationError(validationErrors);
        } else {
            showValidationSuccess();
        }

    } catch (error) {
        const errorMessage = error && error.message ? error.message : 'Unknown spreadsheet parsing error';
        showValidationError([`Error reading Excel file: ${errorMessage}`]);
        console.error('Excel validation error:', error);
    }
}

// Helper function to show validation errors
function showValidationError(errors) {
    const existingMsg = document.getElementById('validationMessage');
    if (existingMsg) existingMsg.remove();

    const validationMsg = document.createElement('div');
    validationMsg.id = 'validationMessage';
    validationMsg.style.cssText = 'margin: 15px 0; padding: 12px; background: #ffeaea; border: 2px solid #ff4444; border-radius: 6px;';

        const errorList = errors.map(error => `<li style="color: #cc0000; margin: 5px 0;">${escapeHtml(error)}</li>`).join('');
    validationMsg.innerHTML = `
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <span style="font-size: 18px; margin-right: 8px;">❌</span>
            <strong style="color: #cc0000;">Validation Errors Found</strong>
        </div>
        <ul style="margin: 0; padding-left: 20px;">
            ${errorList}
        </ul>
        <div style="margin-top: 10px; font-size: 14px; color: #666;">
            Please fix these errors in your Excel file before uploading.
        </div>
    `;

    const fileInfo = document.getElementById('fileInfo');
    if (fileInfo) {
        fileInfo.parentNode.insertBefore(validationMsg, fileInfo.nextSibling);
    }

    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Fix Errors First';
        uploadBtn.style.backgroundColor = '#ccc';
    }

    showToast('❌ Validation failed! Please check the errors above.', 'error');
}

// Helper function to show validation success
function showValidationSuccess() {
    const existingMsg = document.getElementById('validationMessage');
    if (existingMsg) existingMsg.remove();

    const validationMsg = document.createElement('div');
    validationMsg.id = 'validationMessage';
    validationMsg.style.cssText = 'margin: 15px 0; padding: 12px; background: #eaffea; border: 2px solid #44ff44; border-radius: 6px;';

    validationMsg.innerHTML = `
        <div style="display: flex; align-items: center;">
            <span style="font-size: 18px; margin-right: 8px;">✅</span>
            <strong style="color: #00aa00;">File Validated Successfully!</strong>
            <span style="margin-left: 10px; font-size: 14px; color: #666;">Ready to upload.</span>
        </div>
    `;

    const fileInfo = document.getElementById('fileInfo');
    if (fileInfo) {
        fileInfo.parentNode.insertBefore(validationMsg, fileInfo.nextSibling);
    }

    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload & Import';
        uploadBtn.style.backgroundColor = '';
    }

    showToast('✅ Excel file validated successfully!', 'success');
}

// Render questions list from questionsData and choicesData (INJECTS data-ai AND BADGES)
function renderQuestionsList(questions, choices) {
    const container = document.getElementById('questionsContainer');
    if (!container) return;
    if (!questions || questions.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-question-circle"></i>
                <h3>No questions yet</h3>
                <p>Create your first question by clicking the button above!</p>
            </div>
        `;
        return;
    }
    let html = '';
    questions.forEach(q => {
        // Detect AI status strictly
        const isAi = (q.is_ai_generated == 1 || (q.source && q.source.toLowerCase() === 'ai_generated')) ? '1' : '0';
        
        html += `
        <div class="question-card" data-id="${q.question_id}"
             data-lesson="${q.lesson_id}"
             data-type="${q.question_type}"
             data-visibility="${q.visibility || 'private'}"
             data-ai="${isAi}"
             data-archived="${q.is_archived == 1 ? '1' : '0'}"
             data-search="${(q.question_text + ' ' + (q.lesson_title || '') + ' ' + (q.chapter_title || '')).toLowerCase().replace(/\"/g, '&quot;')}">
            <div class="question-header">
                <div class="question-meta">
                    <span class="badge badge-${q.question_type}">${capitalize(q.question_type.replace('_',' '))}</span>
                    ${isAi === '1' 
                        ? `<span class="source-badge badge-source-ai"><i class="fas fa-sparkles"></i> AI Generated</span>` 
                        : `<span class="source-badge badge-source-manual"><i class="fas fa-user-pen"></i> Manual</span>`}
                    <span class="badge badge-${q.visibility || 'private'}">
                        ${capitalize((q.visibility || 'private'))}
                    </span>
                    <span class="lesson-tag"><i class="fas fa-book"></i> ${escapeHtml(q.lesson_title || '')}</span>
                    <span class="question-teacher">${q.first_name ? 'By: ' + escapeHtml(q.first_name + ' ' + (q.last_name || '')) : ''}</span>
                </div>
                <div class="question-actions">
                    <button class="btn btn-sm btn-info" onclick="editQuestion(${q.question_id})"><i class="fas fa-edit"></i></button>
                    ${(typeof isAdmin !== 'undefined' && isAdmin) || q.created_by_teacher_id == currentTeacherId ? `<button class="btn btn-sm ${q.is_archived == 1 ? 'btn-success' : 'btn-danger'}" onclick="toggleArchiveQuestion(${q.question_id}, ${q.is_archived == 1 ? 'true' : 'false'})"><i class="fas ${q.is_archived == 1 ? 'fa-box-open' : 'fa-box-archive'}"></i></button>` : ''}
                </div>
            </div>
            <div class="question-text">${escapeHtml(q.question_text)}</div>
            ${choices[q.question_id] ? `<div class="choices-preview">${choices[q.question_id].map(choice => `<div class="choice-item ${choice.is_correct ? 'correct' : ''}">${choice.is_correct ? '<i class=\"fas fa-check-circle\"></i>' : '<i class=\"far fa-circle\"></i>'}${escapeHtml(choice.choice_text)}</div>`).join('')}</div>` : ''}
        </div>
        `;
    });
    container.innerHTML = html;
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = 'auto';
    
    const form = document.getElementById(modalId)?.querySelector('form');
    if (form) {
        form.reset();
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => clearFieldHighlight(input));
        if (modalId === 'addQuestionModal') {
            resetChoices();
        }
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            closeModal(modal.id);
        });
    }
});

// ============ TOAST NOTIFICATIONS ============

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) {
        console.warn(message);
        return;
    }
    const toastMessage = toast.querySelector('.toast-message');
    if (!toastMessage) return;
    
    toast.className = 'toast show ' + type;
    toastMessage.textContent = message;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ============ QUESTION TYPE TOGGLE ============

function toggleChoicesSection() {
    const questionType = document.getElementById('questionType').value;
    const choicesSection = document.getElementById('choicesSection');
    const trueFalseSection = document.getElementById('trueFalseSection');
    const shortAnswerSection = document.getElementById('shortAnswerSection');
    const choiceInputs = document.querySelectorAll('#choicesSection input[name="choices[]"]');
    const shortAnswerInput = document.getElementById('shortAnswerInput');

    if (questionType === 'mcq') {
        choicesSection.style.display = 'block';
        trueFalseSection.style.display = 'none';
        shortAnswerSection.style.display = 'none';
        choiceInputs.forEach(input => input.required = true);
        if (shortAnswerInput) shortAnswerInput.required = false;
    } else if (questionType === 'true_false') {
        choicesSection.style.display = 'none';
        trueFalseSection.style.display = 'block';
        shortAnswerSection.style.display = 'none';
        choiceInputs.forEach(input => input.required = false);
        if (shortAnswerInput) shortAnswerInput.required = false;
    } else if (questionType === 'short_answer') {
        choicesSection.style.display = 'none';
        trueFalseSection.style.display = 'none';
        shortAnswerSection.style.display = 'block';
        choiceInputs.forEach(input => input.required = false);
        if (shortAnswerInput) shortAnswerInput.required = true;
    } else {
        choicesSection.style.display = 'none';
        trueFalseSection.style.display = 'none';
        shortAnswerSection.style.display = 'none';
        choiceInputs.forEach(input => input.required = false);
        if (shortAnswerInput) shortAnswerInput.required = false;
    }
}

function toggleEditChoicesSection() {
    const questionType = document.getElementById('editQuestionType').value;
    const choicesSection = document.getElementById('editChoicesSection');
    const trueFalseSection = document.getElementById('editTrueFalseSection');
    const shortAnswerSection = document.getElementById('editShortAnswerSection');
    const choiceInputs = document.querySelectorAll('#editChoicesSection input[name="choices[]"]');
    const shortAnswerInput = document.getElementById('editShortAnswerInput');

    if (questionType === 'mcq') {
        choicesSection.style.display = 'block';
        trueFalseSection.style.display = 'none';
        shortAnswerSection.style.display = 'none';
        choiceInputs.forEach(input => input.required = true);
        if (shortAnswerInput) shortAnswerInput.required = false;
    } else if (questionType === 'true_false') {
        choicesSection.style.display = 'none';
        trueFalseSection.style.display = 'block';
        shortAnswerSection.style.display = 'none';
        choiceInputs.forEach(input => input.required = false);
        if (shortAnswerInput) shortAnswerInput.required = false;
    } else if (questionType === 'short_answer') {
        choicesSection.style.display = 'none';
        trueFalseSection.style.display = 'none';
        shortAnswerSection.style.display = 'block';
        choiceInputs.forEach(input => input.required = false);
        if (shortAnswerInput) shortAnswerInput.required = true;
    } else {
        choicesSection.style.display = 'none';
        trueFalseSection.style.display = 'none';
        shortAnswerSection.style.display = 'none';
        choiceInputs.forEach(input => input.required = false);
        if (shortAnswerInput) shortAnswerInput.required = false;
    }
}

// ============ CHOICE MANAGEMENT ============

function resetChoices() {
    const container = document.getElementById('choicesContainer');
    container.innerHTML = `
        <div class="choice-row">
            <input type="radio" name="correct_choice" value="0" checked>
            <input type="text" name="choices[]" value="Option A" placeholder="Choice A" required>
        </div>
        <div class="choice-row">
            <input type="radio" name="correct_choice" value="1">
            <input type="text" name="choices[]" value="Option B" placeholder="Choice B" required>
        </div>
        <div class="choice-row">
            <input type="radio" name="correct_choice" value="2">
            <input type="text" name="choices[]" value="Option C" placeholder="Choice C" required>
        </div>
        <div class="choice-row">
            <input type="radio" name="correct_choice" value="3">
            <input type="text" name="choices[]" value="Option D" placeholder="Choice D" required>
        </div>
    `;
    choiceIndex = 4;
    updateChoiceButtonState();
    toggleChoicesSection();
}

function addChoice() {
    const container = document.getElementById('choicesContainer');
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const currentCount = container.querySelectorAll('.choice-row').length;

    if (currentCount >= 4) {
        showToast('Multiple-choice questions are limited to 4 options.', 'error');
        return;
    }

    const row = document.createElement('div');
    row.className = 'choice-row';
    row.innerHTML = `
        <input type="radio" name="correct_choice" value="${currentCount}">
        <input type="text" name="choices[]" placeholder="Choice ${letters[currentCount]}" required>
    `;

    container.appendChild(row);
    choiceIndex++;
    updateChoiceButtonState();
}

function updateChoiceButtonState() {
    const container = document.getElementById('choicesContainer');
    const addButton = document.getElementById('addChoiceButton');
    const count = container.querySelectorAll('.choice-row').length;
    if (!addButton) return;

    addButton.disabled = count >= 4;
    addButton.style.opacity = count >= 4 ? '0.65' : '1';
}

function addEditChoice() {
    const container = document.getElementById('editChoicesContainer');
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const currentCount = container.querySelectorAll('.choice-row').length;

    if (currentCount >= 4) {
        showToast('Multiple-choice questions are limited to 4 options.', 'error');
        return;
    }

    const row = document.createElement('div');
    row.className = 'choice-row';
    row.innerHTML = `
        <input type="radio" name="edit_correct_choice" value="${currentCount}">
        <input type="text" name="choices[]" placeholder="Choice ${letters[currentCount]}" required>
    `;

    container.appendChild(row);
}

function reindexChoices() {
    const rows = document.querySelectorAll('#choicesContainer .choice-row');
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    rows.forEach((row, index) => {
        row.querySelector('input[type="radio"]').value = index;
        row.querySelector('input[type="text"]').placeholder = `Choice ${letters[index]}`;
    });
    
    choiceIndex = rows.length;
}

function reindexEditChoices() {
    const rows = document.querySelectorAll('#editChoicesContainer .choice-row');
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    rows.forEach((row, index) => {
        row.querySelector('input[type="radio"]').value = index;
        row.querySelector('input[type="text"]').placeholder = `Choice ${letters[index]}`;
    });
}

// ============ FILTER FUNCTIONS ============

function initializeFilters() {
    // Filters are initialized via select onChange triggers
}

function filterQuestions() {
    const lessonFilter = document.getElementById('filterLesson')?.value || '';
    const typeFilter = document.getElementById('filterType')?.value || '';
    const visibilityFilter = document.getElementById('filterVisibility')?.value || '';
    const sourceFilter = document.getElementById('filterSource')?.value || '';
    const archiveFilter = document.getElementById('filterArchive')?.value || 'active';
    const searchFilter = document.getElementById('liveSearchInput')?.value.toLowerCase().trim() || '';
    
    const cards = document.querySelectorAll('.question-card');
    
    cards.forEach(card => {
        const lesson = card.dataset.lesson;
        const type = card.dataset.type;
        const visibility = card.dataset.visibility;
        const isAi = card.dataset.ai; // Reads '1' or '0'
        const isArchived = card.dataset.archived || '0';
        const searchText = card.textContent.toLowerCase();
        
        let show = true;
        
        if (lessonFilter && lesson !== lessonFilter) show = false;
        if (typeFilter && type !== typeFilter) show = false;
        if (visibilityFilter && visibility !== visibilityFilter) show = false;
        if (archiveFilter === 'active' && isArchived === '1') show = false;
        if (archiveFilter === 'archived' && isArchived !== '1') show = false;
        if (searchFilter && !searchText.includes(searchFilter)) show = false;
        
        // Strict Source Enforcement
        if (sourceFilter === 'ai' && isAi !== '1') show = false;
        if (sourceFilter === 'manual' && isAi === '1') show = false;
        
        card.style.display = show ? 'block' : 'none';
    });
}

// Live Search Input Handler
function liveSearchQuestions() {
    const query = (document.getElementById('liveSearchInput')?.value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.question-card');

    cards.forEach(card => {
        const searchText = card.getAttribute('data-search') || card.textContent.toLowerCase();
        if (query === '' || searchText.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });

    // Re-apply filters on top of search
    filterQuestions();
}

// ============ VALIDATION HELPERS ============

function highlightField(field, isValid = false) {
    if (isValid) {
        field.classList.remove('error');
        field.classList.add('valid');
    } else {
        field.classList.remove('valid');
        field.classList.add('error');
    }
}

function clearFieldHighlight(field) {
    field.classList.remove('error', 'valid');
}

async function submitQuestion(event) {
    event.preventDefault();
    const form = event.target;
    
    if (!form.question_text.value.trim()) {
        showToast('Please enter a question text', 'error');
        form.question_text.focus();
        highlightField(form.question_text, false);
        return;
    } else {
        highlightField(form.question_text, true);
    }
    
    if (!form.lesson_id.value) {
        showToast('Please select a lesson', 'error');
        form.lesson_id.focus();
        highlightField(form.lesson_id, false);
        return;
    } else {
        highlightField(form.lesson_id, true);
    }
    
    const formData = new FormData();
    formData.append('action', 'create_question');
    formData.append('lesson_id', form.lesson_id.value);
    formData.append('question_text', form.question_text.value.trim());
    formData.append('question_type', form.question_type.value);
    formData.append('visibility', form.visibility.value);

    if (form.author_teacher_id) {
        formData.append('author_teacher_id', form.author_teacher_id.value);
    }

    if (form.question_type.value === 'mcq') {
        const choices = form.querySelectorAll('input[name="choices[]"]');
        const filledChoices = Array.from(choices).filter(choice => choice.value.trim() !== '');
        
        if (filledChoices.length < 4) {
            showToast('Multiple choice questions must have at least 4 answer choices', 'error');
            return;
        }
        
        const choiceTexts = filledChoices.map(choice => choice.value.trim().toLowerCase());
        const uniqueChoices = new Set(choiceTexts);
        if (uniqueChoices.size !== choiceTexts.length) {
            showToast('Answer choices must be unique (no duplicates allowed)', 'error');
            return;
        }
        
        const correctChoice = form.querySelector('input[name="correct_choice"]:checked');
        if (!correctChoice) {
            showToast('Please select the correct answer for the multiple choice question', 'error');
            return;
        }
        
        const correctChoiceIndex = parseInt(correctChoice.value);
        const correctChoiceText = choices[correctChoiceIndex]?.value?.trim();
        if (!correctChoiceText) {
            showToast('The selected correct answer must have text entered', 'error');
            return;
        }
        
        choices.forEach(choice => formData.append('choices[]', choice.value));
        formData.append('correct_choice', correctChoice.value);
    } else if (form.question_type.value === 'true_false') {
        const tfAnswer = form.querySelector('input[name="tf_answer"]:checked');
        formData.append('tf_answer', tfAnswer ? tfAnswer.value : 'true');
    } else if (form.question_type.value === 'short_answer') {
        const shortAnswer = form.short_answer.value.trim();
        if (!shortAnswer) {
            showToast('Please provide the correct answer for the short answer question', 'error');
            form.short_answer.focus();
            highlightField(form.short_answer, false);
            return;
        }
        highlightField(form.short_answer, true);
        formData.append('short_answer', shortAnswer);
    }

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            showAddQuestionSuccessMessage(result.message);
            resetAddQuestionForm(form, true);

            const newQuestionId = result.data?.question_id;
            if (newQuestionId) {
                const question = await fetchQuestionById(newQuestionId);
                if (question) {
                    appendQuestionCard(question, question.choices || []);
                } else {
                    await refreshQuestionsList();
                }
            } else {
                await refreshQuestionsList();
            }
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

function resetAddQuestionForm(form, preserveSuccessMessage = false) {
    form.reset();
    resetChoices();
    if (typeof toggleChoicesSection === 'function') {
        toggleChoicesSection();
    }

    if (!preserveSuccessMessage) {
        const successMessage = document.getElementById('addQuestionSuccessMessage');
        if (successMessage) {
            successMessage.style.display = 'none';
        }
    }

    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => clearFieldHighlight(input));
}

function showAddQuestionSuccessMessage(message) {
    const successMessage = document.getElementById('addQuestionSuccessMessage');
    if (!successMessage) return;

    successMessage.textContent = message || 'Question added successfully.';
    successMessage.style.display = 'block';

    setTimeout(() => {
        successMessage.style.display = 'none';
    }, 4000);
}

async function fetchQuestionById(questionId) {
    try {
        const response = await fetch(`${API_URL}?action=get_question&id=${questionId}`);
        const result = await response.json();
        return result.success ? result.data : null;
    } catch (error) {
        console.error('Failed to fetch new question:', error);
        return null;
    }
}

function appendQuestionCard(question, choices = []) {
    const container = document.getElementById('questionsContainer');
    if (!container || !question) return;

    if (container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }

    const html = generateQuestionCardHtml(question, choices);
    container.insertAdjacentHTML('afterbegin', html);
}

function generateQuestionCardHtml(q, choices = []) {
    const isAi = (q.is_ai_generated == 1 || (q.source && q.source.toLowerCase() === 'ai_generated')) ? '1' : '0';

    const choicesHtml = Array.isArray(choices) && choices.length ? `
            <div class="choices-preview">
                ${choices.map(choice => `<div class="choice-item ${choice.is_correct ? 'correct' : ''}">${choice.is_correct ? '<i class="fas fa-check-circle"></i>' : '<i class="far fa-circle"></i>'}${escapeHtml(choice.choice_text)}</div>`).join('')}
            </div>
        ` : '';

    return `
        <div class="question-card" data-id="${q.question_id}"
             data-lesson="${q.lesson_id}"
             data-type="${q.question_type}"
             data-visibility="${q.visibility || 'private'}"
             data-ai="${isAi}"
             data-archived="${q.is_archived == 1 ? '1' : '0'}"
             data-search="${(q.question_text + ' ' + (q.lesson_title || '') + ' ' + (q.chapter_title || '')).toLowerCase().replace(/\"/g, '&quot;')}">
            <div class="question-header">
                <div class="question-meta">
                    <span class="badge badge-${q.question_type}">${capitalize(q.question_type.replace('_',' '))}</span>
                    ${isAi === '1' 
                        ? `<span class="source-badge badge-source-ai"><i class="fas fa-sparkles"></i> AI Generated</span>` 
                        : `<span class="source-badge badge-source-manual"><i class="fas fa-user-pen"></i> Manual</span>`}
                    <span class="badge badge-${q.visibility || 'private'}">${capitalize((q.visibility || 'private'))}</span>
                    <span class="lesson-tag"><i class="fas fa-book"></i> ${escapeHtml(q.lesson_title || '')}</span>
                    <span class="question-teacher">${q.first_name ? 'By: ' + escapeHtml(q.first_name + ' ' + (q.last_name || '')) : ''}</span>
                </div>
                <div class="question-actions">
                    <button class="btn btn-sm btn-info" onclick="editQuestion(${q.question_id})"><i class="fas fa-edit"></i></button>
                    ${(typeof isAdmin !== 'undefined' && isAdmin) || q.created_by_teacher_id == currentTeacherId ? `<button class="btn btn-sm ${q.is_archived == 1 ? 'btn-success' : 'btn-danger'}" onclick="toggleArchiveQuestion(${q.question_id}, ${q.is_archived == 1 ? 'true' : 'false'})"><i class="fas ${q.is_archived == 1 ? 'fa-box-open' : 'fa-box-archive'}"></i></button>` : ''}
                </div>
            </div>
            <div class="question-text">${escapeHtml(q.question_text)}</div>
            ${choicesHtml}
        </div>
    `;
}

async function editQuestion(id) {
    try {
        const response = await fetch(`${API_URL}?action=get_question&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const question = result.data;
            
            document.getElementById('editQuestionId').value = question.question_id;
            document.getElementById('editQuestionLesson').value = question.lesson_id;
            document.getElementById('editQuestionType').value = question.question_type || 'mcq';
            document.getElementById('editQuestionText').value = question.question_text;
            document.getElementById('editQuestionVisibility').value = question.visibility;
            
            const container = document.getElementById('editChoicesContainer');
            container.innerHTML = '';
            
            const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            
            if (question.question_type === 'mcq') {
                if (question.choices && question.choices.length > 0) {
                    question.choices.forEach((choice, index) => {
                        const row = document.createElement('div');
                        row.className = 'choice-row';
                        row.innerHTML = `
                            <input type="radio" name="edit_correct_choice" value="${index}" ${choice.is_correct ? 'checked' : ''}>
                            <input type="text" name="choices[]" value="${escapeHtml(choice.choice_text)}" placeholder="Choice ${letters[index]}" required>
                        `;
                        container.appendChild(row);
                    });
                } else {
                    const defaultValues = ['Option A', 'Option B', 'Option C', 'Option D'];
                    for (let i = 0; i < 4; i++) {
                        const row = document.createElement('div');
                        row.className = 'choice-row';
                        row.innerHTML = `
                            <input type="radio" name="edit_correct_choice" value="${i}" ${i === 0 ? 'checked' : ''}>
                            <input type="text" name="choices[]" value="${defaultValues[i]}" placeholder="Choice ${letters[i]}" required>
                        `;
                        container.appendChild(row);
                    }
                }
            } else if (question.question_type === 'true_false') {
                const correctChoice = question.choices.find(choice => choice.is_correct);
                const tfValue = correctChoice && correctChoice.choice_text.toLowerCase() === 'true' ? 'true' : 'false';
                document.querySelector(`input[name="edit_tf_answer"][value="${tfValue}"]`).checked = true;
            } else if (question.question_type === 'short_answer') {
                const correctChoice = question.choices.find(choice => choice.is_correct);
                document.getElementById('editShortAnswerInput').value = correctChoice ? correctChoice.choice_text : '';
            }
            
            toggleEditChoicesSection();
            openModal('editQuestionModal');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Failed to load question data.', 'error');
        console.error(error);
    }
}

async function updateQuestion(event) {
    event.preventDefault();
    
    const form = event.target;
    
    if (!form.question_text.value.trim()) {
        showToast('Please enter a question text', 'error');
        form.question_text.focus();
        highlightField(form.question_text, false);
        return;
    } else {
        highlightField(form.question_text, true);
    }
    
    if (!form.lesson_id.value) {
        showToast('Please select a lesson', 'error');
        form.lesson_id.focus();
        highlightField(form.lesson_id, false);
        return;
    } else {
        highlightField(form.lesson_id, true);
    }
    
    const formData = new FormData(form);
    formData.append('action', 'update_question');
    
    if (form.question_type.value === 'mcq') {
        const choices = form.querySelectorAll('input[name="choices[]"]');
        const filledChoices = Array.from(choices).filter(choice => choice.value.trim() !== '');
        
        if (filledChoices.length < 4) {
            showToast('Multiple choice questions must have at least 4 answer choices', 'error');
            return;
        }
        
        const choiceTexts = filledChoices.map(choice => choice.value.trim().toLowerCase());
        const uniqueChoices = new Set(choiceTexts);
        if (uniqueChoices.size !== choiceTexts.length) {
            showToast('Answer choices must be unique (no duplicates allowed)', 'error');
            return;
        }
        
        const correctChoice = form.querySelector('input[name="edit_correct_choice"]:checked');
        if (!correctChoice) {
            showToast('Please select the correct answer for the multiple choice question', 'error');
            return;
        }
        
        const correctChoiceIndex = parseInt(correctChoice.value);
        const correctChoiceText = choices[correctChoiceIndex]?.value?.trim();
        if (!correctChoiceText) {
            showToast('The selected correct answer must have text entered', 'error');
            return;
        }
        
        formData.append('correct_choice', correctChoice.value);
    } else if (form.question_type.value === 'true_false') {
        const tfAnswer = form.querySelector('input[name="edit_tf_answer"]:checked');
        formData.append('tf_answer', tfAnswer ? tfAnswer.value : 'true');
    } else if (form.question_type.value === 'short_answer') {
        const shortAnswer = form.edit_short_answer.value.trim();
        if (!shortAnswer) {
            showToast('Please provide the correct answer for the short answer question', 'error');
            form.edit_short_answer.focus();
            highlightField(form.edit_short_answer, false);
            return;
        }
        highlightField(form.edit_short_answer, true);
        formData.append('short_answer', shortAnswer);
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('editQuestionModal');
            await refreshQuestionsList();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

async function confirmCriticalAction(title, text, confirmText = 'Yes, proceed') {
    if (typeof Swal !== 'undefined' && Swal.fire) {
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33'
        });
        return result.isConfirmed;
    }

    return confirm('Are you sure?\n\n' + text);
}

async function toggleArchiveQuestion(id, isArchived = false) {
    const confirmed = await confirmCriticalAction(
        isArchived ? 'Unarchive Question?' : 'Archive Question?',
        isArchived ? 'This question will be returned to the active question list.' : 'Archived questions are hidden from active lists and can be unarchived later.',
        isArchived ? 'Yes, unarchive question' : 'Yes, archive question'
    );
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'toggle_archive_question');
    formData.append('question_id', id);
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            await refreshQuestionsList();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

// ============ EXCEL UPLOAD FUNCTIONS ============

function downloadTemplate() {
    window.location.href = '../api/download_questions_template.php';
}

async function uploadQuestions() {
    const fileInput = document.getElementById('excelFile');
    const lessonSelect = document.getElementById('uploadLesson');
    const visibilitySelect = document.getElementById('uploadVisibility');
    
    const file = fileInput.files[0];
    const lessonId = lessonSelect.value;
    const visibility = visibilitySelect.value;
    
    if (!file) {
        showToast('Please select an Excel file', 'error');
        return;
    }
    
    const validationMsg = document.getElementById('validationMessage');
    if (validationMsg && validationMsg.style.backgroundColor === 'rgb(255, 234, 234)') {
        showToast('Please fix validation errors before uploading', 'error');
        return;
    }
    
    if (!lessonId) {
        showToast('Please select a lesson', 'error');
        return;
    }
    
    if (!visibility) {
        showToast('Please select visibility', 'error');
        return;
    }
    
    try {
        let jsonData = [];
        if (file.name.endsWith('.csv')) {
            const text = await file.text();
            jsonData = parseCSV(text);
        } else {
            if (typeof XLSX === 'undefined') {
                showToast('XLSX library not available. Please upload a CSV file instead.', 'error');
                return;
            }
            jsonData = await readQuestionSpreadsheetRows(file);
        }
        
        if (jsonData.length < 2) {
            showToast('Excel file must contain at least a header row and one data row', 'error');
            return;
        }
        
        const header = jsonData[0].map(h => String(h ?? '').toLowerCase().trim());
        let formatType = null;
        if (
            header.includes('question type') &&
            header.includes('question text') &&
            header.includes('choice1') &&
            header.includes('choice2') &&
            header.includes('choice3') &&
            header.includes('choice4') &&
            header.includes('correct choice')
        ) {
            formatType = 'expected';
        } else if (
            header.includes('question') &&
            header.includes('question type') &&
            header.includes('option a') &&
            header.includes('option b') &&
            header.includes('option c') &&
            header.includes('option d') &&
            header.includes('answer')
        ) {
            formatType = 'custom';
        } else {
            showToast('Excel file headers do not match any supported format.', 'error');
            return;
        }

        jsonData.shift();

        const questions = [];
        const errorRows = [];
        for (let i = 0; i < jsonData.length; i++) {
            const row = jsonData[i];
            if (!row || row.every(value => String(value ?? '').trim() === '')) {
                continue;
            }
            let question = null;
            let debugReason = '';
            if (formatType === 'expected') {
                const [type, text, choice1, choice2, choice3, choice4, correct] = row;
                if (!type || !text) { debugReason = 'Missing type or text'; }
                question = {
                    lesson_id: parseInt(lessonId),
                    question_type: type ? type.trim() : '',
                    question_text: text ? text.trim() : '',
                    visibility: visibility,
                    choices: [],
                    correct_choice: null
                };
                if (type === 'mcq') {
                    if (!choice1 || !choice2 || !choice3 || !choice4 || !correct) { debugReason = 'MCQ missing choices or correct'; }
                    question.choices = [String(choice1).trim(), String(choice2).trim(), String(choice3).trim(), String(choice4).trim()];
                    if (!debugReason) {
                        const result = resolveMCQCorrectChoice(question.choices, correct);
                        if (result.error) {
                            debugReason = result.error;
                        } else {
                            question.correct_choice = result.index;
                        }
                    }
                } else if (type === 'true_false') {
                    const tfVal = String(correct).trim().toLowerCase();
                    if (tfVal === 'true' || tfVal === 't') {
                        question.correct_choice = 'true';
                    } else if (tfVal === 'false' || tfVal === 'f') {
                        question.correct_choice = 'false';
                    } else {
                        debugReason = 'TF correct not true/false';
                    }
                } else if (type === 'short_answer') {
                    if (!correct) { debugReason = 'Short answer missing correct'; }
                    question.correct_choice = correct ? String(correct).trim() : '';
                } else if (!type) {
                    debugReason = 'Invalid type';
                }
            } else if (formatType === 'custom') {
                const col = {};
                header.forEach((h, idx) => { col[h] = idx; });
                const type = row[col['question type']];
                const text = row[col['question']];
                const choice1 = row[col['option a']];
                const choice2 = row[col['option b']];
                const choice3 = row[col['option c']];
                const choice4 = row[col['option d']];
                const correct = row[col['answer']];
                if (!type || !text) { debugReason = 'Missing type or text'; }
                question = {
                    lesson_id: parseInt(lessonId),
                    question_type: type ? String(type).trim() : '',
                    question_text: text ? String(text).trim() : '',
                    visibility: visibility,
                    choices: [],
                    correct_choice: null
                };
                if (question.question_type === 'mcq') {
                    if (!choice1 || !choice2 || !choice3 || !choice4 || !correct) { debugReason = 'MCQ missing choices or correct'; }
                    question.choices = [String(choice1).trim(), String(choice2).trim(), String(choice3).trim(), String(choice4).trim()];
                    if (!debugReason) {
                        const result = resolveMCQCorrectChoice(question.choices, correct);
                        if (result.error) {
                            debugReason = result.error;
                        } else {
                            question.correct_choice = result.index;
                        }
                    }
                } else if (question.question_type === 'true_false') {
                    const tfVal = String(correct).trim().toLowerCase();
                    if (tfVal === 'true' || tfVal === 't') {
                        question.correct_choice = 'true';
                    } else if (tfVal === 'false' || tfVal === 'f') {
                        question.correct_choice = 'false';
                    } else {
                        debugReason = 'TF correct not true/false';
                    }
                } else if (question.question_type === 'short_answer') {
                    if (!correct) { debugReason = 'Short answer missing correct'; }
                    question.correct_choice = correct ? String(correct).trim() : '';
                } else if (!type) {
                    debugReason = 'Invalid type';
                }
            }
            if (debugReason) {
                errorRows.push({row: i+2, error: debugReason});
            } else if (question) {
                questions.push(question);
            }
        }

        if (errorRows.length > 0) {
            showToast(`❌ Found ${errorRows.length} validation error(s). Please fix them in your Excel file before uploading.`, 'error');

            const uploadBtn = document.getElementById('uploadBtn');
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Fix Errors First';
                uploadBtn.style.backgroundColor = '#ccc';
            }

            return;
        }
        if (questions.length === 0) {
            showToast('No valid questions found in the Excel file.', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'bulk_create_questions');
        formData.append('questions', JSON.stringify(questions));

        const uploadAuthorEl = document.getElementById('uploadAuthor');
        if (uploadAuthorEl && uploadAuthorEl.value) {
            formData.append('author_teacher_id', uploadAuthorEl.value);
        }
        
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result && result.success) {
            const importedCount = result.data?.imported_count ?? 0;
            
            showToast(`Successfully imported ${importedCount} questions`, 'success');
            clearFile();
            closeModal('uploadQuestionsModal');
            await refreshQuestionsList();
        } else {
            const errorMsg = result && result.message ? result.message : 'Import failed - unknown error';
            showValidationError([`Upload failed: ${errorMsg}`]);
            showToast(errorMsg, 'error');
        }
        
    } catch (error) {
        const errorMessage = error && error.message ? error.message : 'Unknown error while processing the Excel file';
        showValidationError([`Upload failed: ${errorMessage}`]);
        showToast(errorMessage, 'error');
        console.error(error);
    }
}

// ============ QUESTIONS LIST REFRESH ============
async function refreshQuestionsList() {
    if (window.useServerQuestionMarkup) {
        window.location.reload();
        return;
    }

    try {
        const response = await fetch('../api/questions_list_api.php');
        const data = await response.json();
        if (!data.questions) return;

        renderQuestionsList(data.questions, data.choices || {});
    } catch (e) {
        showToast('Failed to refresh questions list', 'error');
        console.error(e);
    }
}

async function refreshQuestionsListModal() {
    try {
        const response = await fetch('../api/questions_list_api.php');
        const data = await response.json();
        if (!data.questions) return;
        const container = document.getElementById('modalQuestionsContainer');
        if (!container) return;
        if (data.questions.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <h3>No questions yet</h3>
                    <p>Create your first question by clicking the button above!</p>
                </div>
            `;
            return;
        }
        let html = '';
        data.questions.forEach(q => {
            html += `
            <div class="question-card" data-id="${q.question_id}"
                 data-lesson="${q.lesson_id}"
                 data-type="${q.question_type}"
                 data-visibility="${q.visibility}">
                <div class="question-header">
                    <div class="question-meta">
                        <span class="badge badge-${q.question_type}">${capitalize(q.question_type.replace('_',' '))}</span>
                        <span class="badge badge-${q.visibility}">${capitalize(q.visibility)}</span>
                        <span class="lesson-tag"><i class="fas fa-book"></i> ${escapeHtml(q.lesson_title)}</span>
                        <span class="question-teacher">${q.first_name ? 'By: ' + escapeHtml(q.first_name + ' ' + (q.last_name || '')) : ''}</span>
                    </div>
                </div>
                <div class="question-text">${escapeHtml(q.question_text)}</div>
            </div>
            `;
        });
        container.innerHTML = html;
    } catch (e) {
        showToast('Failed to refresh questions list', 'error');
        console.error(e);
    }
}

const questionsListModal = document.getElementById('questionsListModal');
if (questionsListModal) {
    questionsListModal.addEventListener('show', refreshQuestionsListModal);
}

function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function (c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}

function parseCSV(text) {
    const rows = [];
    let cur = '';
    let row = [];
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (inQuotes) {
            if (ch === '"') {
                if (text[i+1] === '"') {
                    cur += '"';
                    i++; 
                } else {
                    inQuotes = false;
                }
            } else {
                cur += ch;
            }
        } else {
            if (ch === '"') {
                inQuotes = true;
            } else if (ch === ',') {
                row.push(cur);
                cur = '';
            } else if (ch === '\r') {
                continue;
            } else if (ch === '\n') {
                row.push(cur);
                rows.push(row);
                row = [];
                cur = '';
            } else {
                cur += ch;
            }
        }
    }
    if (cur !== '' || row.length > 0) {
        row.push(cur);
        rows.push(row);
    }
    return rows;
}

const styleId = 'upload-error-table-style';
if (!document.getElementById(styleId)) {
    const style = document.createElement('style');
    style.id = styleId;
    style.innerHTML = `
    .upload-error-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0;
        background: transparent;
        color: #b00020;
        font-size: 15px;
        box-shadow: none;
        border: none;
        border-radius: 0;
        overflow: hidden;
    }
    .upload-error-table th, .upload-error-table td {
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
        text-align: left;
    }
    .upload-error-table th {
        background: #ffeaea;
        font-weight: bold;
    }
    .upload-error-table tr:nth-child(even) {
        background: #fff2f2;
    }
    `;
    document.head.appendChild(style);
}