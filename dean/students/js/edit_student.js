$(document).ready(function () {
    const $editForm = $('#editStudentForm');
    const $editParentForm = $('#editParentForm');
    if (!$editForm.length) return;

    // Remove previous handlers
    $(document).off('click', '.btn-edit-student');
    $(document).off('submit', '#editStudentForm');
    $editForm.off('submit');
    $('#editStudentModal').off('hidden.bs.modal');

    // Handle edit button click
    $(document).on('click', '.btn-edit-student', function (e) {
        e.preventDefault();
        const studentId = $(this).data('student-id');
        loadStudentData(studentId);
    });

    function loadStudentData(id) {
        $.ajax({
            url: `/Project/dean/students/processes/get_student.php?id=${id}`,
            type: 'GET',
            dataType: 'json'
        })
        .done(function (response) {
            if (response.status === 'success') {
                populateForm(response.data);
                $('#editStudentModal').modal('show');
            } else {
                showAlert('error', response.message || 'Failed to fetch student data');
            }
        })
        .fail(function () {
            showAlert('error', 'Failed to fetch student data');
        });
    }

    function populateForm(data) {
    // Student Fields
    $('#edit_s_id').val(data.s_id);
    $('#edit_s_fname').val(data.s_fname);
    $('#edit_s_lname').val(data.s_lname);
    $('#edit_s_mname').val(data.s_mname);
    $('#edit_s_suffix').val(data.s_suffix);
    $('#edit_s_gender').val(data.s_gender);
    $('#edit_s_bdate').val(data.s_bdate);
    $('#edit_s_cnum').val(data.s_cnum);
    $('#edit_s_email').val(data.s_email);
    $('#edit_s_status').val(data.s_status);
    $('#edit_degree_id').val(data.degree_id);

    // Parent Fields
    if (data.parent) {
        $('#edit_p_id').val(data.parent.p_id);
        $('#edit_p_fname').val(data.parent.p_fname);
        $('#edit_p_lname').val(data.parent.p_lname);
        $('#edit_p_mname').val(data.parent.p_mname);
        $('#edit_p_suffix').val(data.parent.p_suffix);
        $('#edit_p_gender').val(data.parent.p_gender);
        $('#edit_p_bdate').val(data.parent.p_bdate);
        $('#edit_p_cnum').val(data.parent.p_cnum);
        $('#edit_p_email').val(data.parent.p_email);
        $('#edit_p_status').val(data.parent.p_status);
        $('#edit_p_password_plain').val(data.parent.p_password_plain);
        $('#edit_child_id').val(data.s_id); // Assign student as their own child
    } else {
        $('#editParentForm')[0].reset();
        $('#edit_p_id').val('');
        $('#edit_child_id').val(data.s_id);
    }
}


    // Submit handler
    $editForm.on('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return false;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        const formData = new FormData(this);

        // Append parent form data
        const parentFormData = new FormData($editParentForm[0]);
        for (let [key, value] of parentFormData.entries()) {
            formData.append(key, value);
        }

        $submitBtn.prop('disabled', true);

        $.ajax({
            url: '/Project/dean/students/processes/edit_student.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
        .done(function (response) {
            if (response.success || response.status === 'success') {
                updateTableRow(response.data);
                $('#editStudentModal').modal('hide');
                showAlert('success', 'Student updated successfully!');
                resetForm($editForm);
                resetForm($editParentForm);
            } else {
                showAlert('error', response.message || 'Update failed');
            }
        })
        .fail(function (jqXHR) {
            console.error('Update failed:', jqXHR.responseText);
            showAlert('error', 'Failed to update student');
        })
        .always(function () {
            $submitBtn.prop('disabled', false);
        });

        return false;
    });

    $('#editStudentModal').on('hidden.bs.modal', function () {
        resetForm($editForm);
        resetForm($editParentForm);
        $editForm.removeClass('was-validated');
    });

    function updateTableRow(data) {
        const $row = $(`tr[data-student-id="${data.s_id}"]`);
        if ($row.length) {
            $row.find('.student-lname').text(data.s_lname);
            $row.find('.student-fname').text(data.s_fname);
            $row.find('.student-mname').text(data.s_mname ? data.s_mname[0] + '.' : '');
            $row.find('.student-suffix').text(data.s_suffix || '');
            $row.find('.student-gender').text(data.s_gender);
            $row.find('.student-bdate').text(formatDateForDisplay(data.s_bdate));
            $row.find('.student-cnum').text(data.s_cnum || '');
            $row.find('.student-email').text(data.s_email || '');
            $row.find('.student-degree').text(data.degree_code || '');

            const $statusCell = $row.find('.student-status');
            const $statusBadge = $statusCell.find('.badge');
            $statusBadge.removeClass('bg-success bg-danger')
                .addClass(data.s_status === 'active' ? 'bg-success' : 'bg-danger')
                .text(data.s_status.charAt(0).toUpperCase() + data.s_status.slice(1));
        } else {
            refreshTable();
        }
    }

    function showAlert(type, message) {
        $('#alertContainer').empty();

        const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill';
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        const $alert = $(alertHtml).appendTo('#alertContainer');

        const alertTimeout = setTimeout(() => {
            $alert.fadeOut('slow', function () { $(this).remove(); });
        }, 3000);

        $(document).on('click', '.btn-edit-student', function () {
            clearTimeout(alertTimeout);
            $alert.remove();
        });
    }

    function refreshTable() {
        // Optional: Add table refresh logic if needed
    }

    function resetForm($form) {
        $form[0].reset();
        $form.removeClass('was-validated');
    }

    $(document).on('click', '.eye-button', function () {
        const $wrapper = $(this).closest('.password-wrapper');
        const $dots = $wrapper.find('.dots');
        const $password = $wrapper.find('.real-password');
        const $icon = $(this).find('i');

        if ($dots.is(':visible')) {
            $dots.hide();
            $password.show();
            $icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            $dots.show();
            $password.hide();
            $icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    function formatDateForDisplay(dateStr) {
        const [year, month, day] = dateStr.split('-');
        return `${month}/${day}/${year}`;
    }
});
