// AJAX Teacher Login Script

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const feedback = document.getElementById('loginFeedback');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            feedback.textContent = '';
            feedback.className = '';

            const formData = new FormData(form);
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('dashboard.php')) {
                    feedback.textContent = 'Login successful! Redirecting...';
                    feedback.className = 'feedback success';
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1200);
                } else if (html.includes('Invalid email or password')) {
                    feedback.textContent = 'Invalid email or password.';
                    feedback.className = 'feedback error';
                } else {
                    feedback.textContent = 'Login failed. Please try again.';
                    feedback.className = 'feedback error';
                }
            })
            .catch(() => {
                feedback.textContent = 'Network error. Please try again.';
                feedback.className = 'feedback error';
            });
        });
    }
});

// Simple fade animation for feedback
const style = document.createElement('style');
style.innerHTML = `
.feedback {
  margin-top: 16px;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 1rem;
  opacity: 0;
  transition: opacity 0.4s;
}
.feedback.success { background: #e0ffe0; color: #2e7d32; border: 1px solid #81c784; opacity: 1; }
.feedback.error { background: #ffe0e0; color: #c62828; border: 1px solid #e57373; opacity: 1; }
`;
document.head.appendChild(style);
