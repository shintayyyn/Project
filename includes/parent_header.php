<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// Parent info
$stmt = $conn->prepare("
    SELECT p_id, CONCAT(p_fname, ' ', IFNULL(p_mname, ''), ' ', p_lname, ' ', IFNULL(p_suffix, '')) AS full_name
    FROM parents
    WHERE p_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent_info = $stmt->get_result()->fetch_assoc();
?>

<style>
/* =========================
   Parent Header Namespaced
   ========================= */
.parent-header-card {
    max-width: calc(100% - 50px); 
    position: static;
    background: var(--quaternary);
    border-radius: 15px;
    padding: 2rem 2rem;
    color: var(--primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    position: relative;
     position: sticky;
    top: 10px; /* distance from top of viewport */
    z-index: 1000; /* ensure it stays above other elements */
}

.parent-header-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.parent-avatar-circle {
    width: 80px;
    height: 80px;
    background: #fff;
    color: #033A70;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: bold;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.parent-header-text h1 {
    font-weight: 600;
    margin: 0;
}

.parent-header-text .role {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 0;
}

.parent-header-avatar {
    position: absolute;
    right: -40px;
    bottom: 0;
    top: -80px;
    width: 280px;
    height: auto;
    object-fit: contain;
    pointer-events: none;
    z-index: 2;
}

/* Responsive for Mobile */
@media (max-width: 576px) {
    .parent-header-card {
        flex-direction: column;
        text-align: left;
        gap: 1.5rem;
        max-width: calc(100% - 30px);
        padding: 1.5rem;
        margin-top: 5px;
    }

    .parent-header-content {
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
    }

    .parent-avatar-circle {
        font-size: 1.2rem;
        width: 50px;
        height: 50px;
        flex-shrink: 0;
    }

    .parent-header-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .parent-header-text h1 {
        font-size: 15px;
        color: var(--primary);
        font-weight: 700;
    }

    .parent-header-text .role {
        font-size: 0.7rem;
        color: var(--minimal);
        font-weight: 500;
    }

    .parent-header-avatar {
        top: -40px;
        width: 170px;
        height: 170px;
        right: -19px;
    }
}
</style>

<!-- =======================
     Parent Header Component
     ======================= -->
<center>
<div class="parent-header-card mt-5">
    <div class="parent-header-content">
        <div class="parent-avatar-circle">
            <?php
                $initials = strtoupper(substr($parent_info['full_name'], 0, 2));
                echo $initials;
            ?>
        </div>
        <div class="parent-header-text">
            <h1>Hi! <?php echo htmlspecialchars($parent_info['full_name']); ?></h1>
            <h3 class="role">It's good to see you again.</h3>
        </div>
    </div>
    <img src="../assets/img/avatar.png" alt="Avatar" class="parent-header-avatar">
</div>
</center>
