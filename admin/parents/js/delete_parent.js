$(document).ready(function () {

    // ==================== Row Click: set active-row ====================
    $("#parentsTable tbody").on("click", "tr", function () {
        $("#parentsTable tbody tr").removeClass("active-row");
        $(this).addClass("active-row");
    });

    // ==================== Delete Parent ====================
    $(document).on("click", ".btn-delete-parent", function (e) {
        e.preventDefault();

        // Get currently active row
        const $activeRow = $("#parentsTable tbody tr.active-row");
        if (!$activeRow.length) {
            Swal.fire({
                icon: "warning",
                title: "No Parent Selected",
                text: "Please select a parent first.",
            });
            return;
        }

        const parentId = $activeRow.data("parent-id");
        if (!parentId) {
            Swal.fire({
                icon: "error",
                title: "Invalid ID",
                text: "Invalid parent ID.",
            });
            return;
        }

        // SweetAlert2 confirm
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
                    url: "/admin/parents/processes/delete_parent.php",
                    type: "POST",
                    data: { p_id: parentId },
                    dataType: "json"
                }).done(function (res) {
                    if (res.status === "success") {
                        showAlert("success", res.message);

                        // Remove the row from table
                        $activeRow.fadeOut(400, function () {
                            $(this).remove();
                        });

                        // Reset details panel
                        $('#parentDetailsBody').html(`
                            <div class="card-body-empty" id="noParentSelected">
                                <i class="bi bi-person-lines-fill"></i>
                                No parent selected.
                            </div>
                        `);
                    } else {
                        showAlert("error", res.message);
                    }
                }).fail(function (xhr) {
                    showAlert("error", "Error deleting parent: " + xhr.responseText);
                });
            }
        });
    });

    // ==================== SweetAlert2 toast helper ====================
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
