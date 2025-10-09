<?php
// dashboard.php already has session_start()
require_once(__DIR__ . '/../../includes/db.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}
?>

<style>
table{
    overflow: hidden;
}
/* Make all action buttons same size and aligned */
.action-btn {
    width: 38px;        /* fixed width */
    height: 38px;       /* fixed height */
    padding: 0;         /* remove extra padding */
    display: inline-flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;    /* adjust icon size if needed */
    border-radius: 4px; /* optional: keep rounded corners */
}

#roomsTable td.text-center {
    white-space: nowrap; /* prevent buttons from wrapping */
}

</style>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Room Management</h2>
            <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active">Rooms</li>
      </ol>
    </nav>
        </div>
        <button type="button" class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#addRoomModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Room
        </button>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Rooms List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="roomsTable">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM rooms ORDER BY room_number";
                        $result = mysqli_query($conn, $query);
                        while ($room = mysqli_fetch_assoc($result)) {
                            ?>
                            <tr id="room-<?php echo $room['room_id']; ?>">
                                <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($room['capacity']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary action-btn edit-room" data-id="<?php echo $room['room_id']; ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger action-btn delete-room" data-id="<?php echo $room['room_id']; ?>">
                                        <i class="fa-solid fa-trash-can"></i>
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

<script>
$(document).ready(function () {
  // Initialize DataTable
var roomsTable = $('#roomsTable').DataTable({
        scrollY: '50vh',
        scrollX: false,        // No horizontal scroll
        scrollCollapse: true,  // Table shrinks if fewer rows
        responsive: true,      // Optional: makes table responsive
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: -1 } // Last column unsortable
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



    function showAlert(message, type = 'success') {
        const validTypes = ['success', 'error', 'warning', 'info', 'question'];
        if (!validTypes.includes(type)) type = 'info';

        Swal.fire({
            icon: type,
            title: type === 'success' ? 'Success!' :
                   type === 'error' ? 'Error!' :
                   type === 'warning' ? 'Warning!' : 'Notice',
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    // Add Room
    $('#addRoomForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: './rooms/processes/add_room.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response){
                if(response.status === 'success'){
                    $('#addRoomModal').modal('hide');
                    showAlert(response.message, 'success');
                    // Add new row dynamically
                    var newRow = roomsTable.row.add([
                        response.data.room_number,
                        response.data.capacity,
                        `<button class="btn btn-sm btn-primary edit-room" data-id="${response.data.room_id}">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-room" data-id="${response.data.room_id}">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>`
                    ]).draw(false).node();
                    $(newRow).attr('id', 'room-' + response.data.room_id);
                    $('#addRoomForm')[0].reset();
                } else {
                    showAlert(response.message || 'An unknown error occurred', 'error');
                }
            },
            error: function(xhr){
                let msg = 'An error occurred. Please try again.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if(res.message) msg = res.message;
                } catch(e){
                    console.error('Could not parse JSON response', e, xhr.responseText);
                }
                showAlert(msg, 'error');
            }
        });
    });

    // Edit Room (delegate click)
    $('#roomsTable').on('click', '.edit-room', function(){
        var roomId = $(this).data('id');
        $.getJSON('./rooms/processes/get_room.php', {id: roomId}, function(response){
            if(response.status === 'success'){
                $('#edit_room_id').val(response.data.room_id);
                $('#edit_room_number').val(response.data.room_number);
                $('#edit_capacity').val(response.data.capacity);
                $('#editRoomModal').modal('show');
            } else {
                showAlert(response.message || 'Failed to fetch room details', 'error');
            }
        }).fail(function(xhr){
            let msg = 'Failed to fetch room details';
            try {
                const res = JSON.parse(xhr.responseText);
                if(res.message) msg = res.message;
            } catch(e){ console.error(e); }
            showAlert(msg, 'error');
        });
    });

    // Update Room
    $('#editRoomForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: './rooms/processes/edit_room.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response){
                if(response.status === 'success'){
                    $('#editRoomModal').modal('hide');
                    showAlert(response.message, 'success');
                    var id = $('#edit_room_id').val();
                    var row = roomsTable.row('#room-' + id);
                    row.data([
                        $('#edit_room_number').val(),
                        $('#edit_capacity').val(),
                        `<button class="btn btn-sm btn-primary edit-room" data-id="${id}">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-room" data-id="${id}">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>`
                    ]).draw(false);
                } else {
                    showAlert(response.message || 'An unknown error occurred', 'error');
                }
            },
            error: function(xhr){
                let msg = 'An error occurred. Please try again.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if(res.message) msg = res.message;
                } catch(e){ console.error('Could not parse JSON', e); }
                showAlert(msg, 'error');
            }
        });
    });

   // Delete Room with SweetAlert confirmation
$('#roomsTable').on('click', '.delete-room', function(){
    var roomId = $(this).data('id');

    Swal.fire({
        title: 'Are you sure?',
        text: "This will permanently delete the room!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: './rooms/processes/delete_room.php',
                type: 'POST',
                data: {id: roomId},
                dataType: 'json',
                success: function(response){
                    if(response.status === 'success'){
                        showAlert(response.message, 'success');
                        roomsTable.row('#room-' + roomId).remove().draw(false);
                    } else {
                        showAlert(response.message || 'Failed to delete room', 'error');
                    }
                },
                error: function(xhr){
                    let msg = 'An error occurred. Please try again.';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if(res.message) msg = res.message;
                    } catch(e){ console.error(e); }
                    showAlert(msg, 'error');
                }
            });
        }
    });
});
});
</script>

