<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
} else {
    $user_id = 0;
}

$is_new = ($user_id == 0);
$error_text = "";
$info_text  = "";

if (isset($_POST['username'])) {

    $username   = $_POST['username'];
    $email      = isset($_POST['email']) ? $_POST['email'] : "";
    $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : "";
    $last_name  = isset($_POST['last_name']) ? $_POST['last_name'] : "";
    $role       = isset($_POST['role']) ? $_POST['role'] : "user";
    $insured    = isset($_POST['insured']) ? 1 : 0;
    $password   = isset($_POST['password']) ? $_POST['password'] : "";

    if ($username === "") {
        $error_text = "Gebruikersnaam is verplicht.";
    }

    if ($is_new && $password === "") {
        $error_text = "Wachtwoord is verplicht voor nieuwe gebruiker.";
    }

    if ($error_text === "") {

        if ($is_new) {
            $sql = "INSERT INTO users (username, email, first_name, last_name, role, insured, password)
                    VALUES (
                        '" . $username . "',
                        '" . $email . "',
                        '" . $first_name . "',
                        '" . $last_name . "',
                        '" . $role . "',
                        " . $insured . ",
                        '" . $password . "'
                    )";
            mysqli_query($db, $sql);
            $info_text = "Gebruiker aangemaakt.";
            $is_new = false;

        } else {
            $sql = "UPDATE users SET
                        username = '" . $username . "',
                        email = '" . $email . "',
                        first_name = '" . $first_name . "',
                        last_name = '" . $last_name . "',
                        role = '" . $role . "',
                        insured = " . $insured . "
                    WHERE id = " . $user_id;
            mysqli_query($db, $sql);
            $info_text = "Gebruiker opgeslagen.";
        }
    }
}

$user_row = array(
    "username"   => "",
    "email"      => "",
    "first_name" => "",
    "last_name"  => "",
    "role"       => "user",
    "insured"    => 0
);

if (!$is_new) {
    $sql = "SELECT * FROM users WHERE id = " . $user_id . " LIMIT 1";
    $result = mysqli_query($db, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $user_row = $row;
    } else {
        $error_text = "Gebruiker niet gevonden.";
    }
}

include 'header.php';
?>

<div class="card">
    <h2><?php echo $is_new ? "Nieuwe gebruiker" : "Gebruiker bewerken"; ?></h2>

    <?php if ($error_text !== "") { ?>
        <div class="error"><?php echo $error_text; ?></div>
    <?php } ?>

    <?php if ($info_text !== "") { ?>
        <div class="success"><?php echo $info_text; ?></div>
    <?php } ?>

    <form method="post">
        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username" value="<?php echo $user_row['username']; ?>">

        <label for="first_name">Voornaam</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo $user_row['first_name']; ?>">

        <label for="last_name">Achternaam</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo $user_row['last_name']; ?>">

        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?php echo $user_row['email']; ?>">

        <label for="role">Rol</label>
        <select id="role" name="role">
            <option value="user" <?php if ($user_row['role'] === 'user') echo "selected"; ?>>Gebruiker</option>
            <option value="admin" <?php if ($user_row['role'] === 'admin') echo "selected"; ?>>Admin</option>
        </select>

        <label>
            <input type="checkbox" name="insured" <?php if ($user_row['insured']) echo "checked"; ?>>
            Verzekerd
        </label>

        <?php if ($is_new) { ?>
            <label for="password">Wachtwoord (nieuw)</label>
            <input type="password" id="password" name="password">
        <?php } else { ?>
            <p><em>Wachtwoord wijzigen kan niet hier.</em></p>
        <?php } ?>

        <button type="submit">Opslaan</button>
        <a class="btn btn-small" href="admin_users.php">Terug</a>
    </form>
</div>

<?php
include 'footer.php';
?>
