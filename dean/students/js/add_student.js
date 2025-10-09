$(document).ready(function() {
    const $addForm = $('#addStudentForm');
    if (!$addForm.length) return;

    // Name field validation
    $('.name-input').on('input', function() {
        let value = $(this).val();
        value = value.replace(/[^A-Za-z\s-]/g, '');
        value = value.toLowerCase().split(/[\s-]+/).map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
        $(this).val(value);
    });

    // Phone number validation
    $('input[name="s_cnum"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        $(this).val(value);
        
        if (value.length === 11 && value.startsWith('09')) {
            $(this).removeClass('is-invalid');
            this.setCustomValidity('');
        } else {
            $(this).addClass('is-invalid');
            this.setCustomValidity('Must be 11 digits starting with 09');
        }
    });

    // Form submission
    $addForm.on('submit', function(e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        const formData = new FormData(this);

        // Append parent form data
        const parentFormData = new FormData($('#addParentForm')[0]);
        for (let [key, value] of parentFormData.entries()) {
            formData.append(key, value);
        }

        $submitBtn.prop('disabled', true);

        $.ajax({
            url: '/Project/dean/students/processes/add_student.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal and reset form
                    $('#addStudentModal').modal('hide');
                    $addForm[0].reset();
                    $('#addParentForm')[0].reset();
                    $addForm.removeClass('was-validated');
                    showAlert('success', 'Student added successfully');
                    
                    // Check if "No students found" row exists and remove it
                    const noDataRow = $('#studentsTableBody tr td[colspan]');
                    if (noDataRow.length) {
                        noDataRow.parent().remove();
                    }

                    // Create and add new row
                    const newRow = createStudentRow(response.data);
                    $('#studentsTableBody').prepend(newRow);
                    
                    // Highlight new row briefly
                    const $newRow = $(`tr[data-student-id="${response.data.s_id}"]`);
                    $newRow.addClass('highlight-new');
                    setTimeout(() => $newRow.removeClass('highlight-new'), 3000);
                } else {
                    showAlert('error', response.message || 'Failed to add student');
                }
            },
            error: function(xhr) {
                showAlert('error', 'Server error occurred while adding student');
                console.error('Add failed:', xhr.responseText);
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
            }
        });
    });

    function createStudentRow(data) {
        return `
            <tr data-student-id="${data.s_id}">
                <td>${data.s_id}</td>
                <td class="student-lname">${data.s_lname}</td>
                <td class="student-fname">${data.s_fname}</td>
                <td class="text-center student-mname">${data.s_mname ? data.s_mname[0] + '.' : ''}</td>
                <td class="text-center student-suffix">${data.s_suffix || ''}</td>
                <td class="text-center student-gender">${data.s_gender}</td>
                <td class="student-bdate">${formatDate(data.s_bdate)}</td>
                <td class="text-center">${calculateAge(data.s_bdate)}</td>
                <td class="student-cnum text-center">${data.s_cnum}</td>
                <td class="text-truncate student-email">${data.s_email}</td>
                <td class="td-password">
                    <div class="password-wrapper">
                        <span class="dots">••••••••</span>
                        <span class="real-password" style="display: none;">${data.s_password}</span>
                        <button type="button" class="eye-button">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
                <td class="text-center student-status">
                    <span class="badge bg-${data.s_status === 'active' ? 'success' : 'danger'}">
                        ${capitalizeFirst(data.s_status)}
                    </span>
                </td>
                <td class="text-center student-degree">${data.degree_code || ''}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-primary px-2 btn-edit-student" data-bs-toggle="modal" data-bs-target="#editStudentModal" data-student-id="${data.s_id}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger px-2 btn-delete-student" data-student-id="${data.s_id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
    }

    // Helper functions
    function showAlert(type, message) {
        const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill';
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('#alertContainer').html(alertHtml);
        setTimeout(() => {
            $('.alert').fadeOut('slow', function() { $(this).remove(); });
        }, 3000);
    }

    function showNotification(type, message) {
        const notificationHtml = `
            <span class="notification notification-${type}">
                ${message}
            </span>`;
        $('#notificationContainer').html(notificationHtml);
        setTimeout(() => $('.notification').fadeOut(), 5000);
    }

    function formatDate(dateString) {
        const [year, month, day] = dateString.split('-');
        return `${month}/${day}/${year}`;
    }

    function calculateAge(birthDate) {
        const birth = new Date(birthDate);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }

    function capitalizeFirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
});

// Add this CSS to your stylesheet
const style = document.createElement('style');
style.textContent = `
    @keyframes highlightNew {
        from { background-color: rgba(25, 135, 84, 0.1); }
        to { background-color: transparent; }
    }
    .highlight-new {
        animation: highlightNew 3s ease-out;
    }
`;
document.head.appendChild(style);