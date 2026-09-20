/**
 * Chapter Management JavaScript
 * Handles CRUD operations for chapters, lessons, and topics
 */

// API Base URL
const API_URL = '../api/chapter_api.php';

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    // loadTopics(); // Commented out - topics tab removed
    initLessonFilter();
    initSectionSelector();
});

// ============ TAB NAVIGATION ============

function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            // Remove active class from all
            tabBtns.forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked
            this.classList.add('active');
            const targetEl = document.getElementById(tabId + '-tab');
            if (targetEl) targetEl.classList.add('active');
        });
    });
}

// ============ MODAL FUNCTIONS ============

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = 'auto';
    
    // Reset forms
    const form = document.getElementById(modalId)?.querySelector('form');
    if (form) form.reset();
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
    if (!toast) return;
    const toastMessage = toast.querySelector('.toast-message');
    
    toast.className = 'toast show ' + type;
    if (toastMessage) toastMessage.textContent = message;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ============ LESSON FILTERING ============

function initLessonFilter() {
    const filterSelect = document.getElementById('chapterFilter');
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            const selectedChapterId = this.value;
            const chapterSections = document.querySelectorAll('.chapter-section');

            chapterSections.forEach(section => {
                if (selectedChapterId === '' || section.dataset.chapterId === selectedChapterId) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        });
    }
    
    // Initialize lesson order auto-calculation
    initLessonOrderAutoCalc();
}

function initLessonOrderAutoCalc() {
    const chapterSelect = document.getElementById('lessonChapter');
    const orderInput = document.getElementById('lessonOrder');
    
    if (chapterSelect && orderInput) {
        // Make order input read-only
        orderInput.readOnly = true;
        orderInput.style.backgroundColor = '#f8f9fa';
        orderInput.style.cursor = 'not-allowed';
        
        chapterSelect.addEventListener('change', function() {
            const selectedChapterId = this.value;
            if (selectedChapterId && typeof nextOrderByChapter !== 'undefined' && nextOrderByChapter[selectedChapterId] !== undefined) {
                orderInput.value = nextOrderByChapter[selectedChapterId];
            } else {
                orderInput.value = '1';
            }
        });
        
        // Set initial value if a chapter is already selected
        if (chapterSelect.value) {
            chapterSelect.dispatchEvent(new Event('change'));
        }
    }
}

function initSectionSelector() {
    const sectionSelect = document.getElementById('classSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.addEventListener('change', function() {
        const classId = this.value || '0';
        const url = new URL(window.location.href);
        url.searchParams.set('class_id', classId);
        window.location.href = url.toString();
    });
}

// ============ CHAPTER FUNCTIONS ============

async function submitChapter(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create_chapter');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('addChapterModal');
            location.reload();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

async function editChapter(id) {
    try {
        const response = await fetch(`${API_URL}?action=get_chapter&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const chapter = result.data;
            document.getElementById('editChapterId').value = chapter.chapter_id;
            document.getElementById('editChapterTitle').value = chapter.chapter_title;
            document.getElementById('editChapterOrder').value = chapter.chapter_order;
            openModal('editChapterModal');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Failed to load chapter data.', 'error');
        console.error(error);
    }
}

async function updateChapter(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'update_chapter');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('editChapterModal');
            location.reload();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

// REAL-TIME TOGGLE CHAPTER LOCK WITH SWEETALERT
async function toggleChapterLock(chapterId, isLocked, classId) {
    if (!classId || Number(classId) <= 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Section Required',
                text: 'Select a section first.',
                confirmButtonColor: '#f59e0b'
            });
        } else {
            showToast('Select a section first.', 'error');
        }
        return;
    }

    const formData = new FormData();
    formData.append('action', 'set_chapter_lock');
    formData.append('chapter_id', chapterId);
    formData.append('is_locked', isLocked);
    formData.append('class_id', classId);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Real-time DOM element updates
            const row = document.querySelector(`tr[data-id="${chapterId}"]`);
            if (row) {
                const badge = row.querySelector('.chapter-lock-badge');
                const button = row.querySelector('.actions button');

                if (Number(isLocked) === 1) {
                    if (badge) {
                        badge.className = 'chapter-lock-badge locked';
                        badge.innerHTML = '<i class="fas fa-lock"></i> Locked';
                    }
                    if (button) {
                        button.className = 'btn btn-sm btn-primary';
                        button.innerHTML = '<i class="fas fa-lock-open"></i> Unlock';
                        button.setAttribute('onclick', `toggleChapterLock(${chapterId}, 0, ${classId})`);
                    }
                } else {
                    if (badge) {
                        badge.className = 'chapter-lock-badge unlocked';
                        badge.innerHTML = '<i class="fas fa-lock-open"></i> Unlocked';
                    }
                    if (button) {
                        button.className = 'btn btn-sm btn-secondary';
                        button.innerHTML = '<i class="fas fa-lock"></i> Lock';
                        button.setAttribute('onclick', `toggleChapterLock(${chapterId}, 1, ${classId})`);
                    }
                }
            }

            // SweetAlert Feedback
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: result.message || 'Chapter access updated successfully.',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                showToast(result.message, 'success');
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: result.message || 'Could not update chapter status.',
                    confirmButtonColor: '#ef4444'
                });
            } else {
                showToast(result.message, 'error');
            }
        }
    } catch (error) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Failed to communicate with the server.',
                confirmButtonColor: '#ef4444'
            });
        } else {
            showToast('Failed to update chapter access.', 'error');
        }
        console.error(error);
    }
}

// REAL-TIME TOGGLE EXAM LOCK WITH SWEETALERT
async function toggleExamLock(examKey, isLocked, classId) {
    if (!classId || Number(classId) <= 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Section Required',
                text: 'Select a section first.',
                confirmButtonColor: '#f59e0b'
            });
        } else {
            showToast('Select a section first.', 'error');
        }
        return;
    }

    const formData = new FormData();
    formData.append('action', 'set_exam_lock');
    formData.append('exam_key', examKey);
    formData.append('is_locked', isLocked);
    formData.append('class_id', classId);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Real-time DOM element updates
            const card = document.querySelector('.exam-schedule-card');
            const badge = card ? card.querySelector('.exam-lock-badge') : null;
            const button = card ? card.querySelector('button') : null;

            if (badge && button) {
                if (Number(isLocked) === 1) {
                    badge.className = 'exam-lock-badge locked';
                    badge.innerHTML = '<i class="fas fa-lock"></i> Locked';

                    button.className = 'btn btn-sm btn-primary';
                    button.innerHTML = '<i class="fas fa-lock-open"></i> Unlock Exam';
                    button.setAttribute('onclick', `toggleExamLock('${examKey}', 0, ${classId})`);
                } else {
                    badge.className = 'exam-lock-badge unlocked';
                    badge.innerHTML = '<i class="fas fa-lock-open"></i> Unlocked';

                    button.className = 'btn btn-sm btn-secondary';
                    button.innerHTML = '<i class="fas fa-lock"></i> Lock Exam';
                    button.setAttribute('onclick', `toggleExamLock('${examKey}', 1, ${classId})`);
                }
            }

            // SweetAlert Feedback
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: result.message || 'Exam schedule updated successfully.',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                showToast(result.message, 'success');
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: result.message || 'Could not update exam schedule.',
                    confirmButtonColor: '#ef4444'
                });
            } else {
                showToast(result.message, 'error');
            }
        }
    } catch (error) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Failed to communicate with the server.',
                confirmButtonColor: '#ef4444'
            });
        } else {
            showToast('Failed to update exam schedule.', 'error');
        }
        console.error(error);
    }
}

// ============ LESSON FUNCTIONS ============

async function submitLesson(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create_lesson');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('addLessonModal');
            location.reload();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

async function editLesson(id) {
    try {
        const response = await fetch(`${API_URL}?action=get_lesson&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const lesson = result.data;
            
            // Create edit lesson modal if not exists
            let modal = document.getElementById('editLessonModal');
            if (!modal) {
                createEditLessonModal();
                modal = document.getElementById('editLessonModal');
            }
            
            document.getElementById('editLessonId').value = lesson.lesson_id;
            document.getElementById('editLessonChapter').value = lesson.chapter_id;
            document.getElementById('editLessonTitle').value = lesson.lesson_title;
            document.getElementById('editLessonOrder').value = lesson.lesson_order;
            
            openModal('editLessonModal');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Failed to load lesson data.', 'error');
        console.error(error);
    }
}

function createEditLessonModal() {
    const chaptersOptions = document.getElementById('lessonChapter')?.innerHTML || '';
    
    const modalHTML = `
        <div class="modal" id="editLessonModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Edit Lesson</h3>
                    <button class="close-btn" onclick="closeModal('editLessonModal')">&times;</button>
                </div>
                <form id="editLessonForm" onsubmit="updateLesson(event)">
                    <input type="hidden" id="editLessonId" name="lesson_id">
                    <div class="form-group">
                        <label for="editLessonChapter">Chapter *</label>
                        <select id="editLessonChapter" name="chapter_id" required>
                            ${chaptersOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editLessonTitle">Lesson Title *</label>
                        <input type="text" id="editLessonTitle" name="lesson_title" required>
                    </div>
                    <div class="form-group">
                        <label for="editLessonOrder">Lesson Order</label>
                        <input type="number" id="editLessonOrder" name="lesson_order" value="1" min="1">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editLessonModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Lesson</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

async function updateLesson(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'update_lesson');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('editLessonModal');
            location.reload();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

// ============ TOPIC FUNCTIONS ============

async function loadTopics() {
    try {
        const response = await fetch(`${API_URL}?action=get_topics`);
        const result = await response.json();
        
        const tbody = document.getElementById('topicsTableBody');
        if (!tbody) return;
        
        if (result.success && result.data.length > 0) {
            tbody.innerHTML = result.data.map(topic => `
                <tr data-id="${topic.topic_id}">
                    <td>${escapeHtml(topic.topic_title)}</td>
                    <td>${escapeHtml(topic.lesson_title)}</td>
                    <td>${escapeHtml((topic.content || '').substring(0, 100))}${(topic.content || '').length > 100 ? '...' : ''}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-info" onclick="editTopic(${topic.topic_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteTopic(${topic.topic_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        console.error('Failed to load topics:', error);
    }
}

async function submitTopic(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create_topic');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('addTopicModal');
            loadTopics();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

async function editTopic(id) {
    try {
        const response = await fetch(`${API_URL}?action=get_topic&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const topic = result.data;
            
            // Create edit topic modal if not exists
            let modal = document.getElementById('editTopicModal');
            if (!modal) {
                createEditTopicModal();
                modal = document.getElementById('editTopicModal');
            }
            
            document.getElementById('editTopicId').value = topic.topic_id;
            document.getElementById('editTopicLesson').value = topic.lesson_id;
            document.getElementById('editTopicTitle').value = topic.topic_title;
            document.getElementById('editTopicContent').value = topic.content || '';
            
            openModal('editTopicModal');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Failed to load topic data.', 'error');
        console.error(error);
    }
}

function createEditTopicModal() {
    const lessonsOptions = document.getElementById('topicLesson')?.innerHTML || '';
    
    const modalHTML = `
        <div class="modal" id="editTopicModal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Edit Topic</h3>
                    <button class="close-btn" onclick="closeModal('editTopicModal')">&times;</button>
                </div>
                <form id="editTopicForm" onsubmit="updateTopic(event)">
                    <input type="hidden" id="editTopicId" name="topic_id">
                    <div class="form-group">
                        <label for="editTopicLesson">Lesson *</label>
                        <select id="editTopicLesson" name="lesson_id" required>
                            ${lessonsOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editTopicTitle">Topic Title *</label>
                        <input type="text" id="editTopicTitle" name="topic_title" required>
                    </div>
                    <div class="form-group">
                        <label for="editTopicContent">Content</label>
                        <textarea id="editTopicContent" name="content" rows="6"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editTopicModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Topic</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

async function updateTopic(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'update_topic');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('editTopicModal');
            loadTopics();
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

async function deleteTopic(id) {
    const confirmed = await confirmCriticalAction(
        'Delete Topic?',
        'Are you sure you want to delete this topic? This action cannot be undone.',
        'Yes, delete topic'
    );
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_topic');
    formData.append('topic_id', id);
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            document.querySelector(`#topicsTableBody tr[data-id="${id}"]`)?.remove();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('An error occurred. Please try again.', 'error');
        console.error(error);
    }
}

// ============ UTILITY FUNCTIONS ============

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}