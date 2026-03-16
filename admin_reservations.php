<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'header.php';

$user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (isset($_GET['date'])) {
    $filter_date = $_GET['date'];
} else {
    $filter_date = date('Y-m-d');
}

if (isset($_GET['type'])) {
    $filter_type = $_GET['type'];
} else {
    $filter_type = 'all';
}



$list = array();

$where = "DATE(r.start_time) = '" . $date . "'";

if (!$is_admin) {
    $where .= " AND r.user_id = " . $user_id;
}

if ($filter_type === 'piste') {
    $where .= " AND r.type = 'piste'";
} elseif ($filter_type === 'lesson') {
    $where .= " AND r.type = 'lesson'";
}

$sql = "SELECT r.*,
               t.name AS track_name,
               u.username AS user_name,
               i.username AS instructor_name
        FROM reservations r
        LEFT JOIN tracks t ON r.track_id = t.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN users i ON r.instructor_id = i.id
        WHERE r.status = 'active' AND " . $where . "
        ORDER BY r.start_time";

$res = mysqli_query($db, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['kind'] = 'reservation';
        $list[] = $row;
    }
}

if ($is_admin && ($filter_type === 'all' || $filter_type === 'blocked')) {
    $sql_b = "SELECT b.*, t.name AS track_name
              FROM blocked_times b
              LEFT JOIN tracks t ON b.track_id = t.id
              WHERE DATE(b.start_time) = '" . $date . "'
              ORDER BY b.start_time";
    $res_b = mysqli_query($db, $sql_b);
    if ($res_b) {
        while ($row = mysqli_fetch_assoc($res_b)) {
            $row['kind'] = 'blocked';
            $list[] = $row;
        }
    }
}

usort($list, function($a, $b) {
    if ($a['start_time'] == $b['start_time']) return 0;
    return ($a['start_time'] < $b['start_time']) ? -1 : 1;
});
?>
<div class="card">
    <h2>Reserveringen per dag</h2>

    <form method="get" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end;">
        <div>
            <label>Datum</label><br>
            <input type="date" name="date" value="<?php echo $filter_date; ?>">
        </div>
        <div>
            <label>Type</label><br>
            <select name="type">
                <option value="all"<?php if ($filter_type === 'all') echo ' selected'; ?>>Alles</option>
                <option value="piste"<?php if ($filter_type === 'piste') echo ' selected'; ?>>Piste</option>
                <option value="lesson"<?php if ($filter_type === 'lesson') echo ' selected'; ?>>Les</option>
                <option value="blocked"<?php if ($filter_type === 'blocked') echo ' selected'; ?>>Geblokkeerd</option>
            </select>
        </div>
        <div>
            <button type="submit">Filter</button>
        </div>
    </form>

    <?php if (count($list) === 0) { ?>
        <p>Geen blokken gevonden voor deze dag.</p>
    <?php } else { ?>
        <form method="post" action="bulk_delete.php" id="bulk_form">
        <table class="table">
            <thead>
            <tr>
                <th><input type="checkbox" id="select_all"></th>
                <th>Tijd</th>
                <th>Type</th>
                <th>Piste</th>
                <th>Ruiter</th>
                <th>Geboekt door</th>
                <th>Lesgever</th>
                <th>Notitie</th>
                <th>Acties</th>
            </tr>
            </thead>
            <tbody>
            <?php for ($i = 0; $i < count($list); $i++) { $row = $list[$i]; ?>
                <tr>
                    <td><input type="checkbox" name="selected[]" value="<?php echo $row['kind'] . '_' . $row['id']; ?>"></td>
                    <td>
                        <?php echo $row['start_time']; ?><br>
                        <?php echo $row['end_time']; ?>
                    </td>
                    <td>
                        <?php
                        if ($row['kind'] === 'blocked') {
                            echo '<span style="color:#ef4444;">Geblokkeerd</span>';
                        } else {
                            if ($row['type'] === 'lesson') {
                                echo '<span style="color:#ec4899;">Les</span>';
                            } else {
                                echo '<span style="color:#3b82f6;">Piste</span>';
                            }
                        }
                        ?>
                    </td>
                    <td><?php echo isset($row['track_name']) ? $row['track_name'] : ''; ?></td>
                    <td><?php echo isset($row['rider_name']) ? $row['rider_name'] : ''; ?></td>
                    <td><?php echo isset($row['user_name']) ? $row['user_name'] : ''; ?></td>
                    <td><?php echo isset($row['instructor_name']) ? $row['instructor_name'] : ''; ?></td>
                    <td><?php echo isset($row['notes']) ? $row['notes'] : ''; ?></td>
                    <td>
                        <?php if ($row['kind'] === 'reservation') { ?>
                            <form method="post" action="cancel_reservation.php" style="display:inline;" onsubmit="return confirmCancel(this, '<?php echo $row['start_time']; ?>');">
                                <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                <input type="text" name="reason" placeholder="Reden (indien nodig)" style="width:120px;">
                                <button type="submit">Annuleren</button>
                            </form>
                            <?php 
                         
                            $can_delete_admin = $is_admin;
                            $can_delete_owner = (!$is_admin && $user_id == $row['user_id'] && $row['type'] !== 'lesson');
                            
                            if ($can_delete_admin || $can_delete_owner) { 
                            ?>
                                <form method="post" action="delete_reservation.php" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze reservering wilt verwijderen?');">
                                    <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" style="background:#ef4444;">Verwijderen</button>
                                </form>
                            <?php } ?>
                        <?php } elseif ($row['kind'] === 'blocked' && $is_admin) { ?>
                            <form method="post" action="delete_blocked.php" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze blokkade wilt verwijderen?');">
                                <input type="hidden" name="blocked_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" style="background:#ef4444;">Verwijderen</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <div style="margin-top:1rem;">
            <button type="submit" name="action" value="delete_reservations" style="background:#ef4444;">Geselecteerde reserveringen verwijderen</button>
            <button type="submit" name="action" value="delete_blocked" style="background:#ef4444;">Geselecteerde blokkades verwijderen</button>
        </div>
        </form>
    <?php } ?>
</div>

<?php
include 'footer.php';
