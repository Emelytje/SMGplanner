<?php
include 'config.php';

$error_text = "";

if (isset($_POST['username']) && isset($_POST['password'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === "" || $password === "") {
        $error_text = "Vul alles in.";
    } else {

        $sql = "SELECT * FROM users WHERE username = '" . mysqli_real_escape_string($db, $username) . "' LIMIT 1";
        $result = mysqli_query($db, $sql);

        if (!$result) {
            $error_text = "Databasefout: " . mysqli_error($db);
        } elseif ($row = mysqli_fetch_assoc($result)) {

            if ($row['password'] == $password) {

                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                
                // Ensure session is saved
                session_write_close();

                header("Location: index.php");
                exit;

            } else {
                $error_text = "Wachtwoord klopt niet.";
            }

        } else {
            $error_text = "Gebruiker bestaat niet.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Inloggen - SMG Stables</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<div class="login-container">
    <h2>Inloggen</h2>

    <?php if ($error_text !== '') { ?>
        <div class="error"><?php echo $error_text; ?></div>
    <?php } ?>

    <form method="post">
        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username">

        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password">

        <button type="submit">Inloggen</button>
    </form>
</div>
</body>
</html>
