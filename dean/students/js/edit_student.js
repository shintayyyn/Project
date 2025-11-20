$(document).ready(function() {
    const $editForm = $('#editStudentForm');
    if (!$editForm.length) return;

    const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));

    function calculateAge(bdate) {
        if (!bdate) return '';
        const birthDate = new Date(bdate);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    // ==================== Row Click ====================
    $('#studentsTable tbody').on('click', 'tr', function() {
        $('#studentsTable tbody tr').removeClass('active-row');
        $(this).addClass('active-row');
        const data = buildDataFromRow($(this));
        updateStudentDetails(data);
    });

    // ==================== Edit Button Click ====================
    $(document).on('click', '.btn-edit-student', function(e) {
        e.preventDefault();
        const $activeRow = $('#studentsTable tbody tr.active-row');
        if (!$activeRow.length) return;
        const studentId = $activeRow.data('student-id');
        if (!studentId) return;
        loadStudentData(studentId);
    });

    // ==================== Load Student Data into Modal ====================
    function loadStudentData(id) {
        $.ajax({
            url: `/dean/students/processes/get_student.php?id=${id}`,
            type: 'GET',
            dataType: 'json'
        })
        .done(function(res) {
            if (res.status === 'success') {
                populateForm(res.data);
                editModal.show();
            } else {
                showAlert('error', res.message || 'Failed to fetch student data');
            }
        })
        .fail(function() {
            showAlert('error', 'Failed to fetch student data');
        });
    }

    function populateForm(data) {
        $('#edit_s_id').val(data.s_id);
        $('#edit_s_fname').val(data.s_fname);
        $('#edit_s_lname').val(data.s_lname);
        $('#edit_s_mname').val(data.s_mname || '');
        $('#edit_s_suffix').val(data.s_suffix || '');
        $('#edit_s_gender').val(data.s_gender);
        $('#edit_s_bdate').val(data.s_bdate);
        $('#edit_s_age').val(calculateAge(data.s_bdate));
        $('#edit_s_cnum').val(data.s_cnum || '');
        $('#edit_s_address').val(data.s_address || '');
        $('#edit_s_email').val(data.s_email);
        $('#edit_s_status option').filter(function() {
    return $(this).val().toLowerCase() === (data.s_status || '').toLowerCase();
}).prop('selected', true);

        $('#edit_year_level').val(data.year_level || '');
        $('#edit_is_regular').prop('checked', !!data.is_regular);
        $('#edit_degree_id').val(data.degree_id || '');
    }

    // ==================== Submit Edit Form ====================
    $editForm.on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return false;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        const formData = new FormData(this);
        $submitBtn.prop('disabled', true);

        $.ajax({
            url: '/dean/students/processes/edit_student.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
        .done(function(res) {
            if (res.success || res.status === 'success') {
                res.data.s_age = calculateAge(res.data.s_bdate);
                res.data.parent_fullname = res.data.parent_fullname || 'Solo (No Parent)';

                const $row = updateTableRow(res.data);

                // Update active row and panel immediately
                $row.addClass('active-row');
                updateStudentDetails(buildDataFromRow($row));

                $submitBtn.prop('disabled', false);
                editModal.hide();
                resetForm($editForm);
                showAlert('success', 'Student updated successfully!');
            } else {
                $submitBtn.prop('disabled', false);
                showAlert('error', res.message || 'Update failed');
            }
        })
        .fail(function(jqXHR) {
            console.error('Update failed:', jqXHR.responseText);
            showAlert('error', 'Failed to update student');
        });
    });

    // ==================== Update Table Row ====================
    function updateTableRow(data) {
        const table = $('#studentsTable').DataTable();
        let $row = $(`tr[data-student-id="${data.s_id}"]`);
        const fullName = `${data.s_lname}${data.s_suffix ? ' ' + data.s_suffix : ''}, ${data.s_fname}${data.s_mname ? ' ' + data.s_mname[0] + '.' : ''}`;
        const initialsUpper = getInitials(data.s_fname, data.s_mname, data.s_lname);

        if (!$row.length) {
            // Optionally, add new row if it doesn't exist
            const newRowHtml = `<tr data-student-id="${data.s_id}"></tr>`;
            $('#studentsTable tbody').append(newRowHtml);
            $row = $(`tr[data-student-id="${data.s_id}"]`);
        }

        // Update dataset
        $row.data({
            studentId: data.s_id,
            idcode: data.idcode,
            fullname: fullName,
            section_code: data.section_code || '',
            email: data.s_email,
            parent: data.parent_fullname || 'No record',
            status: data.s_status,
            gender: data.s_gender,
            bdate: data.s_bdate,
            age: data.s_age || '',
            cnum: data.s_cnum || '',
            address: data.s_address || '',
            degree: data.degree_code || '',
            avatar: data.avatar || '',
            year_level: data.year_level || '',
            is_regular: data.is_regular || false
        });

        // Update row columns
        $row.html(`
            <td>${data.idcode}</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    ${data.avatar
                        ? `<img src="${data.avatar}" alt="Avatar" class="rounded-circle" style="width:35px; height:35px; object-fit:cover;">`
                        : `<div class="profile-avatar rounded-circle text-center text-white"
                               style="width:35px; height:35px; font-size:0.9rem; line-height:35px;">
                               ${initialsUpper}
                           </div>`
                    }
                    ${fullName}
                </div>
            </td>
            <td>${data.section_code
                    ? `<span class="badge rounded-pill" style="background: var(--tertiary); color: var(--primary);">${data.section_code}</span>`
                    : `<span class="badge bg-secondary rounded-pill">Not yet assigned</span>`
                }</td>
            <td class="d-none">
                
                ${data.is_regular == 1 || data.is_regular === true || data.is_regular === "1"
            ? `<span class="badge rounded-pill bg-success">REGULAR</span>`
            : `<span class="badge rounded-pill bg-danger">IRREGULAR</span>`
        }
            </td>
        `);

        table.row($row).invalidate().draw(false);
        return $row;
    }

    // ==================== Build Data Object from Row ====================
    function buildDataFromRow($row) {
        const fullnameParts = ($row.data('fullname') || '').split(',');
        const lname = fullnameParts[0] || '';
        const fnameM = (fullnameParts[1] || '').trim().split(' ');

        return {
            s_id: $row.data('studentId'),
            idcode: $row.data('idcode'),
            s_fname: fnameM[0] || '',
            s_mname: fnameM[1]?.replace('.', '') || '',
            s_lname: lname,
            s_suffix: '',
            s_email: $row.data('email'),
            parent_fullname: $row.data('parent'),
            s_status: $row.data('status'),
            s_gender: $row.data('gender'),
            s_bdate: $row.data('bdate'),
            s_age: $row.data('age'),
            s_cnum: $row.data('cnum'),
            s_address: $row.data('address'),
            degree_code: $row.data('degree'),
            avatar: $row.data('avatar') || '',
            year_level: $row.data('year_level') || '',
            is_regular: $row.data('is_regular') || false,
            section_code: $row.data('section_code') || ''
        };
    }

    // ==================== Update Personal Info Panel ====================
    function updateStudentDetails(data) {
        const fullName = `${data.s_lname || ''}${data.s_suffix ? ' ' + data.s_suffix : ''}, ${data.s_fname || ''}${data.s_mname ? ' ' + data.s_mname[0] + '.' : ''}`;

        $('#detailId').text(`ID: ${data.idcode || 'N/A'}`);
        $('#detailFullName').text(fullName || 'N/A');
        $('#detailEmail').text(data.s_email || data.email || '');

        const parentText = data.parent_fullname || data.parent || 'No Parent, Solo';
        $('#detailParent').html(`<span class="badge bg-info">${parentText}</span>`);

        // ✅ Status + Regularity
        const statusText = (data.s_status || data.status || 'Active').toUpperCase();
        const statusBadge = `<span class="badge bg-primary">${statusText}</span>`;

        const regularityBadge = (data.is_regular == 1 || data.is_regular === true || data.is_regular === "1")
            ? '<span class="badge bg-success ms-1">REGULAR</span>'
            : '<span class="badge bg-danger ms-1">IRREGULAR</span>';

        $('#detailStatus').html(`${statusBadge} ${regularityBadge}`);

        $('#detailGender').text(data.s_gender || data.gender || '');
        $('#detailBdate').text(data.s_bdate || data.bdate || '');
        $('#detailAge').text(data.s_age || calculateAge(data.s_bdate));
        $('#detailCnum').text(data.s_cnum || data.cnum || '');
        $('#detailAddress').text(data.s_address || data.address || '');
        $('#detailDegree').text(data.degree_code || data.degree || '');
        $('#detailYearLevel').text(data.year_level || '-');
        $('#detailActiveTerm').text(data.active_term || data.term_name || '');

        // Avatar
        const avatarImg = document.getElementById("studentAvatar");
        const avatarInitials = document.getElementById("studentInitials");
        if (data.avatar) {
            avatarImg.src = data.avatar;
            avatarImg.classList.remove("d-none");
            avatarInitials.classList.add("d-none");
        } else {
            avatarInitials.textContent = getInitials(data.s_fname, data.s_mname, data.s_lname);
            avatarInitials.classList.remove("d-none");
            avatarImg.classList.add("d-none");
        }

        $(".btn-delete-student").attr("data-student-id", data.s_id);
    }

    // ==================== Helpers ====================
    function showAlert(type, message) {
        Swal.fire({
            icon: type,
            title: type === 'success' ? 'Success!' : 'Error!',
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    function resetForm($form) {
        $form[0].reset();
        $form.removeClass('was-validated');
    }

    function getInitials(fname, mname, lname) {
        let initials = '';
        if (fname) initials += fname.charAt(0).toUpperCase();
        if (mname) initials += mname.charAt(0).toUpperCase();
        if (lname) initials += lname.charAt(0).toUpperCase();
        return initials || "??";
    }

    // ==================== Auto-select First Row on Load ====================
    const $firstRow = $('#studentsTable tbody tr').first();
    if ($firstRow.length) {
        $firstRow.addClass('active-row');
        updateStudentDetails(buildDataFromRow($firstRow));
    }

    // ==================== Helper: Get Initials ====================
    function getInitials(fname, mname, lname) {
        let initials = '';
        if (fname) initials += fname.charAt(0).toUpperCase();
        if (mname) initials += mname.charAt(0).toUpperCase();
        if (lname) initials += lname.charAt(0).toUpperCase();
        return initials || "??";
    }
});
