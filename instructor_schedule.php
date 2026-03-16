<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');


$sql_check = "SELECT role FROM users WHERE id = " . $user_id;
$res_check = mysqli_query($db, $sql_check);
if (!$res_check || mysqli_num_rows($res_check) === 0) {
    header('Location: login.php');
    exit;
}
$user_role = mysqli_fetch_assoc($res_check)['role'];

if ($user_role !== 'admin') {
    echo 'Alleen beheerders kunnen lesgeverschtijden beheren. <a href="calendar.php">Terug naar kalender</a>';
    exit;
}

include 'header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add') {
        $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;
        $day_of_week = isset($_POST['day_of_week']) ? (int)$_POST['day_of_week'] : -1;
        $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
        $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
        
        if ($instructor_id === 0 || $day_of_week < 0 || $day_of_week > 6 || $start_time === '' || $end_time === '') {
            echo '<p style="color:red;">Vul alle velden in.</p>';
        } else {
            $sql = "INSERT INTO instructor_availability (instructor_id, day_of_week, start_time, end_time)
                  VALUES (" . $instructor_id . ", " . $day_of_week . ", '" . $start_time . "', '" . $end_time . "')
                    ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time)";
            if (mysqli_query($db, $sql)) {
                echo '<p style="color:green;">Beschikbaarheid opgeslagen!</p>';
            } else {
                echo '<p style="color:red;">Fout bij opslaan: ' . mysqli_error($db) . '</p>';
            }
        }
    } elseif ($action === 'delete') {
        $availability_id = isset($_POST['availability_id']) ? (int)$_POST['availability_id'] : 0;
        if ($availability_id > 0) {
            $sql = "DELETE FROM instructor_availability WHERE id = " . $availability_id;
            mysqli_query($db, $sql);
            echo '<p style="color:green;">Beschikbaarheid verwijderd!</p>';
        }
    }
}

$instructors = array();
$sql_inst = "SELECT id, username FROM users WHERE role = 'admin' OR id IN (
             SELECT DISTINCT instructor_id FROM reservations WHERE instructor_id IS NOT NULL)
             ORDER BY username";
$res_inst = mysqli_query($db, $sql_inst);
if ($res_inst) {
    while ($row = mysqli_fetch_assoc($res_inst)) {
        $instructors[] = $row;
    }
}

$days = array('Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag');
?>

<div class="card">
    <h2>Lesgeversavailability (Beschikbaarheid)</h2>
    
    <div style="display: flex; gap: 2rem; margin-bottom: 2rem;">
    
        <div style="flex: 1; border: 1px solid #ddd; padding: 1rem; border-radius: 4px;">
            <h3>Voeg beschikbaarheid toe</h3>
            <form method="post">
                <input type="hidden" name="action" value="add">
                
                <label>Lesgever</label>
                <select name="instructor_id" required>
                    <option value="">-- Selecteer lesgever --</option>
                    <?php foreach ($instructors as $inst) { ?>
                        <option value="<?php echo $inst['id']; ?>"><?php echo ($inst['username']); ?></option>
                    <?php } ?>
                </select>
                
                <label>Dag van de week</label>
                <select name="day_of_week" required>
                    <option value="">-- Selecteer dag --</option>
                    <?php for ($i = 0; $i < 7; $i++) { ?>
                        <option value="<?php echo $i; ?>"><?php echo $days[$i]; ?></option>
                    <?php } ?>
                </select>
                
                <label>Start tijd (bijv. 14:00)</label>
                <input type="time" name="start_time" required>
                
                <label>Eind tijd (bijv. 18:00)</label>
                <input type="time" name="end_time" required>
                
                <button type="submit" style="margin-top: 1rem;">Opslaan</button>
            </form>
        </div>
        
        <!-- Current availability overview -->
        <div style="flex: 1;">
            <h3>Huidge beschikbaarheid per lesgever</h3>
            <?php 
            if (count($instructors) === 0) { 
                echo '<p>Geen lesgevers beschikbaar.</p>';
            } else {
                foreach ($instructors as $inst) {
                    echo '<div style="margin-bottom: 1.5rem; border: 1px solid #e0e0e0; padding: 1rem; border-radius: 4px;">';
                    echo '<h4>' . ($inst['username']) . '</h4>';
                    
                    $sql_avail = "SELECT * FROM instructor_availability 
                                  WHERE instructor_id = " . $inst['id'] . " 
                                  ORDER BY day_of_week, start_time";
                    $res_avail = mysqli_query($db, $sql_avail);
                    
                    if ($res_avail && mysqli_num_rows($res_avail) > 0) {
                        echo '<table style="width: 100%; font-size: 0.9rem;">';
                        echo '<tr style="border-bottom: 1px solid #ddd;"><td><strong>Dag</strong></td><td><strong>Van-Tot</strong></td><td></td></tr>';
                        while ($avail = mysqli_fetch_assoc($res_avail)) {
                            echo '<tr style="border-bottom: 1px solid #f0f0f0;">';
                            echo '<td>' . $days[(int)$avail['day_of_week']] . '</td>';
                            echo '<td>' . substr($avail['start_time'], 0, 5) . ' - ' . substr($avail['end_time'], 0, 5) . '</td>';
                            echo '<td>';
                            echo '<form method="post" style="display: inline;">';
                            echo '<input type="hidden" name="action" value="delete">';
                            echo '<input type="hidden" name="availability_id" value="' . $avail['id'] . '">';
                            echo '<button type="submit" style="background: #ef4444; padding: 2px 6px; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.8rem;">Verwijderen</button>';
                            echo '</form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<p style="color: #999; margin: 0;">Geen beschikbaarheid ingesteld.</p>';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
