<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include 'header.php';

$users = array();
$result = mysqli_query($db, "SELECT username, email, first_name, last_name FROM users WHERE insured = 0 ORDER BY username");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['email'] !== '') {
            $users[] = $row;
        }
    }
}

$email_list = '';
$first = true;
for ($i = 0; $i < count($users); $i++) {
    $u = $users[$i];
    if ($first) {
        $email_list = $u['email'];
        $first = false;
    } else {
        $email_list = $email_list . ';' . $u['email'];
    }
}

$subject = 'Verzekering paardrijden';
$body = "Beste ruiter,\n\nDit is een herinnering om je verzekering voor het paardrijden in orde te maken.\n\nMet vriendelijke groet,\nSMG Stables";
?>
<div class="card">
    <h2>E-mail naar niet-verzekerde gebruikers</h2>

    <?php if (count($users) === 0) { ?>
        <p>Alle gebruikers zijn verzekerd of er zijn geen gebruikers zonder e-mailadres.</p>
    <?php } else { ?>

        <p>Er zijn <?php echo count($users); ?> gebruikers die niet als verzekerd zijn gemarkeerd.</p>

        <h3>Overzicht</h3>
        <table class="table">
            <thead>
            <tr>
                <th>Gebruikersnaam</th>
                <th>Naam</th>
                <th>E-mail</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u) { ?>
                <tr>
                    <td><?php echo $u['username']; ?></td>
                    <td><?php echo $u['first_name'] . ' ' . $u['last_name']; ?></td>
                    <td><?php echo $u['email']; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <h3>E-maillijst</h3>
        <p>Je kunt deze lijst kopiëren en plakken in je e-mailprogramma:</p>
        <div style="border:1px solid #ccc; padding:0.5rem; word-wrap:break-word;">
            <?php echo $email_list; ?>
        </div>

        <h3>Direct mailen</h3>
        <?php
        
        $subject_encoded = str_replace(' ', '%20', $subject);
        $body_encoded = str_replace("\n", '%0D%0A', $body);
        $body_encoded = str_replace(' ', '%20', $body_encoded);
        ?>
        <p>
            <a class="btn"
               href="mailto:?bcc=<?php echo $email_list; ?>&subject=<?php echo $subject_encoded; ?>&body=<?php echo $body_encoded; ?>">
                Open e-mailprogramma met BCC
            </a>
        </p>

    <?php } ?>
</div>
<?php
include 'footer.php';
