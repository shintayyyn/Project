<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);      // don't show errors to browser
ini_set('log_errors', 1);          // log errors to server
error_reporting(E_ALL);

try {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


    // Only admin access
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    require_once __DIR__ . '/../../../includes/db.php';

    // DataTables parameters
    $draw   = intval($_POST['draw'] ?? 1);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';

    $type   = $_POST['type'] ?? '';
    $status = $_POST['status'] ?? 'all';

    // Build WHERE clauses
    $whereClauses = [];
    $params = [];
    $types = '';

    if (!empty($type)) {
        $whereClauses[] = "table_name = ?";
        $params[] = $type;
        $types .= 's';
    }

    if ($status === 'deleted') {
        $whereClauses[] = "restored_at IS NULL";
    } elseif ($status === 'restored') {
        $whereClauses[] = "restored_at IS NOT NULL";
    }

    if (!empty($searchValue)) {
        $whereClauses[] = "(table_name LIKE ? OR data LIKE ? OR record_id LIKE ?)";
        $searchTerm = "%{$searchValue}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    $whereSQL = count($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Total records
    $totalRecords = $conn->query("SELECT COUNT(*) as cnt FROM archives")->fetch_assoc()['cnt'];

    // Total filtered records
    $sqlFiltered = "SELECT COUNT(*) as cnt FROM archives $whereSQL";
    $stmtFiltered = $conn->prepare($sqlFiltered);
    if ($stmtFiltered === false) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $filteredCount = $stmtFiltered->get_result()->fetch_assoc()['cnt'];
    $stmtFiltered->close();

    // Fetch data with pagination
    $sqlData = "SELECT * FROM archives $whereSQL ORDER BY deleted_at DESC LIMIT ?, ?";
    $stmtData = $conn->prepare($sqlData);
    if ($stmtData === false) throw new Exception("Prepare failed: " . $conn->error);

    $paramsWithLimit = $params;
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit[] = $start;
    $paramsWithLimit[] = $length;

    $stmtData->bind_param($typesWithLimit, ...$paramsWithLimit);
    $stmtData->execute();
    $result = $stmtData->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "id"         => $row['id'],
            "table_name" => $row['table_name'],
            "record_id"  => $row['record_id'],
            "data"       => $row['data'],
            "deleted_at" => $row['deleted_at'],
            "deleted_by" => $row['deleted_by']
        ];
    }
    $stmtData->close();

    // Return JSON for DataTables
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $totalRecords,
        "recordsFiltered" => $filteredCount,
        "data"            => $data
    ]);
    exit;

} catch (Exception $e) {
    error_log("Archives Fetch Error: " . $e->getMessage());
    echo json_encode([
        "draw" => intval($_POST['draw'] ?? 0),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => $e->getMessage()
    ]);
    exit;
}
