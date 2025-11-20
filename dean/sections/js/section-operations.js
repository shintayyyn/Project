$(document).ready(function() {
 


    // Assign Advisor Form Submit Handler
    $('#assignAdvisorForm').on('submit', function(e) {
        e.preventDefault();
        const sectionId = $('#advisorSectionId').val();
        const advisorId = $('#teacherSelect').val();

        if (!sectionId || !advisorId) {
            showAlert('danger', 'Please select an advisor');
            return;
        }

        const data = {
            section_id: sectionId,
            advisor_id: advisorId
        };

        $.ajax({
            url: '  /dean/sections/processes/assign_advisor.php',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success && data.advisor_name) {
                    // Update the advisor display in the section card
                    const $advisorContainer = $(`.advisor-container[data-section-id="${sectionId}"]`);
                    const advisorHtml = `
                        <div class="advisor-name">
                            <span class="text-primary">${data.advisor_name}</span>
                            <div class="btn-group btn-group-sm ms-2">
                                <button type="button" 
                                        class="btn btn-primary px-2 edit-advisor-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#assignAdvisorModal"
                                        data-section-id="${sectionId}"
                                        data-advisor-id="${data.t_id}"
                                        data-mode="edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-danger delete-advisor-btn"
                                        data-section-id="${sectionId}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    $advisorContainer.html(advisorHtml);
                    
                    $('#assignAdvisorModal').modal('hide');
                    showAlert('success', 'Advisor assigned successfully');
                } else {
                    showAlert('danger', data.message || 'Failed to assign advisor');
                }
            },
            error: function() {
                showAlert('danger', 'Server error occurred while assigning advisor');
            }
        });
    });

    // Unassign advisor handler
    $(document).on('click', '.delete-advisor-btn', function() {
        const sectionId = $(this).data('section-id');
        const $advisorContainer = $(this).closest('.advisor-container');
      Swal.fire({
    title: "Are you sure?",
    text: "Do you want to remove this advisor from the section?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, remove",
    cancelButtonText: "Cancel",
    reverseButtons: true
}).then((result) => {
    if (result.isConfirmed) {

        $.ajax({
            url: '/dean/sections/processes/unassign_advisor.php',
            type: 'POST',
            data: JSON.stringify({ section_id: sectionId }),
            contentType: 'application/json',
            success: function(response) {
                const data = typeof response === 'string' ? JSON.parse(response) : response;

                if (data.success) {

                    $advisorContainer.html(`
                        <div class="advisor-name">
                            <span class="text-muted me-2">No advisor assigned</span>
                            <button type="button" 
                                    class="btn btn-sm btn-success assign-advisor-btn"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#assignAdvisorModal"
                                    data-section-id="${sectionId}"
                                    data-mode="add">
                                <i class="bi bi-person-plus"></i>
                            </button>
                        </div>
                    `);

                    Swal.fire({
                        icon: 'success',
                        title: 'Advisor unassigned successfully',
                        timer: 1500,
                        showConfirmButton: false
                    });

                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to unassign advisor'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Server error occurred while unassigning advisor'
                });
            }
        });

    }
});

    });

});