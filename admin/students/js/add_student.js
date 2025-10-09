$(document).ready(function() {
    const $addForm = $('#addStudentForm');
    if (!$addForm.length) return;

    // ================= Unassigned Counter =================
    function updateUnassignedCounter() {
        $.ajax({
            url: '/admin/students/processes/get_unassigned_count.php',
            dataType: 'json'
        }).done(function(res) {
            if (res.success) {
                $('#unassignedCounter').text(res.count);
            }
        }).fail(function() {
            $('#unassignedCounter').text('0');
        });
    }

    // Initial load
    updateUnassignedCounter();

    // ================= Init Parent Status =================
    const existingParent = $('#parentFullnameInput').val();
    if (existingParent && existingParent !== 'Solo (No Parent)') {
        // Has a real parent name
        $('#parentStatusSelect').val('with_parent');
        $('#parentFullnameInput').prop('disabled', false);
        $('#remarksInput').val('Living with Parents/Guardians');
        $('#parent_status').val('with_parent');
        $('#is_solo').val(1); // living with parent
    } else {
        // Solo case
        $('#parentStatusSelect').val('solo');
        $('#parentFullnameInput').val('Solo (No Parent)').prop('disabled', true);
        $('#remarksInput').val('Solo (No Parent)');
        $('#parent_status').val('solo');
        $('#is_solo').val(2); // solo
    }

    // ================= Solo Parent Selection =================
    $('#parentStatusSelect').on('change', function() {
        const selected = $(this).val();
        if (selected === 'solo') {
            $('#parentFullnameInput').val('Solo (No Parent)').prop('disabled', true);
            $('#remarksInput').val('Solo (No Parent)');
            $('#parent_status').val('solo');
            $('#is_solo').val(2); // solo
        } else {
            $('#parentFullnameInput').prop('disabled', false).val('');
            $('#remarksInput').val('Living with Parents/Guardians');
            $('#parent_status').val('with_parent');
            $('#is_solo').val(1); // living with parent
        }
    });

    // ================= Regular/Irregular Toggle =================
    $('#isRegularInput').on('change', function() {
        $('#is_regular').val($(this).prop('checked') ? 2 : 1);
    });

    // ================= Form submit =================
  $addForm.on('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    // ✅ Force is_solo value at submit time
    if (soloCheckbox && soloCheckbox.checked) {
        formData.set('is_solo', 2);
        formData.set('remarks', 'Solo (No Parent)');
    } else {
        formData.set('is_solo', 1);
        formData.set('remarks', 'Living with Parents/Guardians');
    }

        // ✅ Regular / Irregular
        formData.set('is_regular', $('#is_regular').val());
        formData.set('is_solo', $('#is_solo').val());

        // ========== AJAX ==========
        $.ajax({
            url: '/admin/students/processes/add_student.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res) {
            if (res.success) {
                // ✅ Ensure modal closes fully
                $('#addStudentModal').modal('hide');
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');

                // Optional: show success alert
                showAlert('success', res.message);

                // ✅ Update parent detail panel immediately
                if (res.data && res.data.parent_fullname) {
                    $('#detailParent').html(
                        `<span class="badge bg-info">${res.data.parent_fullname}</span>`
                    );
                }

                // ✅ Full page reload (optional)
                setTimeout(() => {
                    window.location.reload();
                }, 500);

                updateUnassignedCounter();
            } else {
                showAlert('error', res.message);
            }
        }).fail(function(xhr) {
            console.error(xhr.responseText);
            showAlert('error', 'Server error');
        }).always(function() {
            $submitBtn.prop('disabled', false);
        });
    });

    // ================= Helpers =================
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

    function calculateAge(bdate) {
        const dob = new Date(bdate);
        if (isNaN(dob)) return '';
        const diff = Date.now() - dob.getTime();
        const ageDate = new Date(diff);
        return Math.abs(ageDate.getUTCFullYear() - 1970);
    }

    $('<style>@keyframes highlightNew{from{background-color:rgba(25,135,84,.2);}to{background-color:transparent;}}.highlight-new{animation:highlightNew 3s ease-out;}</style>').appendTo('head');
});
