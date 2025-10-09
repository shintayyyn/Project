<?php
require_once __DIR__ . '/../../includes/db.php';
 if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// NOTE: keep display_errors off on server-side scripts that return JSON to DataTables.
// ini_set('display_errors', 0);

$base_url = '/admin/archives/process';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
    /* Pills styling */
    .nav-pills .nav-link {
        color: var(--bs-primary);
        font-weight: 500;
        border-radius: 50rem;
    }
    .nav-pills .nav-link.active {
        background-color: var(--bs-primary);
        color: #fff !important;
    }
    .nav-pills .nav-link:hover {
        background-color: rgba(var(--bs-primary-rgb), .1);
        color: var(--bs-primary);
    }
    pre {
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.85rem;
    }
</style>

<div class="container mt-4">
    <h3 class="mb-3">Archives</h3>

    <!-- Pills filter -->
    <ul class="nav nav-pills mb-3" id="archiveTabs">
        <li class="nav-item"><a class="nav-link active" data-type="" href="#">All</a></li>
        <li class="nav-item"><a class="nav-link" data-type="students" href="#">Students</a></li>
        <li class="nav-item"><a class="nav-link" data-type="teachers" href="#">Teachers</a></li>
        <li class="nav-item"><a class="nav-link" data-type="parents" href="#">Parents</a></li>
        <li class="nav-item"><a class="nav-link" data-type="upload_history" href="#">Documents</a></li>
    </ul>

    <!-- Deleted / Restored filter -->
    <!-- <ul class="nav nav-pills mb-3" id="statusTabs">
        <li class="nav-item"><a class="nav-link active" data-status="all" href="#">All</a></li>
        <li class="nav-item"><a class="nav-link" data-status="deleted" href="#">Deleted Only</a></li>
        <li class="nav-item"><a class="nav-link" data-status="restored" href="#">Restored</a></li>
    </ul> -->

    <!-- Bulk actions -->
    <div class="d-flex gap-2 mb-2">
        <input type="checkbox" id="checkAll"> <label for="checkAll" class="me-2">Select All</label>
        <button id="bulkRestore" class="btn btn-success btn-sm">Restore Selected</button>
        <button id="bulkDelete" class="btn btn-danger btn-sm">Delete Selected</button>
    </div>

    <!-- DataTable -->
    <table id="archivesTable" class="table table-bordered table-striped align-middle" style="width:100%">
        <thead class="table-light">
            <tr>
                <th></th>
                <th>ID</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>Data</th>
                <th>Deleted At</th>
                <th>Deleted By</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    let baseUrl = "<?php echo $base_url; ?>";
    let archiveType = '';
    let status = 'all';

    // disable DataTables built-in alert and handle errors ourselves
    $.fn.dataTable.ext.errMode = 'none';

    let table = $('#archivesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: baseUrl + '/fetch_archives.php',
            type: 'POST',
            data: function (d) {
                d.type = archiveType;
                d.status = status;
            },
            dataSrc: function (json) {
                // guard: server should always return valid JSON
                if (!json) {
                    alert('No response from server.');
                    return [];
                }
                if (json.error) {
                    alert('Server error: ' + json.error);
                    return []; // prevent DataTables from breaking
                }
                return json.data || [];
            },
            error: function (xhr, textStatus, errorThrown) {
                // try to parse server JSON error if present
                let resp = xhr.responseText;
                try {
                    let parsed = JSON.parse(resp);
                    alert('Server error: ' + (parsed.error || textStatus));
                } catch (e) {
                    alert('Ajax error: ' + textStatus + ' (HTTP ' + xhr.status + ')');
                    console.log('XHR response:', resp);
                }
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: row => `<input type="checkbox" class="row-check" value="${row.id}">`
            },
            { data: 'id' },
            { data: 'table_name' },
            { data: 'record_id' },
            { data: 'data', render: d => `<pre>${d}</pre>` },
            { data: 'deleted_at' },
            { data: 'deleted_by' },
            {
                data: null,
                render: row => `
                    <button class="btn btn-sm btn-success restore-single" data-id="${row.id}">Restore</button>
                    <button class="btn btn-sm btn-danger delete-single" data-id="${row.id}">Delete</button>
                `
            }
        ],
        order: [[1, 'desc']],
        pageLength: 5,
        lengthChange: false
    });

    // reset Select All on draw
    table.on('draw', function () {
        $('#checkAll').prop('checked', false);
    });

    // Type filter (pills)
    $('#archiveTabs .nav-link').on('click', function (e) {
        e.preventDefault();
        $('#archiveTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        archiveType = $(this).data('type') || '';
        table.ajax.reload();
    });

    // Status filter (pills)
    $('#statusTabs .nav-link').on('click', function (e) {
        e.preventDefault();
        $('#statusTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        status = $(this).data('status') || 'all';
        table.ajax.reload();
    });

    // Select All (current page)
    $('#checkAll').on('change', function() {
        let checked = this.checked;
        $('#archivesTable tbody input.row-check').prop('checked', checked);
    });

    function getSelectedIds() {
        return $('#archivesTable tbody input.row-check:checked').map(function() { return this.value; }).get();
    }

    // Single restore
    $('#archivesTable').on('click', '.restore-single', function () {
        let id = $(this).data('id');
        $.post(baseUrl + '/restore_archive.php', { id }, function (res) {
            if (res && res.error) alert(res.error);
            else alert(res.message || 'Restored');
            table.ajax.reload();
        }, 'json').fail(function(xhr){
            alert('Restore failed. See console.');
            console.log(xhr.responseText);
        });
    });

    // Single delete
    $('#archivesTable').on('click', '.delete-single', function () {
        let id = $(this).data('id');
        if (!confirm("Delete permanently?")) return;
        $.post(baseUrl + '/delete_permanent.php', { id }, function (res) {
            if (res && res.error) alert(res.error);
            else alert(res.message || 'Deleted');
            table.ajax.reload();
        }, 'json').fail(function(xhr){
            alert('Delete failed. See console.');
            console.log(xhr.responseText);
        });
    });

    // Bulk restore
    $('#bulkRestore').on('click', function() {
        let ids = getSelectedIds();
        if (ids.length === 0) return alert("No rows selected.");
        $.post(baseUrl + '/bulk_restore.php', { ids: ids }, function(res) {
            if (res && res.error) alert(res.error);
            else alert(res.message || 'Restored');
            table.ajax.reload();
        }, 'json').fail(function(xhr){
            alert('Bulk restore failed. See console.');
            console.log(xhr.responseText);
        });
    });

    // Bulk delete
    $('#bulkDelete').on('click', function() {
        let ids = getSelectedIds();
        if (ids.length === 0) return alert("No rows selected.");
        if (!confirm("Delete permanently selected rows?")) return;
        $.post(baseUrl + '/bulk_delete.php', { ids: ids }, function(res) {
            if (res && res.error) alert(res.error);
            else alert(res.message || 'Deleted');
            table.ajax.reload();
        }, 'json').fail(function(xhr){
            alert('Bulk delete failed. See console.');
            console.log(xhr.responseText);
        });
    });

    // show DataTables internal errors in console (helps debugging)
    $('#archivesTable').on('error.dt', function (e, settings, techNote, message) {
        console.error('DataTables error:', message);
    });
});
</script>
