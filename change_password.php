<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error_text = "";
$info_text  = "";

if (isset($_POST['old_password']) && isset($_POST['new_password']) && isset($_POST['new_password2'])) {

    $old_password  = $_POST['old_password'];
    $new_password  = $_POST['new_password'];
    $new_password2 = $_POST['new_password2'];

    if ($old_password === "" || $new_password === "" || $new_password2 === "") {
        $error_text = "Vul alle velden in.";
    } elseif ($new_password !== $new_password2) {
        $error_text = "Nieuwe wachtwoorden komen niet overeen.";
    } else {

        $sql = "SELECT password FROM users WHERE id = " . $user_id . " LIMIT 1";
        $result = mysqli_query($db, $sql);

        if ($result && $row = mysqli_fetch_assoc($result)) {

            if ($row['password'] == $old_password) {

                $sql_upd = "UPDATE users SET password = '" . $new_password . "' WHERE id = " . $user_id;
                mysqli_query($db, $sql_upd);
                $info_text = "Wachtwoord gewijzigd.";

            } else {
                $error_text = "Oud wachtwoord klopt niet.";
            }

        } else {
            $error_text = "Gebruiker niet gevonden.";
        }
    }
}

include 'header.php';
?>

<div class="card">
    <h2>Wachtwoord wijzigen</h2>

    <?php if ($error_text !== "") { ?>
        <div class="error"><?php echo $error_text; ?></div>
    <?php } ?>

    <?php if ($info_text !== "") { ?>
        <div class="success"><?php echo $info_text; ?></div>
    <?php } ?>

    <form method="post">
        <label>Oud wachtwoord</label>
        <input type="password" name="old_password">

        <label>Nieuw wachtwoord</label>
        <input type="password" name="new_password">

        <label>Nieuw wachtwoord (herhaal)</label>
        <input type="password" name="new_password2">

        <button type="submit" style="margin-top:1rem;">Opslaan</button>
    </form>
</div>

<?php
include 'footer.php';
?>
