<?php
include 'config.php';
require 'mailer.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$is_admin = ($role === 'admin');

$where = $is_admin ? "" : " AND r.instructor_id = $user_id ";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int)($_POST['reservation_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');

    $q = mysqli_query($db, "
        SELECT r.*, u.email AS user_email, u.username AS user_name, t.name AS track_name
        FROM reservations r
        JOIN users u ON u.id = r.user_id
        JOIN tracks t ON t.id = r.track_id
        WHERE r.id = $rid
        LIMIT 1
    ");
    $r = mysqli_fetch_assoc($q);

    if ($r) {
        if (!$is_admin && (int)$r['instructor_id'] !== $user_id) {
            die("Geen rechten.");
        }

        if ($action === 'approve') {
            mysqli_query($db, "UPDATE reservations SET approval_status='approved', notified_user=0, status_note=NULL WHERE id=$rid");

            sendMailDb(
                $r['user_email'], $r['user_name'],
                "SMG Stables  Reservatie goedgekeurd",
                "Hallo {$r['user_name']},\n\nJe reservatie is GOEDGEKEURD.\n\nPiste: {$r['track_name']}\nVan: {$r['start_time']}\nTot: {$r['end_time']}\n\nGroetjes\nSMG Stables"
            );

        } elseif ($action === 'reject') {
            
            mysqli_query($db, "UPDATE reservations SET approval_status='rejected', notified_user=0, status_note='$note' WHERE id=$rid");

            sendMailDb(
                $r['user_email'], $r['user_name'],
                "SMG Stables - Reservatie afgewezen",
                "Hallo {$r['user_name']},\n\nJe reservatie is AFGEWEZEN.\n\nPiste: {$r['track_name']}\nVan: {$r['start_time']}\nTot: {$r['end_time']}\nReden: {$note}\n\nGroetjes\nSMG Stables"
            );
        }
    }
}

include 'header.php';

$sql = "
SELECT r.id, r.start_time, r.end_time, t.name AS track_name, u.username AS user_name
FROM reservations r
JOIN tracks t ON t.id = r.track_id
JOIN users u ON u.id = r.user_id
WHERE r.status='active'
  AND r.approval_status='pending'
  $where
ORDER BY r.start_time
";
$res = mysqli_query($db, $sql);
?>

<div class="card">
  <h2>Goedkeuringen</h2>

  <?php if (!$res || mysqli_num_rows($res) === 0) { ?>
    <p>Geen aanvragen in behandeling.</p>
  <?php } else { ?>
    <table class="table">
      <thead>
        <tr>
          <th>Datum</th><th>Piste</th><th>Aanvrager</th><th>Actie</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = mysqli_fetch_assoc($res)) { ?>
        <tr>
          <td><?php echo ($row['start_time'].' - '.$row['end_time']); ?></td>
          <td><?php echo ($row['track_name']); ?></td>
          <td><?php echo ($row['user_name']); ?></td>
          <td>
            <form method="post" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
              <input type="hidden" name="reservation_id" value="<?php echo (int)$row['id']; ?>">
              <button class="btn" name="action" value="approve" type="submit">Goedkeuren</button>
              <input type="text" name="note" placeholder="Reden (bij afwijzen)" style="max-width:220px;">
              <button class="btn" name="action" value="reject" type="submit" style="background:#ef4444;">Afwijzen</button>
            </form>
          </td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  <?php } ?>
</div>

<?php include 'footer.php'; ?>