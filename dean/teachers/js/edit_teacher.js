$(document).ready(function() {
    const $editForm = $('#editTeacherForm');
    if (!$editForm.length) return;

    // ✅ Initialize DataTable globally
    const table = $('#teachersTable').DataTable();

    // Remove ALL existing handlers to avoid duplicate bindings
    $(document).off('click', '.btn-edit-teacher');
    $(document).off('submit', '#editTeacherForm');
    $editForm.off('submit');
    $('#editTeacherModal').off('hidden.bs.modal');

    /**
     * ===========================
     *  Load Teacher Data to Modal
     * ===========================
     */
    $(document).on('click', '.btn-edit-teacher', function(e) {
        e.preventDefault();
        const teacherId = $(this).data('teacher-id');
        loadTeacherData(teacherId);
    });

    function loadTeacherData(id) {
        $.ajax({
            url: '/dean/teachers/processes/get_teacher.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateForm(response.data);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to fetch teacher details.',
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Unable to load teacher data. Please try again later.',
                });
            }
        });
    }

    function populateForm(data) {
        $('#edit_t_id').val(data.t_id);
        $('#edit_t_fname').val(data.t_fname);
        $('#edit_t_lname').val(data.t_lname);
        $('#edit_t_mname').val(data.t_mname);
        $('#edit_t_suffix').val(data.t_suffix);
        $('#edit_t_gender').val(data.t_gender);
        $('#edit_t_bdate').val(data.t_bdate);
        $('#edit_t_cnum').val(data.t_cnum);
        $('#edit_t_email').val(data.t_email);
        $('#edit_t_department').val(data.t_department);
        $('#edit_t_status').val(data.t_status);
    }

    /**
     * =======================
     *  Submit Edit Form (AJAX)
     * =======================
     */
    $editForm.on('submit', function(e) {
    e.preventDefault();
    if (!this.checkValidity()) {
        e.stopPropagation();
        $(this).addClass('was-validated');
        return;
    }

    const $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true);

    $.ajax({
        url: '/dean/teachers/processes/update_teacher.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
    $submitBtn.prop('disabled', false);

if (response.success) {
    // ✅ Update row inline
    const $row = $(`#teachersTable tr[data-teacher-id="${response.data.t_id}"]`);
     if ($row.length) {
    const degreeBadge = `<span class="badge rounded-pill bg-warning text-dark">${response.data.degree_code || '-'}</span>`;

    table.row($row).data([
        '', // control
        response.data.idcode || '-',
        `
        <div class="d-flex align-items-center gap-2">
            ${response.data.t_avatar
                ? `<img src="/uploads/teachers/${response.data.t_avatar}" 
                        alt="Avatar" class="rounded-circle"
                        style="width:35px; height:35px; object-fit:cover;">`
                : `<div class="profile-avatar" style="width:35px; height:35px; font-size:0.9rem;">
                       ${getTeacherInitials(response.data)}
                   </div>`}
            ${formatTeacherFullName(response.data)}
        </div>
        `,
        degreeBadge,
        `<span class="badge bg-${response.data.t_status.toLowerCase() === 'active' ? 'success' : 'danger'}">
            ${response.data.t_status.charAt(0).toUpperCase() + response.data.t_status.slice(1)}
        </span>`,
        response.data.t_email || '',
        response.data.t_gender || '',
        formatDateForDisplay(response.data.t_bdate),
        response.data.t_cnum || ''
    ]).draw(false);

    // ✅ Update data attributes (for row click use)
    $row.attr({
        'data-teacher-id': response.data.t_id,
        'data-teacher-idcode': response.data.idcode,
        'data-teacher-name': formatTeacherFullName(response.data),
        'data-teacher-email': response.data.t_email,
        'data-teacher-dept': response.data.degree_code || '-',
        'data-teacher-status': response.data.t_status,
        'data-teacher-avatar': response.data.t_avatar,
        'data-teacher-gender': response.data.t_gender,
        'data-teacher-bdate': formatDateForDisplay(response.data.t_bdate),
        'data-teacher-cnum': response.data.t_cnum
    });
}

    // ✅ ALSO refresh teacher details card if the same teacher is selected
    const $activeRow = $('#teachersTable tbody tr.active-row');
    if ($activeRow.length && $activeRow.data('teacher-id') == response.data.t_id) {
        // Rebuild the details card with updated info
        const avatarHTML = response.data.t_avatar
            ? `<img src="/uploads/teachers/${response.data.t_avatar}" 
                   alt="Teacher Avatar" class="rounded-circle mb-3" 
                   style="width:120px; height:120px; object-fit:cover;">`
            : `<div class="profile-avatar mx-auto mb-3" 
                   style="width:120px; height:120px; font-size:2rem; font-weight:bold;">
                   ${getTeacherInitials(response.data)}
               </div>`;

        const statusBadge = response.data.t_status.toLowerCase() === 'active'
            ? `<span class="badge rounded-pill bg-success">Active</span>`
            : `<span class="badge rounded-pill bg-danger">Inactive</span>`;

        $('#teacherDetailsBody').html(`
            ${avatarHTML}
            <h5 class="fw-bold">${formatTeacherFullName(response.data)}</h5>
            <p class="text-muted mb-1"><strong>ID Code:</strong> ${response.data.idcode}</p>
            <p class="text-muted mb-1"><strong>Email:</strong> ${response.data.t_email || ''}</p>
            <p class="text-muted mb-1"><strong>Department:</strong> ${response.data.degree_code || '-'}</p>
            <p class="text-muted mb-1"><strong>Status:</strong> ${statusBadge}</p>
            <p class="text-muted mb-1"><strong>Gender:</strong> ${response.data.t_gender || '-'}</p>
            <p class="text-muted mb-1"><strong>Birthdate:</strong> ${formatDateForDisplay(response.data.t_bdate)}</p>
            <p class="text-muted mb-1"><strong>Contact:</strong> ${response.data.t_cnum || '-'}</p>
            
            <div class="action-buttons d-flex justify-content-center gap-2 mt-3">
                <button class="btn btn-sm btn-primary btn-edit-teacher" 
                        data-bs-toggle="modal" 
                        data-bs-target="#editTeacherModal" 
                        data-teacher-id="${response.data.t_id}">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
                <button class="btn btn-sm btn-danger btn-delete-teacher" 
                        data-teacher-id="${response.data.t_id}">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        `);
    }

    showAlert('success', response.message || 'Teacher updated successfully.');
    $('#editTeacherModal').modal('hide');
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
}
 else {
        // ❌ failed update
        showAlert('error', response.message || 'An unexpected error occurred.');
    }
},
error: function(xhr) {
    $submitBtn.prop('disabled', false);
    console.error("Update error:", xhr.responseText);

    showAlert('error', 'Failed to update teacher. Please try again later.');
}

    });
});
$('#editTeacherModal').on('hidden.bs.modal', function () {
    $('body').css('overflow', 'auto');
});


function formatDateForDisplay(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString; // fallback if invalid date
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTeacherFullName(data) {
    const lname = data.t_lname || '';
    const suffix = data.t_suffix ? ' ' + data.t_suffix : '';
    const fname = data.t_fname || '';
    const mname = data.t_mname ? ' ' + data.t_mname.charAt(0).toUpperCase() + '.' : '';
    return `${lname}${suffix}, ${fname}${mname}`;
}

function getTeacherInitials(data) {
    const f = data.t_fname ? data.t_fname.charAt(0).toUpperCase() : '';
    const m = data.t_mname ? data.t_mname.charAt(0).toUpperCase() : '';
    const l = data.t_lname ? data.t_lname.charAt(0).toUpperCase() : '';
    return `${f}${m}${l}` || '??';
}

});

function showAlert(type, message) {
    Swal.fire({
        icon: type, // 'success' | 'error' | 'warning' | 'info' | 'question'
        title: type === 'success' ? 'Success!' : 'Error!',
        meesage,
        text: message,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}
