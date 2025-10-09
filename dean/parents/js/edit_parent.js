$(document).ready(function() {
    // Load parent data into edit form when edit button clicked
    $('.btn-edit-parent').on('click', function() {
        var parentId = $(this).data('parent-id');
        $.ajax({
            url: '/Project/dean/parents/processes/edit_parent.php',
            type: 'GET',
            data: { id: parentId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var parent = response.parent;

                    // Populate parent fields
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
                   $('#edit_p_password_plain').val(parent.p_password_plain);

                    // Populate child <select> options dynamically
                    var $childSelect = $('#editParentForm select[name="child_id"]');
                    $childSelect.empty();

                    if (response.children && response.children.length > 0) {
                        $.each(response.children, function(index, child) {
                            var option = $('<option>', {
                                value: child.s_id,
                                text: child.full_name + ' - ' + child.section_code
                            });
                            $childSelect.append(option);
                        });

                        // Optional: select first child or keep selected
                        $childSelect.val(response.children[0].s_id);
                    } else {
                        $childSelect.append($('<option>', {
                            value: '',
                            text: 'No linked children'
                        }));
                    }

                    $('#editParentModal').modal('show');
                } else {
                    alert('Failed to load parent data: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Error loading parent data: ' + xhr.responseText);
            }
        });
    });

    // Submit edit form
    $('#editParentForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);

        if (!form[0].checkValidity()) {
            e.stopPropagation();
            form.addClass('was-validated');
            return;
        }

        $.ajax({
            url: '/Project/dean/parents/processes/update_parent.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editParentModal').modal('hide');
                    location.reload(); // Refresh to reflect changes
                } else {
                    alert('Failed to update parent: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Error updating parent: ' + xhr.responseText);
            }
        });
    });
});
