document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('classForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', submitClassForm);

    const classNameInput = document.getElementById('className');
    if (classNameInput) {
        classNameInput.addEventListener('input', function () {
            classNameInput.setCustomValidity('');
        });
    }

    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            switchTab(button.dataset.tab || 'active-classes');
        });
    });
});

function openClassModal() {
    resetClassForm();
    document.getElementById('classModal').style.display = 'block';
}

function closeClassModal() {
    document.getElementById('classModal').style.display = 'none';
    resetClassForm();
}

function resetClassForm() {
    const form = document.getElementById('classForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('classId').value = '';
    const activeSchoolYearId = document.getElementById('syId').defaultValue || '';
    document.getElementById('syId').value = activeSchoolYearId;
    const schoolYearLabel = document.getElementById('syLabelDisplay');
    if (schoolYearLabel) {
        schoolYearLabel.value = schoolYearLabel.defaultValue || 'No active school year available';
    }
    document.getElementById('classModalTitle').innerHTML = '<i class="fas fa-chalkboard"></i> Add Class';
    document.getElementById('classSubmitButton').textContent = 'Save Class';
}

function editClass(button) {
    const row = button.closest('tr');
    if (!row) {
        return;
    }

    document.getElementById('classId').value = row.dataset.id || '';
    document.getElementById('className').value = row.dataset.className || '';
    document.getElementById('syId').value = row.dataset.syId || '';
    const schoolYearLabel = document.getElementById('syLabelDisplay');
    if (schoolYearLabel) {
        schoolYearLabel.value = row.dataset.syLabel || schoolYearLabel.defaultValue || '';
    }
    document.getElementById('teacherId').value = row.dataset.teacherId || '';
    document.getElementById('classModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Class';
    document.getElementById('classSubmitButton').textContent = 'Update Class';
    document.getElementById('classModal').style.display = 'block';
}

function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(function (content) {
        content.classList.remove('active');
    });

    document.querySelectorAll('.tab-btn').forEach(function (button) {
        button.classList.remove('active');
    });

    const tabContent = document.getElementById(tabName + '-tab');
    if (tabContent) {
        tabContent.classList.add('active');
    }

    const activeButton = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
    if (activeButton) {
        activeButton.classList.add('active');
    }
}

function normalizeClassName(name) {
    return (name || '').trim().replace(/\s+/g, ' ').toLowerCase();
}

function hasDuplicateClassName(className, schoolYearId, currentClassId) {
    const normalizedName = normalizeClassName(className);
    if (!normalizedName || !schoolYearId) {
        return false;
    }

    return Array.from(document.querySelectorAll('tr[data-id]')).some(function (row) {
        const rowClassId = row.dataset.id || '';
        const rowSchoolYearId = row.dataset.syId || '';
        const rowClassName = normalizeClassName(row.dataset.className || '');

        return rowClassId !== String(currentClassId || '') &&
            rowSchoolYearId === String(schoolYearId) &&
            rowClassName === normalizedName;
    });
}

function submitClassForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const classId = formData.get('class_id');
    const isEdit = Boolean(formData.get('class_id'));
    const classNameInput = document.getElementById('className');
    const className = formData.get('class_name');
    const schoolYearId = formData.get('sy_id');

    if (!formData.get('sy_id')) {
        showToast('No active school year is available.');
        return;
    }

    if (!formData.get('teacher_id')) {
        showToast('Assigned teacher is required.');
        return;
    }

    if (hasDuplicateClassName(className, schoolYearId, classId)) {
        if (classNameInput) {
            classNameInput.setCustomValidity('Class name already exists in this school year.');
            classNameInput.reportValidity();
            classNameInput.focus();
        }
        showToast('Class name already exists in this school year.');
        return;
    }

    formData.append('action', isEdit ? 'edit_class' : 'create_class');

    fetch('../api/class_api.php', {
        method: 'POST',
        body: formData
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            showToast(data.message || 'Request finished.');
            if (data.success) {
                closeClassModal();
                window.location.reload();
            }
        })
        .catch(function () {
            showToast('Failed to save class.');
        });
}

async function confirmCriticalAction(title, text, confirmText) {
    if (typeof Swal !== 'undefined' && Swal.fire) {
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText || 'Yes, proceed',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33'
        });
        return result.isConfirmed;
    }

    return confirm('Are you sure?\n\n' + text);
}

async function deleteClass(classId) {
    const confirmed = await confirmCriticalAction(
        'Delete Class?',
        'Are you sure you want to delete this class? This action cannot be undone.',
        'Yes, delete class'
    );
    if (!confirmed) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_class');
    formData.append('class_id', classId);

    fetch('../api/class_api.php', {
        method: 'POST',
        body: formData
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            showToast(data.message || 'Request finished.');
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(function () {
            showToast('Failed to delete class.');
        });
}

function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) {
        return;
    }

    toast.querySelector('.toast-message').textContent = message;
    toast.style.display = 'block';
    setTimeout(function () {
        toast.style.display = 'none';
    }, 2500);
}

window.openClassModal = openClassModal;
window.closeClassModal = closeClassModal;
window.editClass = editClass;
window.deleteClass = deleteClass;