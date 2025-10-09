<?php
session_start();
require_once 'includes/db.php';

// ===== Helper Function for JSON Response =====
function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

// ===== Global Exception Handler =====
set_exception_handler(function($e) {
    jsonResponse(false, "Server error: " . $e->getMessage());
});

// ===== Validate Access =====
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'dean'])) {
    header("Location: /login.php");
    exit();
}

// ===== Handle AJAX Requests =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        // 1. Create Academic Year
        if ($action === 'create_academic_year') {
            $year_start = intval($_POST['year_start']);
            $year_end   = intval($_POST['year_end']);

            if ($year_start <= 0 || $year_end !== $year_start + 1) {
                jsonResponse(false, 'Invalid year range. End year must be Start year + 1.');
            }

            // Check for duplicates
            $check = $conn->prepare("SELECT COUNT(*) AS count FROM academic_years WHERE year_start = ? AND year_end = ?");
            $check->bind_param("ii", $year_start, $year_end);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc()['count'];

            if ($exists > 0) {
                jsonResponse(false, 'This academic year already exists.');
            }

            $stmt = $conn->prepare("INSERT INTO academic_years (year_start, year_end, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $year_start, $year_end);

            if ($stmt->execute()) {
                jsonResponse(true, 'Academic year created successfully.', [
                    'ay_id'      => $stmt->insert_id,
                    'year_start' => $year_start,
                    'year_end'   => $year_end
                ]);
            } else {
                jsonResponse(false, 'Failed to create academic year.');
            }
        }

        // 2. Create Academic Term
        if ($action === 'create_term') {
            $ay_id    = intval($_POST['ay_id']);
            $semester = trim($_POST['semester']);

            if ($ay_id <= 0 || $semester === '') {
                jsonResponse(false, 'Invalid academic year or semester provided.');
            }

            // Inactivate current active term
            $conn->query("UPDATE academic_terms SET is_active = 2 WHERE is_active = 1");

            // Insert new active term
            $stmt = $conn->prepare("INSERT INTO academic_terms (ay_id, semester, is_active, created_at) VALUES (?, ?, 1, NOW())");
            $stmt->bind_param("is", $ay_id, $semester);

            if ($stmt->execute()) {
                jsonResponse(true, 'Academic term created successfully.', [
                    'term_id'  => $stmt->insert_id,
                    'semester' => $semester
                ]);
            } else {
                jsonResponse(false, 'Failed to create academic term.');
            }
        }

        // 3. Select Existing Term
        if ($action === 'select_term') {
            $term_id = intval($_POST['term_id']);

            if ($term_id <= 0) {
                jsonResponse(false, 'Invalid term selected.');
            }

            // Inactivate others
            $conn->query("UPDATE academic_terms SET is_active = 2 WHERE is_active = 1");

            // Activate selected term
            $stmt = $conn->prepare("UPDATE academic_terms SET is_active = 1, updated_at = NOW() WHERE term_id = ?");
            $stmt->bind_param("i", $term_id);

            if ($stmt->execute()) {
                jsonResponse(true, 'Term selected successfully.');
            } else {
                jsonResponse(false, 'Failed to activate selected term.');
            }
        }

        jsonResponse(false, 'Invalid action specified.');
    } catch (Exception $e) {
        jsonResponse(false, "Unexpected error: " . $e->getMessage());
    }
}

// ===== Normal Page Load =====
$active_term = $conn->query("
    SELECT t.term_id, t.semester, a.year_start, a.year_end
    FROM academic_terms t
    JOIN academic_years a ON t.ay_id = a.ay_id
    WHERE t.is_active = 1
    LIMIT 1
")->fetch_assoc();

$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY year_start DESC");

$terms = $conn->query("
    SELECT t.term_id, t.semester, a.year_start, a.year_end
    FROM academic_terms t
    JOIN academic_years a ON t.ay_id = a.ay_id
    ORDER BY a.year_start DESC, t.semester ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Academic Terms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
    :root {
      --primary: #033A70;
      --accent: #FFCB05;
      --background-light: #EDF8FD;
      --border-light: #D0EEFC;
      --hover-blue: #3E7DCA;
    }

    body {
      background: var(--background-light);
      font-family: Arial, sans-serif;
      color: var(--primary);
    }

    .header-bar {
      background: var(--primary);
      color: white;
      padding: 1rem;
      text-align: center;
      font-weight: bold;
      margin-bottom: 2rem;
    }

    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .card-header {
      background: var(--primary);
      color: white;
      font-weight: bold;
    }

    .form-control, .form-select {
      border-radius: 0.5rem;
      border: 1px solid var(--border-light);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 5px rgba(3,58,112,0.2);
    }

    .btn-custom {
      background: linear-gradient(135deg, var(--primary), var(--hover-blue));
      color: white;
      border: none;
      border-radius: 0.5rem;
      padding: 10px 20px;
    }

    .btn-custom:hover {
      background: var(--hover-blue);
    }

    .alert {
      border-radius: 0.5rem;
    }
  </style>
</head>
<body>
  <div class="header-bar">
    Manage Academic Terms
  </div>

  <div class="container">
    <?php if(!$active_term): ?>
      <div class="alert alert-warning">
        <i class="material-icons align-middle">warning</i>
        No active term found. Please create a term first.
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        <i class="material-icons align-middle">calendar_today</i>
        Active Term:
        <strong><?= $active_term['year_start'] ?> - <?= $active_term['year_end'] ?> | <?= $active_term['semester'] ?></strong>
      </div>
    <?php endif; ?>

    <!-- Step 1: Create Academic Year -->
    <div class="card mb-4">
      <div class="card-header">Step 1: Create a New Academic Year (Optional)</div>
      <div class="card-body">
        <?php $currentYear = date("Y"); ?>
        <form id="academic-year-form" class="row g-2">
          <div class="col-md-4">
            <input type="number" name="year_start" id="year_start" class="form-control" value="<?= $currentYear ?>" min="<?= $currentYear ?>" required>
          </div>
          <div class="col-md-4">
            <input type="number" name="year_end" id="year_end" class="form-control" value="<?= $currentYear + 1 ?>" readonly>
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-custom w-100">Create Academic Year</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Step 2: Create Academic Term -->
    <div class="card mb-4">
      <div class="card-header">Step 2: Create a New Term</div>
      <div class="card-body">
        <form id="create-term-form" class="row g-2">
          <div class="col-md-6">
            <select name="ay_id" id="ay_select" class="form-select" required>
              <option value="">-- Select Academic Year --</option>
              <?php while ($row = $academic_years->fetch_assoc()): ?>
                <option value="<?= $row['ay_id'] ?>"><?= $row['year_start'] ?> - <?= $row['year_end'] ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-4">
            <input type="text" name="semester" class="form-control" placeholder="e.g., 1st Semester" required>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-custom w-100">Create Term</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Step 3: Select Existing Term -->
    <?php if($active_term): ?>
      <div class="card mb-4">
        <div class="card-header">Step 3: Select Term to Proceed</div>
        <div class="card-body">
          <form id="select-term-form" class="row g-2">
            <div class="col-md-8">
              <select name="term_id" id="term_select" class="form-select" required>
                <option value="">-- Select Existing Term --</option>
                <?php while ($row = $terms->fetch_assoc()): ?>
                  <option value="<?= $row['term_id'] ?>">
                    <?= $row['year_start'] ?> - <?= $row['year_end'] ?> | <?= $row['semester'] ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-custom w-100">Proceed to Dashboard</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

<script>
  // Central showAlert function
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

  $(document).ready(function() {
    // Auto update end year when start year changes
    $('#year_start').on('input', function() {
      let startYear = parseInt($(this).val());
      $('#year_end').val(startYear + 1);
    });

    // Create Academic Year
    $('#academic-year-form').submit(function(e) {
      e.preventDefault();
      $.post('manage_terms.php', {
        action: 'create_academic_year',
        year_start: $('#year_start').val(),
        year_end: $('#year_end').val()
      }, function(response) {
        if (response.success) {
          $('#ay_select').prepend(`<option value="${response.ay_id}" selected>${response.year_start} - ${response.year_end}</option>`);
          showAlert(response.message, 'success');
        } else {
          showAlert(response.message, 'error');
        }
      }, 'json').fail(() => {
        showAlert('Network or server error occurred.', 'error');
      });
    });

    // Create Term
    $('#create-term-form').submit(function(e) {
      e.preventDefault();
      $.post('manage_terms.php', {
        action: 'create_term',
        ay_id: $('#ay_select').val(),
        semester: $('input[name="semester"]').val()
      }, function(response) {
        if (response.success) {
          $('#term_select').prepend(`<option value="${response.term_id}" selected>${$('#ay_select option:selected').text()} | ${response.semester}</option>`);
          showAlert(response.message, 'success');
        } else {
          showAlert(response.message, 'error');
        }
      }, 'json').fail(() => {
        showAlert('Network or server error occurred.', 'error');
      });
    });

    // Select Term
    $('#select-term-form').submit(function(e) {
      e.preventDefault();
      $.post('manage_terms.php', {
        action: 'select_term',
        term_id: $('#term_select').val()
      }, function(response) {
        if (response.success) {
          showAlert(response.message, 'success');
          setTimeout(() => window.location.href = '/admin/dashboard.php', 1000);
        } else {
          showAlert(response.message, 'error');
        }
      }, 'json').fail(() => {
        showAlert('Network or server error occurred.', 'error');
      });
    });
  });
</script>
</body>
</html>
