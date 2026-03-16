<?php
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Niet ingelogd.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag.']);
    exit;
}

$raw_id = isset($_POST['id']) ? $_POST['id'] : '';
$start  = isset($_POST['start']) ? $_POST['start'] : '';
$end    = isset($_POST['end']) ? $_POST['end'] : '';

if ($raw_id === '' || $start === '' || $end === '') {
    echo json_encode(['success' => false, 'message' => 'Ontbrekende parameters.']);
    exit;
}


$kind = $raw_id[0];
$id   = (int)substr($raw_id, 1);

$user_id = (int)$_SESSION['user_id'];

if ($kind === 'b') {

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Alleen admin kan blokkades verplaatsen.']);
        exit;
    }

    $sql = "SELECT * FROM blocked_times WHERE id = " . $id . " LIMIT 1";
    $res = mysqli_query($db, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        echo json_encode(['success' => false, 'message' => 'Blokkade niet gevonden.']);
        exit;
    }
    $row = mysqli_fetch_assoc($res);
    $track_id = (int)$row['track_id'];


    $sql_b = "SELECT COUNT(*) AS c
              FROM blocked_times
              WHERE track_id = " . $track_id . "
                AND id <> " . $id . "
                AND NOT (end_time <= '" . $start . "' OR start_time >= '" . $end . "')";
    $res_b = mysqli_query($db, $sql_b);
    $row_b = mysqli_fetch_assoc($res_b);
    if ((int)$row_b['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Andere blokkade in dit tijdslot.']);
        exit;
    }

   
    $sql_r = "SELECT COUNT(*) AS c
              FROM reservations
              WHERE track_id = " . $track_id . "
                AND status = 'active'
                AND NOT (end_time <= '" . $start . "' OR start_time >= '" . $end . "')";
    $res_r = mysqli_query($db, $sql_r);
    $row_r = mysqli_fetch_assoc($res_r);
    if ((int)$row_r['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Reservering in dit tijdslot.']);
        exit;
    }

    $sql_u = "UPDATE blocked_times
              SET start_time = '" . $start . "',
                  end_time   = '" . $end . "'
              WHERE id = " . $id;
    if (mysqli_query($db, $sql_u)) {
        echo json_encode(['success' => true, 'message' => 'Blokkade verplaatst!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fout bij verplaatsen: ' . mysqli_error($db)]);
    }
    exit;
} elseif ($kind === 'r') {
  
    $sql = "SELECT * FROM reservations WHERE id = " . $id . " LIMIT 1";
    $res = mysqli_query($db, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        echo json_encode(['success' => false, 'message' => 'Reservering niet gevonden.']);
        exit;
    }
    $row = mysqli_fetch_assoc($res);

    $is_owner      = ((int)$row['user_id'] === $user_id);
    $is_admin      = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $is_instructor = ((int)$row['instructor_id'] === $user_id);
    $is_lesson     = ($row['type'] === 'lesson');

   
    if ($is_lesson && !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Alleen admin kan lessen verplaatsen.']);
        exit;
    }

    if (!$is_lesson && !$is_owner && !$is_admin && !$is_instructor) {
        echo json_encode(['success' => false, 'message' => 'Je mag deze reservering niet verplaatsen.']);
        exit;
    }

    $track_id = (int)$row['track_id'];

)
    $sql_r2 = "SELECT COUNT(*) AS c
               FROM reservations
               WHERE track_id = " . $track_id . "
                 AND status = 'active'
                 AND id <> " . $id . "
                 AND NOT (end_time <= '" . $start . "' OR start_time >= '" . $end . "')";
    $res2 = mysqli_query($db, $sql_r2);
    $row2 = mysqli_fetch_assoc($res2);
    if ((int)$row2['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Andere reservering in dit tijdslot op deze piste.']);
        exit;
    }


    $sql_b2 = "SELECT COUNT(*) AS c
               FROM blocked_times
               WHERE track_id = " . $track_id . "
                 AND NOT (end_time <= '" . $start . "' OR start_time >= '" . $end . "')";
    $res_b2 = mysqli_query($db, $sql_b2);
    $row_b2 = mysqli_fetch_assoc($res_b2);
    if ((int)$row_b2['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Dit tijdslot is geblokkeerd.']);
        exit;
    }

   
    if ($is_lesson) {
        $instructor_id = (int)$row['instructor_id'];
        $day_of_week = date('w', strtotime($start));
        $start_time_only = date('H:i', strtotime($start));
        $end_time_only = date('H:i', strtotime($end));
        
        $sql_avail = "SELECT COUNT(*) AS c
                      FROM instructor_availability
                      WHERE instructor_id = " . $instructor_id . "
                        AND day_of_week = " . $day_of_week . "
                        AND start_time <= '" . $start_time_only . "'
                        AND end_time >= '" . $end_time_only . "'";
        $res_avail = mysqli_query($db, $sql_avail);
        $row_avail = mysqli_fetch_assoc($res_avail);
        if ((int)$row_avail['c'] === 0) {
            echo json_encode(['success' => false, 'message' => 'Lesgever niet beschikbaar op dit moment.']);
            exit;
        }
        
      
        $sql_conflict = "SELECT COUNT(*) AS c
                         FROM reservations
                         WHERE instructor_id = " . $instructor_id . "
                           AND type = 'lesson'
                           AND status = 'active'
                           AND id <> " . $id . "
                           AND NOT (end_time <= '" . $start . "' OR start_time >= '" . $end . "')";
        $res_conflict = mysqli_query($db, $sql_conflict);
        $row_conflict = mysqli_fetch_assoc($res_conflict);
        if ((int)$row_conflict['c'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Lesgever heeft ander les op hetzelfde moment.']);
            exit;
        }
    }

    $sql_upd = "UPDATE reservations
                SET start_time = '" . $start . "',
                    end_time   = '" . $end . "'
                WHERE id = " . $id;
    if (mysqli_query($db, $sql_upd)) {
        echo json_encode(['success' => true, 'message' => 'Reservering verplaatst!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fout bij verplaatsen: ' . mysqli_error($db)]);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Ongeldig type.']);
    exit;
}
