<?php
include 'config.php';


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Je bent niet ingelogd.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag.']);
    exit;
}

$reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

if ($reservation_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige reservering.']);
    exit;
}

$sql = "SELECT user_id, type FROM reservations WHERE id = $reservation_id";
$res = mysqli_query($db, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Reservering niet gevonden.']);
    exit;
}

$row = mysqli_fetch_assoc($res);
$user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_owner = ((int)$row['user_id'] === $user_id);
$is_lesson = ($row['type'] === 'lesson');

if (!$is_admin && (!$is_owner || $is_lesson)) {
    echo json_encode(['success' => false, 'message' => 'Je mag deze reservering niet verwijderen. Alleen admin kan lessen verwijderen.']);
    exit;
}


$sql = "DELETE FROM reservations WHERE id = $reservation_id";
if (mysqli_query($db, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Reservering succesvol verwijderd!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Fout bij verwijderen: ' . mysqli_error($db)]);
}
exit;
