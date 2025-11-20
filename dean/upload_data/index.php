<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_type'] = 'dean';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../includes/db.php';

$page_title = "Upload Data";

// Fetch upload history
$upload_history = [];
$stmt = $conn->query("SELECT * FROM upload_history WHERE uploaded_by = 'dean' ORDER BY uploaded_at DESC");
while ($row = $stmt->fetch_assoc()) {
    $upload_history[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>dean - Upload Data</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<style>
    body{
        overflow-x: hidden;
    }
     .card-body {
    overflow: hidden; 
    padding: 20px;
}
</style>
<body>
<div class="container-fluid ">
    <!-- Optional: Global notification containers -->
    <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>

    <!-- Header with breadcrumb and button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-2 fw-bold">Upload Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Upload History</li>
                </ol>
            </nav>
        </div>
         <div class="row g-2 justify-content-center">
    <div class="col-auto">
        <button class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-upload me-2"></i>Upload CSV/Excel
        </button>
    </div>
    <div class="col-auto">
        <a href="/dean/upload_data/download_temp.php" class="btn btn-primary" style="min-width: 180px;">
            <i class="bi bi-file-earmark-arrow-down me-2"></i>Get Template
        </a>
    </div>
</div>
        
    </div>

    <!-- Upload History Card -->
<div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Upload History</h5>
        </div>
          <div class="card-body p-2">
             <div class="table-responsive p-3" >
                 <table class="table table-hover align-middle p-2 display nowrap" id="uploadHistoryTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Filename</th>
                            <th>Records</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upload_history as $upload): ?>
                            <tr>
                                <td><?= $upload['id'] ?></td>
                                <td><?= htmlspecialchars($upload['filename']) ?></td>
                                <td><?= $upload['total_records'] ?></td>
                                <td><?= htmlspecialchars($upload['uploaded_by']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($upload['uploaded_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-secondary view-btn" data-id="<?= $upload['id'] ?>">
                                        <i class="bi bi-eye me-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
 </div>

  <!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-sm">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="uploadModalLabel">Upload CSV/Excel File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="instructions text-muted small mb-4">
          Upload a CSV/Excel file using the provided template:<br />
            <span class="text-danger fw-semibold">Note:</span>
            The <code>is_solo, is_regular, and year_level</code> columns are numeric. Passwords will be generated automatically. <br>
            <strong>Instructions:</strong><br>
            - <code>is_solo</code>: 2 = Yes, 1 = No<br>
            - <code>is_regular</code>: 2 = Yes, 1 = No<br>
            - <code>year_level</code>: 1 = 1st Year, 2 = 2nd Year, 3 = 3rd Year, 4 = 4th Year, etc.
        
        <form id="uploadForm" method="POST" enctype="multipart/form-data" action="/dean/upload_data/process_upload.php">
          <div class="mb-3">
            <label for="csvFile" class="form-label fw-semibold">Select CSV or Excel File</label>
            <input class="form-control" type="file" id="csvFile" name="csvFile"
              accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
              required />
          </div>
          <button type="submit" class="btn btn-primary px-4">Upload</button>
        </form>

        <div id="message" class="mt-4"></div>
      </div>
    </div>
  </div>
</div>
<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewModalLabel">Uploaded File Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="fileContent" class="table-responsive"></div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>


<script>
$(document).ready(function () {
    $('#uploadHistoryTable').DataTable({
        scrollY: '50vh',           
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        ordering: true,
        columnDefs: [
            { orderable: false, targets: -1 }
        ],
        dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
        language: {
            lengthMenu: "Show _MENU_ entries"
        }
    });
    
let cards = [];
let currentPage = 0;

function renderFormalCards(csv) {
    const lines = csv.trim().split('\n');
    if (lines.length < 2) return '<div>No data found</div>';

    const rawHeaders = lines[0].split(',');
    const headers = rawHeaders.map(h => h.toLowerCase().replace(/\s+/g, '_'));

    const readableMapping = {
        'is_solo': { '1': 'No', '2': 'Yes' },
        'is_regular': { '1': 'Yes', '2': 'No' },
        'student_gender': { 'M': 'Male', 'F': 'Female', 'N/A': 'N/A' },
        'same_address_flag': { '1': 'Yes', '2': 'No' }
    };

    cards = lines.slice(1).map(line => {
        const row = line.split(',');
        const rowData = {};
        row.forEach((cell, idx) => {
            const colName = headers[idx];
            rowData[colName] = readableMapping[colName] && readableMapping[colName][cell] !== undefined
                ? readableMapping[colName][cell]
                : cell;
        });
        return rowData;
    });

    currentPage = 0;
    return renderCard(currentPage);
}

function renderCard(page) {
    const student = cards[page];
    if (!student) return '<div>No data found</div>';

    const studentInitials = (student.student_first_name[0] || '') + (student.student_last_name[0] || '');
    const studentFullName = `${student.student_first_name} ${student.student_middle_name ? student.student_middle_name[0]+'.' : ''} ${student.student_last_name} ${student.student_suffix || ''}`.trim();

    const parentInitials = (student.p_fname ? student.p_fname[0] : '') + (student.p_lname ? student.p_lname[0] : '');
    const parentFullName = `${student.p_fname} ${student.p_mname ? student.p_mname[0]+'.' : ''} ${student.p_lname} ${student.p_suffix || ''}`.trim();

    // Only show parent info if not Solo
    const showParent = student.is_solo !== 'Yes';

    const cardHTML = `
    <div class="card mb-3">
        <div class="card-body">
                <h6 class="mt-3">Student Information</h6>
            <div class="d-flex align-items-center mb-3">
                <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;border-radius:50%;font-weight:bold;">
                    ${studentInitials.toUpperCase()}
                </div>
                <strong>${studentFullName}</strong>
            </div>
            <div class="row mb-3">
                <div class="col-md-6"><strong>Gender:</strong> ${student.student_gender}</div>
                <div class="col-md-6"><strong>Birthdate:</strong> ${student.student_birthdate}</div>
                <div class="col-md-6"><strong>Contact Number:</strong> ${student.student_contact_number}</div>
                <div class="col-md-6"><strong>Email:</strong> ${student.student_email}</div>
                <div class="col-md-6"><strong>Address:</strong> ${student.student_address}</div>
                <div class="col-md-6"><strong>Degree:</strong> ${student.student_degree}</div>
                <div class="col-md-6"><strong>Year Level:</strong> ${student.year_level}</div>
                <div class="col-md-6"><strong>Regular:</strong> ${student.is_regular}</div>
                <div class="col-md-6"><strong>Solo:</strong> ${student.is_solo}</div>
            </div>
            ${showParent ? `
            <h6 class="mt-3">Parent Information</h6>
            <div class="d-flex align-items-center mb-2">
                <div class="avatar-circle bg-success text-white d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;border-radius:50%;font-weight:bold;">
                    ${parentInitials.toUpperCase()}
                </div>
                <strong>${parentFullName}</strong>
            </div>
            <div class="row mb-2">
                <div class="col-md-6"><strong>Gender:</strong> ${student.p_gender}</div>
                <div class="col-md-6"><strong>Birthdate:</strong> ${student.p_bdate}</div>
                <div class="col-md-6"><strong>Email:</strong> ${student.p_email}</div>
                <div class="col-md-6"><strong>Contact Number:</strong> ${student.p_cnum}</div>
                <div class="col-md-6"><strong>Same Address as Student:</strong> ${student.same_address_flag}</div>
                <div class="col-md-12"><strong>Address:</strong> ${student.parent_address}</div>
            </div>` : ''}
            <div class="d-flex justify-content-between mt-3">
                <button class="btn btn-sm btn-outline-primary" id="prevRecord" ${page===0?'disabled':''}>Prev</button>
                <span>Record ${page+1} of ${cards.length}</span>
                <button class="btn btn-sm btn-outline-primary" id="nextRecord" ${page===cards.length-1?'disabled':''}>Next</button>
            </div>
        </div>
    </div>`;

    $('#fileContent').html(cardHTML);
}

// Prev/Next click handlers
$('#fileContent').on('click', '#prevRecord', function() {
    if (currentPage > 0) {
        currentPage--;
        renderCard(currentPage);
    }
});

$('#fileContent').on('click', '#nextRecord', function() {
    if (currentPage < cards.length - 1) {
        currentPage++;
        renderCard(currentPage);
    }
});

// Delegate click for all pages
$('#uploadHistoryTable').on('click', '.view-btn', function () {
    const id = $(this).data('id');

    $.ajax({
        url: '/dean/upload_data/get_raw_data.php',
        method: 'POST',
        data: { id: id },
        success: function (response) {
            renderFormalCards(response);
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        },
        error: function () {
            $('#fileContent').html('<div class="alert alert-danger">Failed to load data.</div>');
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
    });
});


$('#uploadForm').on('submit', function (e) {
    e.preventDefault();

    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    if (!file) {
        alert('Please select a file.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
        const data = new Uint8Array(event.target.result);
        const workbook = XLSX.read(data, { type: 'array' });

        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];

        // Convert to 2D array including headers
        const jsonArray = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

        if (jsonArray.length < 2) {
            $('#message').html('<div class="alert alert-danger">Excel file is empty or improperly formatted.</div>');
            return;
        }

        const filename = file.name;

        $.ajax({
            url: '/dean/upload_data/process_upload.php',
            type: 'POST',
            data: {
                excelData: JSON.stringify(jsonArray),
                filename: filename
            },
            success: function (response) {
                let res = response;
                if (typeof response === 'string') {
                    try {
                        res = JSON.parse(response);
                    } catch (e) {}
                }

                $('#message').html(
                    `<div class="alert alert-${res.success ? 'success' : 'danger'}">${res.message}</div>`
                );

                if (res.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            },
            error: function () {
                $('#message').html('<div class="alert alert-danger">An error occurred during upload.</div>');
            }
        });
    };

    reader.readAsArrayBuffer(file);
});



    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>
</body>
</html>
