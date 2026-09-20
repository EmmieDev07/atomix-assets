// Students Management JavaScript
const STUDENTS_PER_PAGE = 10;
const STUDENT_UPLOAD_MAX_BYTES = 2 * 1024 * 1024; // 2MB
let studentsCurrentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize search and filter functionality
    const searchInput = document.getElementById('studentSearch');
    const classFilter = document.getElementById('classFilter');

    if (searchInput) {
        searchInput.addEventListener('input', filterStudents);
    }
    if (classFilter) {
        classFilter.addEventListener('change', filterStudents);
    }

    document.addEventListener('click', function(event) {
        const button = event.target.closest('.students-page-btn');
        if (!button) {
            return;
        }

        const pageValue = button.getAttribute('data-page');
        if (pageValue === 'prev') {
            studentsCurrentPage = Math.max(1, studentsCurrentPage - 1);
        } else if (pageValue === 'next') {
            studentsCurrentPage += 1;
        } else {
            studentsCurrentPage = parseInt(pageValue, 10) || 1;
        }

        filterStudents(false);
    });

    filterStudents(true);

    // Optional upload controls (present on admin students page)
    const fileUploadLink = document.querySelector('.file-upload-link');
    const studentExcelFile = document.getElementById('studentExcelFile');
    const studentFileUploadArea = document.getElementById('studentFileUploadArea');

    if (fileUploadLink && studentExcelFile) {
        fileUploadLink.onclick = function() {
            studentExcelFile.click();
        };
    }

    if (studentExcelFile) {
        studentExcelFile.onchange = function() {
            const file = this.files[0];
            if (file) {
                if (!validateStudentUploadFile(file)) {
                    this.value = '';
                    return;
                }
                updateStudentFileInfo(file);
            }
        };
    }

    if (studentFileUploadArea && studentExcelFile) {
        studentFileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#3b82f6';
            this.style.background = '#eff6ff';
        });

        studentFileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#cbd5e1';
            this.style.background = '#f8fafc';
        });

        studentFileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#cbd5e1';
            this.style.background = '#f8fafc';

            const files = e.dataTransfer.files;
            if (!files.length) return;
            const file = files[0];
            if (!validateStudentUploadFile(file)) {
                return;
            }

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            studentExcelFile.files = dataTransfer.files;
            updateStudentFileInfo(file);
        });
    }
});

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    // Reset forms when closing
    if (id === 'editStudentModal') {
        const editForm = document.getElementById('editStudentForm');
        if (editForm) editForm.reset();
    }
    if (id === 'addStudentModal') {
        const addForm = document.getElementById('addStudentForm');
        if (addForm) addForm.reset();
    }
}

function filterStudents(resetPage = true) {
    const searchInput = document.getElementById('studentSearch');
    const classFilterInput = document.getElementById('classFilter');
    const paginationContainer = document.getElementById('studentsPagination');
    const searchTerm = ((searchInput && searchInput.value) || '').toLowerCase().trim();
    const classFilter = ((classFilterInput && classFilterInput.value) || '').trim();
    const studentItems = Array.from(document.querySelectorAll('.student-item'));

    if (!studentItems.length) {
        if (paginationContainer) {
            paginationContainer.style.display = 'none';
            paginationContainer.innerHTML = '';
        }
        return;
    }

    if (resetPage) {
        studentsCurrentPage = 1;
    }

    const matchingItems = studentItems.filter(item => {
        const name = (item.dataset.name || '').toLowerCase();
        const username = (item.dataset.username || '').toLowerCase();
        const studentClass = (item.dataset.class || '').trim();

        // Check search term
        const matchesSearch = searchTerm === '' ||
            name.includes(searchTerm) ||
            username.includes(searchTerm);

        // Check class filter
        const matchesClass = classFilter === '' || studentClass === classFilter;

        return matchesSearch && matchesClass;
    });

    const totalMatches = matchingItems.length;
    const totalPages = Math.max(1, Math.ceil(totalMatches / STUDENTS_PER_PAGE));
    studentsCurrentPage = Math.min(Math.max(1, studentsCurrentPage), totalPages);
    const start = (studentsCurrentPage - 1) * STUDENTS_PER_PAGE;
    const end = start + STUDENTS_PER_PAGE;

    studentItems.forEach(item => {
        item.classList.add('hidden');
        item.style.display = 'none';
    });

    matchingItems.slice(start, end).forEach(item => {
        item.classList.remove('hidden');
        item.style.display = 'flex';
    });

    renderStudentsPagination(totalMatches, totalPages, studentsCurrentPage);

    // Update visible count
    updateVisibleCount(totalMatches, studentItems.length);
}

function renderStudentsPagination(totalMatches, totalPages, currentPage) {
    const container = document.getElementById('studentsPagination');
    if (!container) {
        return;
    }

    if (totalMatches <= STUDENTS_PER_PAGE) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    const startItem = ((currentPage - 1) * STUDENTS_PER_PAGE) + 1;
    const endItem = Math.min(currentPage * STUDENTS_PER_PAGE, totalMatches);
    const pageButtons = [];
    const windowStart = Math.max(1, currentPage - 2);
    const windowEnd = Math.min(totalPages, windowStart + 4);

    pageButtons.push(`<button type="button" class="students-page-btn" data-page="prev" ${currentPage === 1 ? 'disabled' : ''}>Prev</button>`);

    for (let i = windowStart; i <= windowEnd; i++) {
        pageButtons.push(`<button type="button" class="students-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`);
    }

    pageButtons.push(`<button type="button" class="students-page-btn" data-page="next" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>`);

    container.style.display = 'flex';
    container.innerHTML =
        `<div class="students-pagination-info">Showing ${startItem}-${endItem} of ${totalMatches} students</div>` +
        `<div class="students-pagination-controls">${pageButtons.join('')}</div>`;
}

function updateVisibleCount(filteredCount, totalCount) {
    const header = document.querySelector('.students-card h2');

    if (header) {
        const baseText = header.textContent.split('(')[0].trim();
        header.innerHTML = `${baseText} (${filteredCount}/${totalCount})`;
    }
}

function editStudent(studentId) {
    // Fetch student data
    fetch(`../api/admin_student_api.php?action=get_student&student_id=${studentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const student = data.data;
                document.getElementById('editStudentId').value = student.student_id;
                document.getElementById('editFirstName').value = student.first_name;
                document.getElementById('editLastName').value = student.last_name;
                document.getElementById('editUsername').value = student.username;
                document.getElementById('editClass').value = student.class_id || '';
                document.getElementById('editStatus').value = student.status;
                openModal('editStudentModal');
            } else {
                showToast('Failed to load student data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while loading student data', 'error');
        });
}

function updateStudent(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Client-side validation
    const firstName = formData.get('first_name').trim();
    const lastName = formData.get('last_name').trim();
    const username = formData.get('username').trim();

    if (!firstName || !lastName || !username) {
        showToast('All required fields must be filled', 'error');
        return;
    }

    if (firstName.length > 50 || lastName.length > 50) {
        showToast('Names must be 50 characters or less', 'error');
        return;
    }

    if (username.length > 50) {
        showToast('Username must be 50 characters or less', 'error');
        return;
    }

    formData.append('action', 'update_student');

    fetch('../api/admin_student_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            closeModal('editStudentModal');
            form.reset();
            // Reload the page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating the student', 'error');
    });
}

async function submitAddStudent(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    const firstName = (formData.get('first_name') || '').trim();
    const lastName = (formData.get('last_name') || '').trim();
    const classId = (formData.get('class_id') || '').trim();
    const gender = (formData.get('gender') || '').trim();
    let password = (formData.get('password') || '').trim();

    if (!password) password = 'changeme';

    if (!firstName || !lastName || !classId || !gender) {
        showToast('Please fill all required fields', 'error');
        return;
    }

    try {
        const payload = { class_id: classId, first_name: firstName, last_name: lastName, gender, password };

        const res = await fetch('../api/student_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            // Show created credentials to admin with option to copy
            const username = data.username || '';
            const passwordResp = data.password || '';
            const pwResult = await Swal.fire({
                title: 'Student created',
                html: `
                    <p><strong>Username:</strong> ${username}</p>
                    <p><strong>Password:</strong> <span style="font-weight:600;" id="newPassword">${passwordResp}</span></p>
                `,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Copy Password',
                cancelButtonText: 'Close'
            });

            if (pwResult.isConfirmed) {
                try {
                    await navigator.clipboard.writeText(passwordResp);
                    showToast('Password copied to clipboard', 'success');
                } catch (err) {
                    const input = document.createElement('input');
                    input.value = passwordResp;
                    document.body.appendChild(input);
                    input.select();
                    try { document.execCommand('copy'); showToast('Password copied to clipboard', 'success'); }
                    catch (err2) { showToast('Unable to copy. Please copy manually.', 'error'); }
                    document.body.removeChild(input);
                }
            }

            closeModal('addStudentModal');
            form.reset();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showToast(data.error || 'Failed to create student', 'error');
        }
    } catch (err) {
        console.error('Error:', err);
        showToast('An error occurred while creating the student', 'error');
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

async function archiveStudent(userId) {
    const confirmed = await confirmCriticalAction(
        'Mark Student Inactive?',
        'The student will be marked inactive and won\'t be able to log in until set active again.',
        'Yes, set inactive'
    );
    if (!confirmed) {
        return;
    }

    fetch('../api/admin_student_api.php?action=toggle_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, action: 'deactivate' })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating the student', 'error');
    });
}

async function unarchiveStudent(userId) {
    const confirmed = await confirmCriticalAction(
        'Mark Student Active?',
        'The student will be marked active and will be able to log in again.',
        'Yes, set active'
    );
    if (!confirmed) {
        return;
    }

    fetch('../api/admin_student_api.php?action=toggle_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, action: 'activate' })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating the student', 'error');
    });
}

function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = toast.querySelector('.toast-message');
    toastMessage.textContent = msg;
    toast.className = `toast ${type} show`;
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.classList.contains('show')) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    }
});
    
// Admin reset student password
async function resetStudentPassword(userId) {
    try {
        const confirmResult = await Swal.fire({
            title: 'Reset password?',
            text: 'This student\'s password will be reset to the default password.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, reset it',
            cancelButtonText: 'Cancel'
        });
        if (!confirmResult.isConfirmed) return;

        Swal.fire({
            title: 'Resetting Password...',
            text: 'Please wait.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const response = await fetch('../api/student_api.php?action=reset_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const result = await response.json();
        Swal.close();
        if (result.success) {
            Swal.fire({
                title: 'Password Reset',
                html: `<p>${result.message || 'Password reset successfully.'}</p><p><strong>Default password:</strong> ${result.temp_password || 'changeme'}</p>`,
                icon: 'success',
                confirmButtonText: 'OK'
            });
        } else {
            Swal.fire('Error', result.error || 'Failed to reset password', 'error');
        }
    } catch (error) {
        Swal.close();
        Swal.fire('Error', 'An error occurred while resetting the password', 'error');
    }
}

function clearStudentFile() {
    const fileEl = document.getElementById('studentExcelFile');
    const infoEl = document.getElementById('studentFileInfo');
    const contentEl = document.querySelector('.file-upload-content');
    const nameEl = document.getElementById('studentFileName');

    if (fileEl) fileEl.value = '';
    if (nameEl) nameEl.textContent = '';
    if (infoEl) infoEl.style.display = 'none';
    if (contentEl) contentEl.style.display = 'block';
}

function validateStudentUploadFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'xls', 'csv'].includes(ext)) {
        showToast('Invalid file type. Only Excel (.xlsx/.xls) or CSV allowed.', 'error');
        return false;
    }
    if (file.size > STUDENT_UPLOAD_MAX_BYTES) {
        showToast('File too large. Maximum upload size is 2MB.', 'error');
        return false;
    }
    return true;
}

function updateStudentFileInfo(file) {
    const nameEl = document.getElementById('studentFileName');
    const infoEl = document.getElementById('studentFileInfo');
    const contentEl = document.querySelector('.file-upload-content');
    if (nameEl) nameEl.textContent = file.name;
    if (infoEl) infoEl.style.display = 'flex';
    if (contentEl) contentEl.style.display = 'none';
}

function renderPreviewTable(preview) {
    const container = document.getElementById('previewTableContainerModal');
    if (!container) return;

    if (!preview.length) {
        container.innerHTML = '<div>No rows found in file.</div>';
        openModal('previewStudentsModal');
        return;
    }

    let html = '<table class="data-table"><thead><tr>';
    for (const key in preview[0].row) html += `<th>${key}</th>`;
    html += '<th>Status</th><th>Errors</th></tr></thead><tbody>';

    preview.forEach(p => {
        html += '<tr>';
        for (const key in p.row) html += `<td>${p.row[key] ?? ''}</td>`;
        html += `<td>${p.valid ? 'OK' : 'Invalid'}</td>`;
        html += `<td>${(p.errors || []).join('<br>')}</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    html += '<button class="btn btn-success" id="importStudentsBtn" style="margin-top:16px;">Import Students</button>';
    container.innerHTML = html;

    openModal('previewStudentsModal');
    const btn = document.getElementById('importStudentsBtn');
    if (btn) {
        btn.onclick = function() {
            importStudents(preview);
        };
    }
}

async function importStudents(preview) {
    const validRows = preview.filter(p => p.valid).map(p => p.row);
    const classSelect = document.getElementById('uploadClassId');
    const classId = classSelect ? classSelect.value : '';

    if (!validRows.length) {
        showToast('No valid rows to import.', 'error');
        return;
    }
    if (!classId) {
        showToast('Please select a class before importing.', 'error');
        return;
    }

    const confirmed = await confirmCriticalAction(
        'Import Students?',
        `This will import ${validRows.length} valid student record(s) into the selected class.`,
        'Yes, import now'
    );
    if (!confirmed) {
        return;
    }

    fetch('../api/import_students.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ students: validRows, class_id: classId })
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            showToast('Import failed: invalid server response', 'error');
            return;
        }

        if (data.result) {
            const result = data.result;
            const summary = `Imported: ${result.imported}\nSkipped: ${result.skipped}\nFailed: ${result.failed}`;
            Swal.fire({
                title: 'Import Results',
                text: summary,
                icon: result.failed === 0 ? 'success' : 'warning'
            });
            closeModal('previewStudentsModal');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.error || 'Import failed', 'error');
        }
    })
    .catch(err => showToast('Error importing students: ' + err, 'error'));
}

function uploadStudents() {
    const fileInput = document.getElementById('studentExcelFile');
    const classSelect = document.getElementById('uploadClassId');

    if (!fileInput || !fileInput.files.length) {
        showToast('Please select an Excel or CSV file.', 'error');
        return;
    }
    if (!classSelect || !classSelect.value) {
        showToast('Please select a class for uploaded students.', 'error');
        return;
    }

    const file = fileInput.files[0];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'xls', 'csv'].includes(ext)) {
        showToast('Invalid file type. Only Excel (.xlsx/.xls) or CSV allowed.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('student_file', file);
    formData.append('class_id', classSelect.value);

    fetch('../api/upload_students.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        let res;
        try {
            res = await response.json();
        } catch (e) {
            showToast('Upload failed: Invalid JSON response', 'error');
            return;
        }

        closeModal('uploadStudentsModal');
        if (res.preview) {
            renderPreviewTable(res.preview);
        } else {
            showToast(res.error ? 'Upload failed: ' + res.error : 'Upload failed.', 'error');
        }
    })
    .catch(err => showToast('Error uploading file: ' + err, 'error'));
}