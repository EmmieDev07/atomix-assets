async function resetTeacherPassword(userId) {
    try {
        const confirmResult = await Swal.fire({
            title: 'Reset password? ',
            text: 'A new password will be generated for this teacher.',
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

        const response = await fetch('../api/teacher_api.php?action=reset_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const result = await response.json();
        Swal.close();
        if (result.success) {
            if (result.email_sent) {
                showToast('Password reset successful. Reset details sent via email.', 'success');
                const sentTo = result.email_to ? ('\n\nSent to: ' + result.email_to) : '';
                Swal.fire({
                    title: 'Password Reset',
                    text: 'The teacher\'s reset password details were sent to their email.' + sentTo,
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            } else {
                const detail = result.email_reason ? ('\n\nReason: ' + result.email_reason) : '';
                showToast(result.message || 'Password reset successful, but email was not sent.', 'error');
                Swal.fire('Warning', (result.message || 'Password reset successful, but email was not sent.') + detail, 'warning');
            }
        } else {
            showToast(result.message || 'Failed to reset password', 'error');
            Swal.fire('Error', result.message || 'Failed to reset password', 'error');
        }
    } catch (error) {
        Swal.close();
        console.error('Error:', error);
        showToast('An error occurred while resetting the password', 'error');
        Swal.fire('Error', 'An error occurred while resetting the password', 'error');
    }
}

async function resendTeacherVerification(userId) {
    try {
        const response = await fetch('../api/teacher_api.php?action=resend_verification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Verification email resent successfully.', 'success');
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire('Verification Sent', result.message || 'Verification email resent successfully.', 'success');
            }
        } else {
            showToast(result.message || 'Failed to resend verification email.', 'error');
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire('Error', result.message || 'Failed to resend verification email.', 'error');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while resending verification email', 'error');
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire('Error', 'An error occurred while resending verification email', 'error');
        }
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
// Teachers Management JavaScript
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    // Reset form
    const form = document.getElementById('addTeacherForm');
    if (form) form.reset();
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.querySelector('.toast-message');

    toastMessage.textContent = message;
    toast.className = `toast ${type} show`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function isValidEmailFormat(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

async function submitTeacher(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    let password = formData.get('password');
    if (!password || password.trim() === '') {
        formData.set('password', 'changeme');
    }

    Swal.fire({
        title: 'Saving Teacher...',
        text: 'Please wait while the teacher account is created.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('create_teacher.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();
        Swal.close();
        if (result.success) {
            if (result.email_sent) {
                showToast(result.message || 'Teacher added successfully and email sent.', 'success');
            } else {
                showToast(result.message || 'Teacher added successfully, but email was not sent.', 'error');
                if (typeof Swal !== 'undefined' && Swal.fire) {
                    Swal.fire('Warning', result.message || 'Teacher added successfully, but email was not sent.', 'warning');
                }
            }
            closeModal('addTeacherModal');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to add teacher', 'error');
        }
    } catch (error) {
        Swal.close();
        console.error('Error:', error);
        showToast('An error occurred while adding the teacher', 'error');
    }
}

async function editTeacher(teacherId) {
    // Fetch teacher data
    try {
        const response = await fetch(`../api/teacher_api.php?action=get&id=${teacherId}`);
        const result = await response.json();
        if (result.success && result.teacher) {
            // Populate form fields
            document.getElementById('edit_teacher_id').value = result.teacher.teacher_id;
            document.getElementById('edit_email').value = result.teacher.email;
            document.getElementById('edit_username').value = result.teacher.username;
            document.getElementById('edit_first_name').value = result.teacher.first_name;
            document.getElementById('edit_last_name').value = result.teacher.last_name;
            openModal('editTeacherModal');
        } else {
            showToast(result.message || 'Failed to load teacher data', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while loading teacher data', 'error');
    }
}

async function submitEditTeacher(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const email = (formData.get('email') || '').toString().trim();

    if (!isValidEmailFormat(email)) {
        showToast('Please enter a valid email address.', 'error');
        const emailInput = document.getElementById('edit_email');
        if (emailInput) {
            emailInput.focus();
        }
        return;
    }

    formData.set('email', email);

    Swal.fire({
        title: 'Updating Teacher...',
        text: 'Please wait while the teacher details are saved.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('../api/teacher_api.php?action=update', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        Swal.close();
        if (result.success) {
            showToast('Teacher updated successfully!', 'success');
            closeModal('editTeacherModal');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to update teacher', 'error');
        }
    } catch (error) {
        Swal.close();
        console.error('Error:', error);
        showToast('An error occurred while updating the teacher', 'error');
    }
}

async function archiveTeacher(teacherId) {
    let archiveReason = '';

    if (typeof Swal !== 'undefined' && Swal.fire) {
        const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'Please provide a reason before archiving this teacher account.',
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Archive reason',
            inputPlaceholder: 'Enter reason for archiving',
            inputAttributes: {
                maxlength: '500',
                'aria-label': 'Archive reason'
            },
            showCancelButton: true,
            confirmButtonText: 'Archive Teacher',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626',
            preConfirm: (value) => {
                const reason = (value || '').trim();
                if (!reason) {
                    Swal.showValidationMessage('Archive reason is required.');
                }
                return reason;
            }
        });

        if (!result.isConfirmed) {
            return;
        }

        archiveReason = result.value;
    } else {
        archiveReason = (prompt('Enter archive reason:') || '').trim();
        if (!archiveReason) {
            alert('Archive reason is required.');
            return;
        }

        if (!confirm('Archive this teacher? The account will be set to inactive.')) {
            return;
        }
    }

    try {
        const response = await fetch('delete_teacher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ teacher_id: teacherId, archive_reason: archiveReason })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Teacher archived successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to archive teacher', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while archiving the teacher', 'error');
    }
}

async function activateTeacher(userId) {
    const confirmed = await confirmCriticalAction(
        'Activate Teacher?',
        'Are you sure you want to restore this teacher account to active status?',
        'Yes, activate'
    );
    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('../api/teacher_api.php?action=toggle_status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: userId, action: 'activate' })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Teacher activated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to activate teacher', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while activating the teacher', 'error');
    }
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