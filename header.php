<?php
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>SMG Stables</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div class="app-container">

<?php if (isset($_SESSION['user_id'])) { ?>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="assets/img/smg-logo.png" alt="SMG Stables">
        <span>SMG Stables</span>
    </div>
        <nav class="sidebar-nav">
            <a href="index.php">Dashboard</a>
            <a href="calendar.php">Kalender</a>
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'instructor')) { ?>
  <a href="approve_reservations.php">Goedkeuringen</a>
<?php } ?>
            <a href="profile.php">Mijn profiel</a>
            <a href="change_password.php">Wachtwoord wijzigen</a>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
                <span class="sidebar-section-title">Beheer</span>
                <a href="admin_users.php">Gebruikers beheren</a>
                <a href="admin_reservations.php">Reserveringen (per dag)</a>
                <a href="admin_tracks.php">Pistes beheren</a>
                <a href="instructor_schedule.php">Lesgeversavailability</a>
                <a href="admin_email_uninsured.php">Mail niet-verzekerden</a>
            <?php } ?>
        </nav>
        <div class="sidebar-bottom">
            <span>
                Ingelogd als<br>
                <?php
                if (isset($_SESSION['username'])) {
                    echo $_SESSION['username'];
                }
                ?>
            </span><br>
            <a class="btn btn-small" href="logout.php">Uitloggen</a>
        </div>
    </aside>
    <div class="menu-backdrop" id="menuBackdrop"></div>
<?php } ?>

    <main class="main-content<?php if (isset($_SESSION['user_id'])) { echo ' with-sidebar'; } ?>">
        <header class="topbar">
            <h1>SMG Stables reserveringssysteem</h1>
        </header>
        <section class="content">
            <?php
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];

    $q = mysqli_query($db, "
        SELECT id, approval_status, status_note, start_time, end_time
        FROM reservations
        WHERE user_id = $uid
          AND notified_user = 0
          AND approval_status IN ('approved','rejected')
        ORDER BY start_time DESC
        LIMIT 5
    ");

    if ($q && mysqli_num_rows($q) > 0) {
        while ($n = mysqli_fetch_assoc($q)) {
            $txt = ($n['approval_status'] === 'approved') ? "Goedgekeurd" : "Afgewezen";
            $cls = ($n['approval_status'] === 'approved') ? "alert-success" : "alert-error";
            $extra = ($n['approval_status'] === 'rejected' && $n['status_note']) ? " - ".$n['status_note'] : "";
            echo '<div class="alert '.$cls.'">Reservatie '.$txt.' ('.$n['start_time'].' - '.$n['end_time'].')'.$extra.'</div>';

            mysqli_query($db, "UPDATE reservations SET notified_user=1 WHERE id=".(int)$n['id']);
        }

        mysqli_query($db, "DELETE FROM reservations WHERE user_id=$uid AND approval_status='rejected' AND notified_user=1");
    }
}
?>
         