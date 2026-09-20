// JS for School Year Management (Admin)

document.addEventListener('DOMContentLoaded', function() {
    loadSchoolYears();
});

function openModal(id) {
    document.getElementById(id).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = 'auto';
    // Reset forms when closing
    if (id === 'addSYModal') {
        document.getElementById('addSYForm').reset();
    } else if (id === 'editSYModal') {
        document.getElementById('editSYForm').reset();
    }
}

function loadSchoolYears() {
    fetch('../api/school_year_api.php?action=get_school_years')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const activeGrid = document.getElementById('grid-active');
            const archivedGrid = document.getElementById('grid-archived');
            if (!activeGrid || !archivedGrid) return;

            const items = data.data || [];
            const active = items.filter(sy => Number(sy.is_active) === 1 || sy.is_active === true);
            const inactive = items.filter(sy => Number(sy.is_active) === 0 || sy.is_active === false);

            // Update stats if present
            const statTotal = document.getElementById('stat-total');
            const statActive = document.getElementById('stat-active');
            const statInactive = document.getElementById('stat-inactive');
            if (statTotal) statTotal.textContent = items.length;
            if (statActive) statActive.textContent = active.length;
            if (statInactive) statInactive.textContent = inactive.length;

            // Render active
            if (active.length === 0) {
                activeGrid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Active School Years</h3>
                        <p>Create or activate a school year to make it available.</p>
                    </div>`;
            } else {
                activeGrid.innerHTML = '';
                active.forEach(sy => {
                    activeGrid.innerHTML += `
                        <div class="school-year-item active" data-id="${sy.sy_id}">
                            <div class="school-year-info">
                                <h4>${sy.label}</h4>
                                <div class="school-year-meta">
                                    <span><i class="fas fa-info-circle"></i> ID: ${sy.sy_id}</span>
                                    <span class="school-year-status active">
                                        <i class="fas fa-check-circle"></i>
                                        Active
                                    </span>
                                </div>
                            </div>
                            <div class="school-year-actions">
                                <button class="btn btn-sm btn-info" onclick="editSY(${sy.sy_id})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </div>`;
                });
            }

            // Render archived
            if (inactive.length === 0) {
                archivedGrid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-archive"></i>
                        <h3>No Archived School Years</h3>
                        <p>Inactive school years will appear here.</p>
                    </div>`;
            } else {
                archivedGrid.innerHTML = '';
                inactive.forEach(sy => {
                    archivedGrid.innerHTML += `
                        <div class="school-year-item" data-id="${sy.sy_id}">
                            <div class="school-year-info">
                                <h4>${sy.label}</h4>
                                <div class="school-year-meta">
                                    <span><i class="fas fa-info-circle"></i> ID: ${sy.sy_id}</span>
                                    <span class="school-year-status inactive">
                                        <i class="fas fa-times-circle"></i>
                                        Inactive
                                    </span>
                                </div>
                            </div>
                            <div class="school-year-actions">
                                <button class="btn btn-sm btn-info" onclick="editSY(${sy.sy_id})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </div>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading school years:', error);
        });
}

function submitSY(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Client-side validation
    const label = formData.get('label').trim();
    if (!label) {
        showToast('School year label is required', 'error');
        return;
    }

    if (label.length > 20) {
        showToast('School year label must be 20 characters or less', 'error');
        return;
    }

    // Check for valid format (e.g., 2025-2026)
    const labelPattern = /^\d{4}-\d{4}$/;
    if (!labelPattern.test(label)) {
        showToast('School year label should be in format YYYY-YYYY (e.g., 2025-2026)', 'error');
        return;
    }

    formData.append('action', 'create_sy');

    fetch('../api/school_year_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            closeModal('addSYModal');
            form.reset();
            // Refresh lists without full reload
            setTimeout(() => loadSchoolYears(), 500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while saving the school year', 'error');
    });
}

function editSY(syId) {
    // Fetch school year data
    fetch(`../api/school_year_api.php?action=get_sy&sy_id=${syId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sy = data.data;
                document.getElementById('editSyId').value = sy.sy_id;
                document.getElementById('editSyLabel').value = sy.label;
                document.getElementById('editSyActive').value = sy.is_active;
                openModal('editSYModal');
            } else {
                showToast('Failed to load school year data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while loading school year data', 'error');
        });
}

function updateSY(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Client-side validation
    const label = formData.get('label').trim();
    if (!label) {
        showToast('School year label is required', 'error');
        return;
    }

    if (label.length > 20) {
        showToast('School year label must be 20 characters or less', 'error');
        return;
    }

    // Check for valid format (e.g., 2025-2026)
    const labelPattern = /^\d{4}-\d{4}$/;
    if (!labelPattern.test(label)) {
        showToast('School year label should be in format YYYY-YYYY (e.g., 2025-2026)', 'error');
        return;
    }

    formData.append('action', 'update_sy');

    fetch('../api/school_year_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            closeModal('editSYModal');
            form.reset();
            setTimeout(() => loadSchoolYears(), 500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating the school year', 'error');
    });
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

async function deleteSY(syId) {
    const confirmed = await confirmCriticalAction(
        'Delete School Year?',
        'Are you sure you want to delete this school year? This action cannot be undone and may affect related data.',
        'Yes, delete school year'
    );
    if (!confirmed) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_sy');
    formData.append('sy_id', syId);

    fetch('../api/school_year_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => loadSchoolYears(), 500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while deleting the school year', 'error');
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
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Expose functions to global scope for inline handlers
try {
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.editSY = editSY;
    window.deleteSY = deleteSY;
    window.submitSY = submitSY;
    window.updateSY = updateSY;
} catch (e) {
    // ignore in non-browser environments
}
