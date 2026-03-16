<?php
include 'config.php';


header('Content-Type: application/json');


set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile:$errline");
    echo json_encode(['success' => false, 'message' => 'Interne fout (PHP).']);
    exit;
});

error_log("=== save_reservation.php started ===");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Je bent niet ingelogd.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag.']);
    exit;
}

$type           = isset($_POST['type']) ? $_POST['type'] : 'piste';
$track_id       = isset($_POST['track_id']) ? (int)$_POST['track_id'] : 0;
$start_in       = isset($_POST['start']) ? $_POST['start'] : '';
$end_in         = isset($_POST['end']) ? $_POST['end'] : '';
$rider_name     = isset($_POST['rider_name']) ? $_POST['rider_name'] : '';
$notes          = isset($_POST['notes']) ? $_POST['notes'] : '';
$instructor_id  = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;
$reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;


error_log("Received: type=$type, track_id=$track_id, start=$start_in, end=$end_in, res_id=$reservation_id, instr=$instructor_id");

if ($track_id === 0 || $start_in === '') {
    echo json_encode(['success' => false, 'message' => 'Vul alle verplichte velden in.']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');


$posted_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $current_user_id;
$user_id = $is_admin ? $posted_user_id : $current_user_id;


$start_db = str_replace('T', ' ', $start_in) . ':00';

if ($end_in === '') {
   
    try {
        $end_time = new DateTime($start_in);
        $end_time->modify('+30 minutes');
        $end_db = $end_time->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ongeldig datumformat.']);
        exit;
    }
} else {
    $end_db = str_replace('T', ' ', $end_in) . ':00';
}


if (strtotime($end_db) <= strtotime($start_db)) {
    echo json_encode(['success' => false, 'message' => 'Eindtijd moet na starttijd liggen.']);
    exit;
}


$approval_status = 'approved';


if ($type === 'lesson') {
    if ($instructor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Bij een les moet een lesgever geselecteerd zijn.']);
        exit;
    }
} else {
   
    $instructor_id = 0;

   
    if ($type === 'piste') {
        $dow = (int)date('w', strtotime($start_db)); 
        $start_time_only = date('H:i:s', strtotime($start_db));
        $end_time_only   = date('H:i:s', strtotime($end_db));

        $q = mysqli_query($db, "
            SELECT COUNT(*) AS c
            FROM instructor_availability
            WHERE day_of_week = $dow
              AND start_time <= '$start_time_only'
              AND end_time   >= '$end_time_only'
        ");

        if ($q) {
            $row = mysqli_fetch_assoc($q);
            if ((int)$row['c'] > 0) {
                $approval_status = 'pending';
            }
        } else {
            error_log("Availability query failed: " . mysqli_error($db));
        }
    }
}


if ($type === 'blocked') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Alleen admin kan blokkeren.']);
        exit;
    }

    $sql_check_b = "SELECT COUNT(*) AS c
                    FROM blocked_times
                    WHERE track_id = $track_id
                      AND NOT (end_time <= '" .  $start_db . "'
                               OR start_time >= '" . $end_db . "')";
    $res_b = mysqli_query($db, $sql_check_b);
    if (!$res_b) {
        echo json_encode(['success' => false, 'message' => 'Databasefout: ' . mysqli_error($db)]);
        exit;
    }
    $row_b = mysqli_fetch_assoc($res_b);
    if ((int)$row_b['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Er is al een blokkade in dit tijdslot.']);
        exit;
    }


    $sql_check_r = "SELECT COUNT(*) AS c
                    FROM reservations
                    WHERE track_id = $track_id
                      AND status = 'active'
                      AND NOT (end_time <= '" .  $start_db . "'
                               OR start_time >= '" .  $end_db . "')";
    $res_r = mysqli_query($db, $sql_check_r);
    if (!$res_r) {
        echo json_encode(['success' => false, 'message' => 'Databasefout: ' . mysqli_error($db)]);
        exit;
    }
    $row_r = mysqli_fetch_assoc($res_r);
    if ((int)$row_r['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Er zijn al reserveringen in dit tijdslot.']);
        exit;
    }

   
    $sql = "INSERT INTO blocked_times (track_id, start_time, end_time, reason)
            VALUES ($track_id,
                    '" . $start_db . "',
                    '" . $end_db . "',
                    '" . $notes . "')";
    if (mysqli_query($db, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Blokkade succesvol opgeslagen!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fout bij opslaan: ' . mysqli_error($db)]);
    }
    exit;
}


$sql_check_r2 = "SELECT COUNT(*) AS c
                 FROM reservations
                 WHERE track_id = $track_id
                   AND status = 'active'
                   AND NOT (end_time <= '" .  $start_db . "'
                            OR start_time >= '" . $end_db . "')";

if ($reservation_id > 0) {
    $sql_check_r2 .= " AND id <> $reservation_id";
}

$res2 = mysqli_query($db, $sql_check_r2);
if (!$res2) {
    error_log("Overlap query failed: " . mysqli_error($db) . " | SQL: " . $sql_check_r2);
    echo json_encode(['success' => false, 'message' => 'Databasefout bij controleren overlap.']);
    exit;
}
$row2 = mysqli_fetch_assoc($res2);
if ((int)$row2['c'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Deze piste is al gereserveerd in dit tijdslot.']);
    exit;
}


$sql_check_b2 = "SELECT COUNT(*) AS c
                 FROM blocked_times
                 WHERE track_id = $track_id
                   AND NOT (end_time <= '" .  $start_db . "'
                            OR start_time >= '" .  $end_db . "')";
$res_b2 = mysqli_query($db, $sql_check_b2);
if (!$res_b2) {
    error_log("Blocked query failed: " . mysqli_error($db) . " | SQL: " . $sql_check_b2);
    echo json_encode(['success' => false, 'message' => 'Databasefout bij controleren blokkades.']);
    exit;
}
$row_b2 = mysqli_fetch_assoc($res_b2);
if ((int)$row_b2['c'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Dit tijdslot is geblokkeerd.']);
    exit;
}


if ($type === 'lesson') {

    $day_of_week = (int)date('w', strtotime($start_db));
    $start_time_only = date('H:i:s', strtotime($start_db));
    $end_time_only   = date('H:i:s', strtotime($end_db));

    
    $sql_avail = "SELECT COUNT(*) AS c
                  FROM instructor_availability
                  WHERE instructor_id = $instructor_id
                    AND day_of_week = $day_of_week
                    AND start_time <= '$start_time_only'
                    AND end_time   >= '$end_time_only'";
    $res_avail = mysqli_query($db, $sql_avail);
    if (!$res_avail) {
        echo json_encode(['success' => false, 'message' => 'Databasefout (availability).']);
        exit;
    }
    $row_avail = mysqli_fetch_assoc($res_avail);
    if ((int)$row_avail['c'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Deze lesgever is niet beschikbaar op dit moment.']);
        exit;
    }

   
    $sql_conflict = "SELECT COUNT(*) AS c
                     FROM reservations
                     WHERE instructor_id = $instructor_id
                       AND type = 'lesson'
                       AND status = 'active'
                       AND NOT (end_time <= '" .  $start_db . "'
                                OR start_time >= '" . $end_db . "')";
    if ($reservation_id > 0) {
        $sql_conflict .= " AND id <> $reservation_id";
    }
    $res_conflict = mysqli_query($db, $sql_conflict);
    if (!$res_conflict) {
        echo json_encode(['success' => false, 'message' => 'Databasefout (conflict).']);
        exit;
    }
    $row_conflict = mysqli_fetch_assoc($res_conflict);
    if ((int)$row_conflict['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Deze lesgever heeft al een les op hetzelfde moment.']);
        exit;
    }


    $approval_status = 'approved';
}

$instructor_id_value = ($instructor_id === 0) ? "NULL" : (int)$instructor_id;

if ($reservation_id === 0) {

    $sql = "INSERT INTO reservations
                (user_id, track_id, start_time, end_time, type, notes, rider_name, instructor_id, status, approval_status)
            VALUES
                ($user_id,
                 $track_id,
                 '" .$start_db . "',
                 '" .  $end_db . "',
                 '" .  $type . "',
                 '" . , $notes . "',
                 '" . $rider_name . "',
                 $instructor_id_value,
                 'active',
                 '" . $approval_status . "')";

    error_log("INSERT SQL: " . $sql);

    if (mysqli_query($db, $sql)) {
        $msg = ($approval_status === 'pending')
            ? 'Aanvraag opgeslagen en staat IN BEHANDELING.'
            : 'Reservering succesvol opgeslagen!';
        echo json_encode(['success' => true, 'message' => $msg, 'approval_status' => $approval_status]);
    } else {
        error_log("INSERT failed: " . mysqli_error($db));
        echo json_encode(['success' => false, 'message' => 'Fout bij opslaan: ' . mysqli_error($db)]);
    }

} else {

    
    $sql_owner = "SELECT user_id, instructor_id, type FROM reservations WHERE id = $reservation_id LIMIT 1";
    $res_owner = mysqli_query($db, $sql_owner);
    if (!$res_owner || mysqli_num_rows($res_owner) === 0) {
        echo json_encode(['success' => false, 'message' => 'Reservering niet gevonden.']);
        exit;
    }
    $row_o = mysqli_fetch_assoc($res_owner);

    $is_owner      = ((int)$row_o['user_id'] === $current_user_id);
    $is_instructor = ((int)$row_o['instructor_id'] === $current_user_id);
    $is_lesson_old = ($row_o['type'] === 'lesson');

   
    if ($is_lesson_old && !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Alleen admin kan lessen aanpassen.']);
        exit;
    }

   
    if (!$is_lesson_old && !$is_owner && !$is_admin && !$is_instructor) {
        echo json_encode(['success' => false, 'message' => 'Je mag deze reservering niet aanpassen.']);
        exit;
    }

  
    if ($type !== 'lesson') {
        $instructor_id_value = "NULL";
    }

    $sql = "UPDATE reservations
            SET track_id        = $track_id,
                start_time      = '" . $start_db . "',
                end_time        = '" . $end_db . "',
                type            = '" . $type . "',
                notes           = '" .  $notes . "',
                rider_name      = '" .$rider_name . "',
                instructor_id   = $instructor_id_value,
                approval_status = '" .  $approval_status . "'
            WHERE id = $reservation_id";

    if (mysqli_query($db, $sql)) {
        $msg = ($approval_status === 'pending')
            ? 'Aanpassing opgeslagen en staat IN BEHANDELING.'
            : 'Reservering succesvol bijgewerkt!';
        echo json_encode(['success' => true, 'message' => $msg, 'approval_status' => $approval_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fout bij bijwerken: ' . mysqli_error($db)]);
    }
}

exit;