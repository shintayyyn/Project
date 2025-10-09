<?php
// Remove session_start since it's already started in dashboard.php
require_once(__DIR__ . '/../../includes/db.php');

// Check if user is logged in and has dean privileges
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    header('Location: ../../login.php');
    exit();
}
?>

<style>
.table-responsive {
    overflow-x: hidden;
}
.table td {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.btn-group {
    display: flex;
    gap: 2px;
}
/* Fix for entries per page dropdown */
.dataTables_length select {
    min-width: 70px !important;
    padding-right: 25px !important;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Room Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Rooms</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                <i class="bi bi-plus-lg me-2"></i>Add New Room
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header ">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Rooms List</h5>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="px-4 py-3">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="roomsTable">
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Capacity</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT * FROM rooms ORDER BY room_number";
                            $result = mysqli_query($conn, $query);
                            while ($room = mysqli_fetch_assoc($result)) {
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars($room['capacity']); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary edit-room" data-id="<?php echo $room['room_id']; ?>">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-room" data-id="<?php echo $room['room_id']; ?>">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRoomForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="room_number" class="form-label">Room Number*</label>
                        <input type="text" class="form-control" id="room_number" name="room_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Capacity*</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" required min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRoomForm">
                <input type="hidden" id="edit_room_id" name="room_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_room_number" class="form-label">Room Number*</label>
                        <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_capacity" class="form-label">Capacity*</label>
                        <input type="number" class="form-control" id="edit_capacity" name="capacity" required min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Initialize DataTable and Event Handlers -->
<script>
$(document).ready(function () {
    $('table').DataTable({
        scrollY: '650px',
        scrollCollapse: true,
        responsive: true,
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: -1 } // Make last column unsortable (e.g., action buttons)
        ],
        dom: '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });


    // Helper function to show alerts
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '1050';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    }

    // Add Room Form Submission
    $('#addRoomForm').on('submit', function(e) {
        e.preventDefault();
        const baseUrl = $('meta[name="base-url"]').attr('content');
        $.ajax({
            url: baseUrl + '/rooms/processes/add_room.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                console.log('Response:', response);
                if (response.status === 'success') {
                    $('#addRoomModal').modal('hide');
                    showAlert('success', 'Room added successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', response.message || 'An error occurred while adding the room.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', xhr.responseText);
                showAlert('danger', 'An error occurred while adding the room. Please try again.');
            }
        });
    });

    // Edit Room Button Click
    $('.edit-room').on('click', function() {
        const baseUrl = $('meta[name="base-url"]').attr('content');
        var roomId = $(this).data('id');
        $.get(baseUrl + '/rooms/processes/get_room.php', {id: roomId}, function(response) {
            if (response.status === 'success') {
                var room = response.data;
                $('#edit_room_id').val(room.room_id);
                $('#edit_room_number').val(room.room_number);
                $('#edit_capacity').val(room.capacity);
                $('#editRoomModal').modal('show');
            } else {
                showAlert('danger', response.message || 'Failed to load room details');
            }
        });
    });

    // Edit Room Form Submission
    $('#editRoomForm').on('submit', function(e) {
        e.preventDefault();
        const baseUrl = $('meta[name="base-url"]').attr('content');
        $.ajax({
            url: baseUrl + '/rooms/processes/edit_room.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#editRoomModal').modal('hide');
                    showAlert('success', 'Room updated successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', response.message || 'An error occurred while updating the room.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', xhr.responseText);
                showAlert('danger', 'An error occurred while updating the room. Please try again.');
            }
        });
    });

    // Delete Room Button Click
    $('.delete-room').on('click', function() {
        if (confirm('Are you sure you want to delete this room?')) {
            const baseUrl = $('meta[name="base-url"]').attr('content');
            var roomId = $(this).data('id');
            $.ajax({
                url: baseUrl + '/rooms/processes/delete_room.php',
                type: 'POST',
                data: {id: roomId},
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('success', 'Room deleted successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('danger', response.message || 'An error occurred while deleting the room.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', xhr.responseText);
                    showAlert('danger', 'An error occurred while deleting the room. Please try again.');
                }
            });
        }
    });
});
</script>