<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_track'])) {
        $name = $_POST['name'];
        $sort_order = (int)$_POST['sort_order'];
        mysqli_query($db, "INSERT INTO tracks (name, sort_order) VALUES ('$name', $sort_order)");
    } elseif (isset($_POST['edit_track'])) {
        $id = (int)$_POST['id'];
        $name = $_POST['name'];
        $sort_order = (int)$_POST['sort_order'];
        mysqli_query($db, "UPDATE tracks SET name = '$name', sort_order = $sort_order WHERE id = $id");
    } elseif (isset($_POST['delete_track'])) {
        $id = (int)$_POST['id'];
        mysqli_query($db, "DELETE FROM tracks WHERE id = $id");
    }
    header('Location: admin_tracks.php');
    exit;
}

$tracks = array();
$result = mysqli_query($db, "SELECT * FROM tracks ORDER BY sort_order, name");
while ($row = mysqli_fetch_assoc($result)) {
    $tracks[] = $row;
}
?>
<div class="card">
    <h2>Beheer Pistes</h2>

    <h3>Nieuwe piste toevoegen</h3>
    <form method="post">
        <label>Naam</label>
        <input type="text" name="name" required>
        <label>Sorteervolgorde</label>
        <input type="number" name="sort_order" value="0">
        <button type="submit" name="add_track">Toevoegen</button>
    </form>

    <h3>Bestaande pistes</h3>
    <table class="table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Naam</th>
            <th>Sorteervolgorde</th>
            <th>Acties</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tracks as $track) { ?>
            <tr>
                <td><?php echo $track['id']; ?></td>
                <td><?php echo $track['name']; ?></td>
                <td><?php echo $track['sort_order']; ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $track['id']; ?>">
                        <input type="text" name="name" value="<?php echo $track['name']; ?>" required>
                        <input type="number" name="sort_order" value="<?php echo $track['sort_order']; ?>">
                        <button type="submit" name="edit_track">Bewerken</button>
                    </form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze piste wilt verwijderen?');">
                        <input type="hidden" name="id" value="<?php echo $track['id']; ?>">
                        <button type="submit" name="delete_track" style="background:#ef4444;">Verwijderen</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
<?php
include 'footer.php';
?>