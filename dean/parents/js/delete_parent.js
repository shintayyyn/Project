$(document).ready(function() {
    $('.btn-delete-parent').on('click', function() {
        if (!confirm('Are you sure you want to delete this parent?')) {
            return;
        }
        var parentId = $(this).data('parent-id');
        $.ajax({
            url: '/Project/dean/parents/processes/delete_parent.php',
            type: 'POST',
            data: { p_id: parentId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload to reflect deletion
                } else {
                    alert('Failed to delete parent: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Error deleting parent: ' + xhr.responseText);
            }
        });
    });
});
