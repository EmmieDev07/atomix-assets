// Edit Class functionality for Class Management
function editClass(classId) {
    // Find the row for the class
    const row = document.querySelector(`tr[data-id='${classId}']`);
    if (!row) return;
    const className = row.children[0].textContent;
    const schoolYear = row.children[1].textContent;

    // Populate the add/edit modal with class data
    document.getElementById('className').value = className;
    // Set the school year dropdown
    const sySelect = document.getElementById('syId');
    for (let i = 0; i < sySelect.options.length; i++) {
        if (sySelect.options[i].text === schoolYear) {
            sySelect.selectedIndex = i;
            break;
        }
    }

    // Change form action to edit
    const form = document.getElementById('addClassForm');
    form.onsubmit = function(event) {
        event.preventDefault();
        const formData = new FormData(form);
        formData.append('action', 'edit_class');
        formData.append('class_id', classId);
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
                // Restore form to add mode
                form.onsubmit = submitClass;
            }
        });
    };
    // Open the modal
    openModal('addClassModal');
}
