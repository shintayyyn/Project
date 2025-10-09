$(document).ready(function() {
    // Delete student handler using event delegation
    $(document).on('click', '.btn-delete-student', function(e) {
        e.preventDefault();
        const studentId = $(this).data('student-id');
        
        if (confirm('Are you sure you want to delete this student?')) {
            $.ajax({
                url: '/Project/dean/students/processes/delete_student.php',
                type: 'POST',
                data: { s_id: studentId },
                dataType: 'json'
            })
            .done(function(response) {
                if (response.success) {
                    // Remove the row with animation
                    $(`tr[data-student-id="${studentId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        // Show success message
                        showAlert('success', response.message);
                        // Check if table is empty
                        if ($('#studentsTableBody tr').length === 0) {
                            $('#studentsTableBody').html('<tr><td colspan="13" class="text-center">No students found</td></tr>');
                        }
                    });
                } else {
                    showAlert('error', response.message || 'Failed to delete student');
                }
            })
            .fail(function(xhr) {
                console.error('Delete failed:', xhr.responseText);
                showAlert('error', 'Failed to delete student');
            });
        }
    });

    // Helper function to show alerts
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
});