<?php
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Je bent niet ingelogd.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET request - return HTML, not JSON
    header('Content-Type: text/html; charset=utf-8');
    
    // Debug: check session
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        echo "Sessie niet beschikbaar. Inloggen nodig.";
        exit;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    // Debug: log the query
    $sql_user = "SELECT id, username, email, phone, first_name, last_name 
                 FROM users 
                 WHERE id = " . $user_id;
    $res_user = mysqli_query($db, $sql_user);

    if (!$res_user) {
        echo "Databasefout: " . mysqli_error($db) . " | SQL: " . $sql_user;
        exit;
    }

    if (mysqli_num_rows($res_user) === 0) {
        echo "Gebruiker met ID " . $user_id . " niet gevonden in database.";
        exit;
    }

    $user = mysqli_fetch_assoc($res_user);

    include 'header.php';
    ?>

    <div class="card">
        <h2>Mijn profiel</h2>

        <div id="form_message" style="display:none;padding:0.5rem;margin-bottom:1rem;border-radius:4px;"></div>

        <form id="profileForm" method="post" action="profile.php">
            <label>Gebruikersnaam</label>
            <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>

            <label>Voornaam</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">

            <label>Achternaam</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">

            <label>E-mail</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">

            <label>Telefoon</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">

            <button type="submit" style="margin-top:1rem;">Opslaan</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = new FormData(profileForm);
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'profile.php', true);
                    
                    xhr.onload = function() {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            var messageDiv = document.getElementById('form_message');
                            
                            if (response.success) {
                                messageDiv.style.display = 'block';
                                messageDiv.style.backgroundColor = '#d1fae5';
                                messageDiv.style.color = '#065f46';
                                messageDiv.textContent = response.message;
                            } else {
                                messageDiv.style.display = 'block';
                                messageDiv.style.backgroundColor = '#fee2e2';
                                messageDiv.style.color = '#991b1b';
                                messageDiv.textContent = response.message;
                            }
                        } catch (err) {
                            alert('Error: ' + err.message);
                        }
                    };
                    
                    xhr.onerror = function() {
                        alert('Er is een fout opgetreden bij het versturen van het formulier.');
                    };
                    
                    xhr.send(formData);
                });
            }
        });
    </script>

    <?php
    include 'footer.php';
    exit;
}

// POST request - handle update and return JSON
$user_id = (int)$_SESSION['user_id'];

$email      = isset($_POST['email']) ? $_POST['email'] : "";
$phone      = isset($_POST['phone']) ? $_POST['phone'] : "";
$first_name = isset($_POST['first_name']) ? $_POST['first_name'] : "";
$last_name  = isset($_POST['last_name']) ? $_POST['last_name'] : "";

$sql = "UPDATE users SET 
            email = '" . mysqli_real_escape_string($db, $email) . "',
            phone = '" . mysqli_real_escape_string($db, $phone) . "',
            first_name = '" . mysqli_real_escape_string($db, $first_name) . "',
            last_name = '" . mysqli_real_escape_string($db, $last_name) . "'
        WHERE id = " . $user_id;

if (mysqli_query($db, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Gegevens opgeslagen!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Fout bij opslaan: ' . mysqli_error($db)]);
}
exit;
