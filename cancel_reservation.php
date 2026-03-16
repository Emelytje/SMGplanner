<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_reservations.php');
    exit;
}

$reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';

if ($reservation_id === 0) {
    echo 'Ongeldige reservering. <a href="admin_reservations.php">Terug</a>';
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

$sql = "SELECT * FROM reservations WHERE id = $reservation_id AND status = 'active' LIMIT 1";
$result = mysqli_query($db, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    echo 'Reservering niet gevonden of al geannuleerd. <a href="admin_reservations.php">Terug</a>';
    exit;
}
$row = mysqli_fetch_assoc($result);

$is_owner = ((int)$row['user_id'] === $user_id);
$is_instructor = ((int)$row['instructor_id'] === $user_id);

if (!$is_owner && !$is_admin && !$is_instructor) {
    echo 'Je mag deze reservering niet annuleren. <a href="admin_reservations.php">Terug</a>';
    exit;
}


$sql_update = "UPDATE reservations SET status = 'canceled' WHERE id = $reservation_id";
mysqli_query($db, $sql_update);

$sql_insert = "INSERT INTO reservation_cancellations (reservation_id, user_id, late, reason, requires_payment) 
               VALUES ($reservation_id, $user_id, " . ($late ? 1 : 0) . ", '$reason', " . ($requires_payment ? 1 : 0) . ")";
mysqli_query($db, $sql_insert);

header('Location: admin_reservations.php');
exit;
?>