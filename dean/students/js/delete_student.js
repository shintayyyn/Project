$(document).ready(function () {
    $(document).on("click", ".btn-delete-student", function (e) {
        e.preventDefault();

        // get the currently active row in the table
        const $activeRow = $("#studentsTable tbody tr.active-row");
        if (!$activeRow.length) {
            Swal.fire({
                icon: "warning",
                title: "No Student Selected",
                text: "Please select a student first.",
            });
            return;
        }

        const studentId = $activeRow.data("student-id");
        if (!studentId) {
            Swal.fire({
                icon: "error",
                title: "Invalid ID",
                text: "Invalid student ID.",
            });
            return;
        }

        // ✅ SweetAlert2 confirm dialog
        Swal.fire({
            title: "Are you sure?",
            text: "This action cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "/dean/students/processes/delete_student.php",
                    type: "POST",
                    data: { s_id: studentId },
                    dataType: "json"
                }).done(function (res) {
                    if (res.success) {
                        showAlert("success", res.message);

                        // remove the row from the table
                        $activeRow.fadeOut(400, function () {
                            $(this).remove();

                            // Reset detail panel
                            $('#studentDetailsContent').addClass('d-none');
                            $('#noStudentSelected').show();
                        });

                    } else {
                        showAlert("error", res.message);
                    }
                });
            }
        });
    });

    $activeRow.fadeOut(400, function () {
    $(this).remove();

    // Reset detail panel
    $('#studentDetailsContent').addClass('d-none');
    $('#noStudentSelected').show();

    // ✅ Update unassigned counters
    updateUnassignedCounters();
});

// Function to recalc unassigned counts
function updateUnassignedCounters() {
    // Example: recalc number of rows still in the table that are unassigned
    const unassignedCount = $("#studentsTable tbody tr").filter(function() {
        // Assuming you mark unassigned rows with a class 'unassigned'
        return $(this).hasClass("unassigned");
    }).length;

    // Update the counter element
    $("#unassignedCounter").text(unassignedCount);
}


    // ✅ SweetAlert2 toast
    function showAlert(type, message) {
        Swal.fire({
            icon: type, // 'success' | 'error' | 'warning' | 'info'
            title: type === "success" ? "Success!" : "Error!",
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: "top-end"
        });
    }
});
