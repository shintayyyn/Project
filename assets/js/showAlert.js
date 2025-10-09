 function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alertContainer');
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible`;
        alertDiv.style.opacity = 0;
        alertDiv.style.transform = 'translateY(20px)';
        alertDiv.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertContainer.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.style.transition = 'all 0.5s ease-in-out';
            alertDiv.style.opacity = 1;
            alertDiv.style.transform = 'translateY(0)';
        }, 10);

        setTimeout(() => {
            alertDiv.style.opacity = 0;
            alertDiv.style.transform = 'translateY(20px)';
            setTimeout(() => alertDiv.remove(), 500);
        }, 3000);
    }
document.addEventListener('DOMContentLoaded', function () {
    const elements = document.querySelectorAll('center, .parent-header-card');
    elements.forEach(el => el.classList.add('loaded'));
});

