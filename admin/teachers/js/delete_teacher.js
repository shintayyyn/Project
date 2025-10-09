// Delete teacher handler
$(document).ready(function () {
    const table = $('#teachersTable').DataTable();

    // Remove old event bindings
    $(document).off('click', '.btn-delete-teacher');

    // Handle delete button click
    $(document).on('click', '.btn-delete-teacher', function () {
        const teacherId = $(this).data('teacher-id'); // from data-id attribute
        if (!teacherId) {
            showAlert('error', 'Invalid Teacher', 'Teacher ID is missing.');
            return;
        }

        // 🔔 Confirm delete with SweetAlert
        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            // Proceed with AJAX delete
            $.ajax({
                url: '/admin/teachers/processes/delete_teacher.php',
                type: 'POST',
                data: { t_id: teacherId },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        // Remove row from DataTable
                        table.row($(`tr[data-teacher-id="${teacherId}"]`)).remove().draw();

                        // Reset teacher details panel
                        $('#teacherDetailsBody').html(`
                            <div class="card-body-empty">
                                <i class="bi bi-person-lines-fill"></i>
                                No teacher selected.
                            </div>
                        `);

                        showAlert('success', 'Deleted!', 'Teacher deleted successfully.');
                    } else {
                        showAlert('error', 'Failed', res.message || 'Could not delete teacher.');
                    }
                },
                error: function () {
                    showAlert('error', 'Error', 'An error occurred while deleting the teacher.');
                }
            });
        });
    });

    // ✅ Unified alert handler
    function showAlert(type, title, message) {
        Swal.fire({
            icon: type, // 'success' | 'error' | 'warning' | 'info' | 'question'
            title: title,
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
});