<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$total_users = 0;
$sql = "SELECT COUNT(*) AS c FROM users";
$res = mysqli_query($db, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $total_users = $row['c'];
}

$insured_users = 0;
$sql = "SELECT COUNT(*) AS c FROM users WHERE insured = 1";
$res = mysqli_query($db, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $insured_users = $row['c'];
}

$uninsured_users = 0;
$sql = "SELECT COUNT(*) AS c FROM users WHERE insured = 0";
$res = mysqli_query($db, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $uninsured_users = $row['c'];
}


$today_reservations = 0;
$sql = "SELECT COUNT(*) AS c FROM reservations WHERE DATE(start_time) = CURDATE() AND status = 'active'";
$res = mysqli_query($db, $sql);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $today_reservations = $row['c'];
}


include 'header.php';
?>

<div class="grid">
    <div class="card">
        <h2>Overzicht</h2>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
            <p><strong>Totaal gebruikers:</strong> <?php echo $total_users; ?></p>
            <p><strong>Verzekerden:</strong> <?php echo $insured_users; ?></p>
            <p><strong>Niet-verzekerden:</strong> <?php echo $uninsured_users; ?></p>
        <?php } ?>

        <p><strong>Reserveringen vandaag:</strong> <?php echo $today_reservations; ?></p>
    </div>

   

    <div class="card">
        <h2>Snel naar</h2>
        <ul>
            <li><a href="profile.php">Mijn profiel</a></li>
            <li><a href="change_password.php">Wachtwoord wijzigen</a></li>
            <li><a href="calendar.php">Kalender</a></li>

            <?php if ($_SESSION['role'] === 'admin') { ?>
                <li><a href="admin_users.php">Gebruikers beheren</a></li>
                <li><a href="admin_reservations.php">Reserveringen beheren</a></li>
                <li><a href="admin_email_uninsured.php">Mail niet-verzekerden</a></li>
            <?php } ?>
        </ul>
    </div>
</div>

<?php
include 'footer.php';
?>
