<?php
require_once __DIR__ . '/../../includes/db.php';

if(!isset($_POST['t_id'])){
    echo json_encode(['error'=>'Teacher ID missing']);
    exit;
}

$t_id = intval($_POST['t_id']);

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM teachers WHERE t_id=?");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher){
    echo json_encode(['error'=>'Teacher not found']);
    exit;
}

// Fetch attendance records
$stmt = $conn->prepare("SELECT * FROM teacher_attendance WHERE t_id=? ORDER BY attendance_date ASC");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statuses = ['Present','Late','Absent','Excuse'];
$semester = array_fill_keys($statuses,0);
$months = [];

foreach($records as $r){
    $status = trim($r['status']);
    if($status==='') $status='Absent';
    if(!in_array($status,$statuses)) $status='Excuse';

    $month = $r['attendance_date'] ? date('F Y',strtotime($r['attendance_date'])) : 'Unknown';
    if(!isset($months[$month])) $months[$month] = array_fill_keys($statuses,0);

    $months[$month][$status] += 1;
    $semester[$status] += 1;
}

if(empty($months)) $months['No Data'] = array_fill_keys($statuses,0);

$fullName = $teacher['t_lname'].', '.$teacher['t_fname'].
            ($teacher['t_mname'] ? ' '.substr($teacher['t_mname'],0,1).'.' : '').
            ($teacher['t_suffix'] ? ' '.$teacher['t_suffix'] : '');

echo json_encode([
    'semester'=>$semester,
    'months'=>array_keys($months),
    'monthly'=>[
        'Present'=>array_map(fn($m)=>$m['Present']??0,$months),
        'Late'=>array_map(fn($m)=>$m['Late']??0,$months),
        'Absent'=>array_map(fn($m)=>$m['Absent']??0,$months),
        'Excuse'=>array_map(fn($m)=>$m['Excuse']??0,$months)
    ],
    'fullName'=>$fullName
]);
