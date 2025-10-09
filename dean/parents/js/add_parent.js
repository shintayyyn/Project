$(document).ready(function () {
    // Auto-generate password when any of the key fields change
    $('#add_p_fname, #add_p_lname, #add_p_bdate').on('input change', function () {
        const fname = $('#add_p_fname').val()?.trim();
        const lname = $('#add_p_lname').val()?.trim();
        const bdate = $('#add_p_bdate').val();

        if (fname && lname && bdate) {
            const birthObj = new Date(bdate);
            const mm = String(birthObj.getMonth() + 1).padStart(2, '0');
            const dd = String(birthObj.getDate()).padStart(2, '0');
            const yyyy = birthObj.getFullYear();
            const formattedDate = mm + dd + yyyy;

            const password = fname.charAt(0).toUpperCase() + lname + formattedDate;
            $('#add_p_password_plain').val(password);
        } else {
            $('#add_p_password_plain').val('');
        }
    });

    // Submit form
    $('#addParentForm').on('submit', function (e) {
        e.preventDefault();

        var form = $(this);

        if (!form[0].checkValidity()) {
            e.stopPropagation();
            form.addClass('was-validated');
            return;
        }

        // No need to regenerate password here, already done above

        $.ajax({
            url: '/Project/dean/parents/processes/add_parent.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#addParentModal').modal('hide');
                    location.reload();
                } else {
                    alert('Failed to add parent: ' + (response.error || 'Unknown error'));
                }
            },
            error: function (xhr) {
                alert('Error adding parent: ' + xhr.responseText);
            }
        });
    });
});
