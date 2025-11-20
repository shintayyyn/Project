$(document).ready(function() {
    const $addForm = $('#addTeacherForm');
    if (!$addForm.length) return;

    const table = $('#teachersTable').DataTable();

    // Remove previous submit handlers to avoid duplicates
    $addForm.off('submit');

    $addForm.on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true);

       $.ajax({
    url: '/dean/teachers/processes/add_teacher.php',
    type: 'POST',
    data: $(this).serialize(),
    dataType: 'json',
    success: function(response) {
        $submitBtn.prop('disabled', false);

        if (response.success) {
            const data = response.data;
            let rowNode;
            const $row = $(`#teachersTable tr[data-teacher-id="${data.t_id}"]`);

            if ($row.length) {
                // ✅ Update existing row
                rowNode = $row[0];
                table.row($row).data([
                    '', 
                    data.idcode || '-',
                    `
                    <div class="d-flex align-items-center gap-2">
                        ${data.t_avatar
                            ? `<img src=" /uploads/teachers/${data.t_avatar}" 
                                    alt="Avatar" class="rounded-circle"
                                    style="width:35px; height:35px; object-fit:cover;">`
                            : `<div class="profile-avatar" style="width:35px; height:35px; font-size:0.9rem;">
                                ${data.t_fname.charAt(0).toUpperCase()}${data.t_lname.charAt(0).toUpperCase()}
                              </div>`}
                        ${data.t_lname}, ${data.t_fname}
                    </div>
                    `,
                     `<span class="badge bg-warning text-dark rounded-pill">${data.degree_code}</span>`,
                    `<span class="badge bg-${data.t_status.toLowerCase() === 'active' ? 'success' : 'danger'}">
                        ${data.t_status.charAt(0).toUpperCase() + data.t_status.slice(1)}
                    </span>`,
                    data.t_email || '',
                    data.t_gender || '',
                    formatDateForDisplay(data.t_bdate),
                    data.t_cnum || '',
                ]).draw(false);

                // highlight and scroll
                table.$('tr').removeClass('active-row');
                $(rowNode).addClass('active-row');

                const $tableContainer = $('#teachersTable').closest('.dataTables_scrollBody');
                if ($tableContainer.length) {
                    $tableContainer.animate({
                        scrollTop: $(rowNode).position().top + $tableContainer.scrollTop() - 50
                    }, 600);
                } else {
                    $('html, body').animate({
                        scrollTop: $(rowNode).offset().top - 100
                    }, 600);
                }

                $row.attr(getRowAttributes(data));

                // rebind row click
                $row.off('click').on('click', function() {
                    teacherDetails(data.t_id);
                });

            } else {
                // ✅ Insert new row dynamically
                rowNode = table.row.add([
                    '', 
                    data.idcode || '-',
                    `
                    <div class="d-flex align-items-center gap-2">
                        ${data.t_avatar
                            ? `<img src=" /uploads/teachers/${data.t_avatar}" 
                                    alt="Avatar" class="rounded-circle"
                                    style="width:35px; height:35px; object-fit:cover;">`
                            : `<div class="profile-avatar" style="width:35px; height:35px; font-size:0.9rem;">
                                ${data.t_fname.charAt(0).toUpperCase()}${data.t_lname.charAt(0).toUpperCase()}
                              </div>`}
                        ${data.t_lname}, ${data.t_fname}
                    </div>
                    `,
                    `<span class="badge bg-warning text-dark rounded-pill">${data.degree_code}</span>`,
                    `<span class="badge bg-${data.t_status.toLowerCase() === 'active' ? 'success' : 'danger'}">
                        ${data.t_status.charAt(0).toUpperCase() + data.t_status.slice(1)}
                    </span>`,
                    data.t_email || '',
                    data.t_gender || '',
                    formatDateForDisplay(data.t_bdate),
                    data.t_cnum || '',
                ]).draw(false).node();

                $(rowNode)
                    .addClass('active-row')
                    .siblings().removeClass('active-row');

                $('html, body').animate({
                    scrollTop: $(rowNode).offset().top - 100
                }, 600);

                $(rowNode).attr(getRowAttributes(data)).addClass('highlight-new');

                $(rowNode).off('click').on('click', function() {
                    teacherDetails(data.t_id);
                });

                // also prepend card
                const $cardContainer = $('#teacherCardsContainer');
                const $card = $(showTeacherDetails(data));
                $cardContainer.prepend($card);

                $card.on('click', function() {
                    teacherDetails(data.t_id);
                });
            }

            // ✅ Success feedback with backend-defined title/message
            showAlert('success', response.title || 'Success', response.message || 'Teacher added successfully.');

            $('#addTeacherModal').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');

            $('#addTeacherForm')[0].reset();
            $('#addTeacherForm').removeClass('was-validated');

        } else {
            showAlert('error', response.title || 'Add Failed', response.message || 'An unexpected error occurred.');
        }
    },
    error: function(xhr) {
        $submitBtn.prop('disabled', false);
        let title = 'Error';
        let msg = 'Failed to add teacher. Please try again later.';

        try {
            const json = JSON.parse(xhr.responseText);
            if (json.title) title = json.title;
            if (json.message) msg = json.message;
        } catch (e) {}

        showAlert('error', title, msg);
    }
});

    });

    $('#addTeacherModal').on('hidden.bs.modal', function () {
        $('body').css('overflow', 'auto');
    });

    // 🔹 Helper: collect attributes for rows
    function getRowAttributes(data) {
        return {
            'data-teacher-id': data.t_id,
            'data-teacher-idcode': data.idcode,
            'data-teacher-name': formatTeacherFullName(data),
            'data-teacher-email': data.t_email,
            'data-teacher-dept': data.degree_code || '-',
            'data-teacher-status': data.t_status,
            'data-teacher-avatar': data.t_avatar,
            'data-teacher-gender': data.t_gender,
            'data-teacher-bdate': formatDateForDisplay(data.t_bdate),
            'data-teacher-cnum': data.t_cnum,
        };
    }


 function showTeacherDetails(data) {
    // Hide default empty state
    $('#noTeacherSelected').hide();

    // Prepare avatar HTML
    const avatarHTML = data.t_avatar
        ? `<img src="/uploads/teachers/${data.t_avatar}" 
                alt="Teacher Avatar" class="rounded-circle mb-3" 
                style="width:120px; height:120px; object-fit:cover;">`
        : `<div class="profile-avatar mx-auto mb-3" 
               style="width:120px; height:120px; font-size:2rem; font-weight:bold;">
               ${data.t_fname.charAt(0).toUpperCase()}${data.t_lname.charAt(0).toUpperCase()}
           </div>`;

    // Prepare status badge
    const statusBadge = data.t_status?.toLowerCase() === 'active'
        ? `<span class="badge rounded-pill bg-success">${data.t_status.charAt(0).toUpperCase() + data.t_status.slice(1)}</span>`
        : `<span class="badge rounded-pill bg-danger">${data.t_status ? data.t_status.charAt(0).toUpperCase() + data.t_status.slice(1) : 'Inactive'}</span>`;

    // Build teacher details HTML
    const teacherDetailsHTML = `
        ${avatarHTML}
        <h5>${formatTeacherFullName(data)}</h5>
        <p class="text-muted mb-1"><strong>ID Code:</strong> ${data.idcode || '-'}</p>
        <p class="text-muted mb-1"><strong>Department:</strong> ${data.degree_code || '-'}</p>
        <p class="text-muted mb-1"><strong>Status:</strong> ${statusBadge}</p>
        <p class="text-muted mb-1"><strong>Email:</strong> ${data.t_email || '-'}</p>
        <p class="text-muted mb-1"><strong>Contact:</strong> ${data.t_cnum || '-'}</p>
        <p class="text-muted mb-1"><strong>Gender:</strong> ${data.t_gender || '-'}</p>
        <p class="text-muted mb-1"><strong>Birthdate:</strong> ${data.t_bdate ? formatDateForDisplay(data.t_bdate) : '-'}</p>
    `;

    // Replace any existing teacher details
    $('#teacherDetailsBody').html(teacherDetailsHTML);
}


    function formatDateForDisplay(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // Highlight animation
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
});
function formatTeacherFullName(data) {
    const lname = data.t_lname || '';
    const suffix = data.t_suffix ? ' ' + data.t_suffix : '';
    const fname = data.t_fname || '';
    const mname = data.t_mname ? ' ' + data.t_mname.charAt(0).toUpperCase() + '.' : '';
    return `${lname}${suffix}, ${fname}${mname}`;
}
  function showAlert(type, message) {
    Swal.fire({
        icon: type, // 'success' | 'error' | 'warning' | 'info' | 'question'
        title: type === 'success' ? 'Success!' : 'Error!',
        text: message,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}
