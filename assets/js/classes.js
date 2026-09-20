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

// Delete class functionality
async function deleteClass(classId) {
    const confirmed = await confirmCriticalAction(
        'Delete Class?',
        'Are you sure you want to delete this class? This action cannot be undone.',
        'Yes, delete class'
    );
    if (!confirmed) return;
    fetch('../api/class_api.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'delete_class', class_id: classId })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message);
        if (data.success) {
            // Reload the page to show updated class list with server-side rendering
            location.reload();
        }
    });
}
// JS for Class Management Modal and Table

document.addEventListener('DOMContentLoaded', function() {
    // Classes are now rendered server-side, no need to load them dynamically
    // loadClasses(); // Commented out since we use server-side rendering
});

function openModal(id) {
    document.getElementById(id).style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Removed loadClasses function as we now use server-side rendering

function submitClass(event) {
    event.preventDefault();
    showToast('Only admins can create classes.');
    return;

    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create_class');
    formData.set('sy_id', document.getElementById('syId').value);
    fetch('../api/class_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message);
        if (data.success) {
            closeModal('addClassModal');
            form.reset();
            // Reload the page to show updated class list with server-side rendering
            location.reload();
        }
    });
}

function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.querySelector('.toast-message').textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2000);
}
