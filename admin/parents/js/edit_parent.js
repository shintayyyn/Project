$(document).ready(function () {

    // ==================== DataTable ====================

    // ==================== Helper: Show Alert ====================
    function showAlert(type, message) {
        Swal.fire({
            icon: type,
            title: type === 'success' ? 'Success!' :
                   type === 'warning' ? 'Warning!' : 'Error!',
            text: message,
            timer: 6500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            showClass: { popup: 'animate__animated animate__fadeInDown' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });
    }

    // ==================== Helper: Update Parent Details Card ====================
    function updateParentDetailsCard(parent) {
        const statusBadge = parent.p_status.toLowerCase() === 'active'
            ? `<span class="badge rounded-pill bg-success">Active</span>`
            : `<span class="badge rounded-pill bg-danger">Inactive</span>`;

        const avatarHTML = parent.avatar
            ? `<img src="${parent.avatar}" class="rounded-circle mb-3" style="width:120px; height:120px; object-fit:cover;">`
            : `<div class="profile-avatar mx-auto mb-3" style="width:120px; height:120px; font-size:2rem;">
                   ${parent.initials || parent.full_name.split(/[ ,]+/).map(n => n.charAt(0)).join('').substring(0,2).toUpperCase()}
               </div>`;

        const childDisplay = parent.child_name
            ? `${parent.child_name} - ${parent.section_code ?? '<span class="badge bg-warning text-dark">Not Assigned</span>'}`
            : 'No child linked';

        $('#parentDetailsBody').html(`
            ${avatarHTML}
            <h5>${parent.full_name}</h5>
            <p class="text-muted mb-1"><strong>Email:</strong> ${parent.p_email}</p>
            <p class="text-muted mb-1"><strong>Status:</strong> ${statusBadge}</p>
            <p class="text-muted mb-1"><strong>Gender:</strong> ${parent.p_gender}</p>
            <p class="text-muted mb-1"><strong>Birthdate:</strong> ${parent.p_bdate}</p>
            <p class="text-muted mb-1"><strong>Contact:</strong> ${parent.p_cnum}</p>
            <p class="text-muted mb-1"><strong>Address:</strong> ${parent.p_address || '-'}</p>
            <div class="action-buttons d-flex justify-content-center gap-2 mt-3">
                <button class="btn btn-sm btn-primary btn-edit-parent" data-bs-toggle="modal" data-bs-target="#editParentModal" data-parent-id="${parent.p_id}">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
                <button class="btn btn-sm btn-danger btn-delete-parent" data-parent-id="${parent.p_id}">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        `);
    }

    // ==================== Row Click ====================
    $('#parentsTable tbody').on('click', 'tr', function () {
        $('#parentsTable tbody tr').removeClass('active-row');
        $(this).addClass('active-row');

        const parent = {
            p_id: $(this).data('parent-id'),
            idcode: $(this).data('parent-idcode'),
            full_name: $(this).data('parent-name'),
            p_email: $(this).data('parent-email'),
            p_status: $(this).data('parent-status'),
            p_gender: $(this).data('parent-gender'),
            p_bdate: $(this).data('parent-bdate'),
            p_cnum: $(this).data('parent-cnum'),
            p_address: $(this).data('parent-address'),
            avatar: $(this).data('parent-avatar'),
            initials: $(this).data('parent-name').split(' ').map(n => n[0]).join('').toUpperCase()
        };

        $('#parentDetailsCard').data('selected-parent-id', parent.p_id);
        updateParentDetailsCard(parent);
    });

    // ==================== Edit Button Click ====================
    $(document).on('click', '.btn-edit-parent', function () {
        const parentId = $(this).data('parent-id');

        $.ajax({
            url: '/admin/parents/processes/edit_parent.php',
            type: 'GET',
            data: { id: parentId },
            dataType: 'json',
           success: function (response) {
    if (response.success) {
        const parent = response.parent;

        // Populate form fields
        $('#edit_p_id').val(parent.p_id);
        $('#edit_p_fname').val(parent.p_fname);
        $('#edit_p_lname').val(parent.p_lname);
        $('#edit_p_mname').val(parent.p_mname);
        $('#edit_p_suffix').val(parent.p_suffix);
        $('#edit_p_gender').val(parent.p_gender);
        $('#edit_p_bdate').val(parent.p_bdate);
        $('#edit_p_cnum').val(parent.p_cnum);
        $('#edit_p_email').val(parent.p_email);
        $('#edit_p_status').val(parent.p_status);
        $('#edit_p_address').val(parent.p_address);

        // Populate child select with address attribute
        const $childSelect = $('#editParentForm select[name="child_id"]');
        if ($childSelect.length) {
            $childSelect.empty();
            if (response.children && response.children.length > 0) {
                $.each(response.children, function (index, child) {
                    $('<option>', {
                        value: child.s_id,
                        text: `${child.full_name} - ${child.section_code || 'Not Assigned'}`,
                        'data-address': child.s_address || ''
                    }).appendTo($childSelect);
                });
            } else {
                $('<option>', { value: '', text: 'No linked children' }).appendTo($childSelect);
            }
        }

        // Reset checkbox each time
        $('#sameAsChildAddress').prop('checked', false);

        // Handle checkbox toggle
        $('#sameAsChildAddress').off('change').on('change', function () {
            if (this.checked) {
                const selectedChild = $childSelect.find('option:selected');
                const childAddress = selectedChild.data('address');
                if (childAddress) {
                    $('#edit_p_address').val(childAddress).trigger('change');
                } else {
                    showAlert('warning', 'Selected child has no address available.');
                    $(this).prop('checked', false);
                }
            }
        });

        $('#editParentModal').modal({ backdrop: 'static', keyboard: false }).modal('show');
    } else {
        showAlert('error', 'Failed to load parent data: ' + (response.error || 'Unknown error'));
    }
},
            error: function (xhr) {
                showAlert('error', 'Error loading parent data: ' + xhr.responseText);
            }
        });
    });

   // ==================== Submit Edit Form ====================
$('#editParentForm').on('submit', function (e) {
    e.preventDefault();
    const form = $(this);

    if (!form[0].checkValidity()) {
        e.stopPropagation();
        form.addClass('was-validated');
        showAlert('warning', 'Please fill all required fields before submitting.');
        return;
    }

    // Use FormData instead of serialize()
    const formData = new FormData(this);

    $.ajax({
        url: '/admin/parents/processes/update_parent.php',
        type: 'POST',
        data: formData,
        processData: false,   // ❗ required for FormData
        contentType: false,   // ❗ required for FormData
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $('#editParentModal').modal('hide');
                showAlert('success', response.message);

                let updatedParent = response.parent;

                // ✅ Ensure address is synced
                updatedParent.p_address = $('#edit_p_address').val();

                // Update DataTable row
                const row = $(`#parentsTable tbody tr[data-parent-id="${updatedParent.p_id}"]`);
                if (row.length) {
                    row.attr('data-parent-name', updatedParent.full_name);
                    row.attr('data-parent-email', updatedParent.p_email);
                    row.attr('data-parent-status', updatedParent.p_status);
                    row.attr('data-parent-gender', updatedParent.p_gender);
                    row.attr('data-parent-bdate', updatedParent.p_bdate);
                    row.attr('data-parent-cnum', updatedParent.p_cnum);
                    row.attr('data-parent-address', updatedParent.p_address);
                    row.attr('data-parent-avatar', updatedParent.avatar);

                    row.find('td:nth-child(3)').html(`
                        <div class="d-flex align-items-center gap-2">
                            ${updatedParent.avatar
                                ? `<img src="${updatedParent.avatar}" alt="Avatar" class="rounded-circle" style="width:35px; height:35px; object-fit:cover;">`
                                : `<div class="profile-avatar rounded-circle d-flex align-items-center justify-content-center" style="width:35px; height:35px; font-size:0.9rem;">${updatedParent.initials}</div>`}
                            ${updatedParent.full_name}
                        </div>
                    `);
                    row.find('td:nth-child(4)').text(
                        updatedParent.child_name ? `${updatedParent.child_name} - ${updatedParent.section_code}` : 'No child linked'
                    );
                    row.find('td:nth-child(5) span')
                        .removeClass()
                        .addClass(`badge bg-${updatedParent.p_status === 'active' ? 'success' : 'danger'}`)
                        .text(updatedParent.p_status);
                }

                // ✅ Update details card if currently selected
                const selectedParentId = $('#parentDetailsCard').data('selected-parent-id');
                if (selectedParentId == updatedParent.p_id) {
                    updateParentDetailsCard(updatedParent);
                }
            } else {
                showAlert('error', 'Failed to update parent: ' + (response.message || 'Unknown error'));
            }
        },
        error: function (xhr) {
            showAlert('error', 'Error updating parent: ' + xhr.responseText);
        }
    });
});

});
