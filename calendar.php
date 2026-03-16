<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'header.php';

$user_id = (int)$_SESSION['user_id'];
$role    = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

$reservations = array();

$sql = "SELECT r.*,
               r.approval_status,
               t.name AS track_name,
               u.username AS user_name,
               i.username AS instructor_name
        FROM reservations r
        LEFT JOIN tracks t ON r.track_id = t.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN users i ON r.instructor_id = i.id
        WHERE r.status = 'active'
          AND (
                r.approval_status = 'approved'
                OR (
                    r.approval_status = 'pending'
                    AND (
                        r.user_id = $user_id
                        OR r.instructor_id = $user_id
                        OR '$role' = 'admin'
                    )
                )
          )
        ORDER BY r.start_time";

$result = mysqli_query($db, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $reservations[] = $row;
    }
}

$blocked = array();

$sql2 = "SELECT b.*, t.name AS track_name
         FROM blocked_times b
         LEFT JOIN tracks t ON b.track_id = t.id
         ORDER BY b.start_time";

$result2 = mysqli_query($db, $sql2);
if ($result2) {
    while ($row = mysqli_fetch_assoc($result2)) {
        $blocked[] = $row;
    }
}

$events = array();

for ($i = 0; $i < count($reservations); $i++) {
    $r = $reservations[$i];

    if ($r['type'] === 'lesson') {
        if ($r['instructor_name'] !== null && $r['instructor_name'] !== '') {
            $title = 'Les ' . $r['instructor_name'] . ' - ' . $r['track_name'];
        } else {
            $title = 'Les - ' . $r['track_name'];
        }
    } else {
        $title = 'Piste - ' . $r['track_name'];
    }

    $color = '#3b82f6';
    if ($r['type'] === 'lesson') {
        $color = '#ec4899';
    }
    if (isset($r['approval_status']) && $r['approval_status'] === 'pending') {
        $color = '#f59e0b';
        $title = 'In behandeling - ' . $title;
    }

    $notes = $r['notes'];
    if ($notes === null) { $notes = ''; }

    $rider = $r['rider_name'];
    if ($rider === null) { $rider = ''; }

    $events[] = array(
        'id' => 'r' . $r['id'],
        'title' => $title,
        'start' => $r['start_time'],
        'end'   => $r['end_time'],
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'extendedProps'   => array(
            'kind'            => 'reservation',
            'type'            => $r['type'],
            'approval_status' => $r['approval_status'],
            'notes'           => $notes,
            'rider_name'      => $rider,
            'user_name'       => $r['user_name'],
            'track_id'        => $r['track_id'],
            'user_id'         => $r['user_id'],
            'instructor_id'   => $r['instructor_id'],
        ),
    );
}

for ($i = 0; $i < count($blocked); $i++) {
    $b = $blocked[$i];

    $title = 'Geblokkeerd - ' . $b['track_name'];
    $color = '#ef4444';

    $reason = '';
    if (isset($b['reason']) && $b['reason'] !== null) {
        $reason = $b['reason'];
    }

    $events[] = array(
        'id' => 'b' . $b['id'],
        'title' => $title,
        'start' => $b['start_time'],
        'end'   => $b['end_time'],
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'extendedProps'   => array(
            'kind'  => 'blocked',
            'notes' => $reason,
        ),
    );
}

$json_events = json_encode($events);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>

<div class="card" style="background: transparent; box-shadow: none;">
    <h2>Kalender</h2>
    <p>
        <span style="background:#3b82f6;color:#fff;padding:2px 6px;border-radius:4px;">Piste (blauw)</span>
        <span style="background:#ec4899;color:#fff;padding:2px 6px;border-radius:4px;">Les (roze)</span>
        <span style="background:#ef4444;color:#fff;padding:2px 6px;border-radius:4px;">Geblokkeerd (rood)</span>
    </p>
</div>

<div class="calendar-layout">

    <div class="calendar-form card">
        <h3>Nieuwe / aangepaste reservering</h3>
        <p style="font-size:0.9rem;">Tip: klik of sleep in de kalender rechts. De tijden worden hier automatisch ingevuld (standaard +30 minuten).</p>

        <div id="form_message" style="display:none;padding:0.5rem;margin-bottom:1rem;border-radius:4px;"></div>

        <form id="reservationForm" method="post" action="save_reservation.php">
            <input type="hidden" name="reservation_id" id="reservation_id" value="0">

            <label>Type</label>
            <select name="type" id="form_type">
                <option value="piste">Piste</option>
                <option value="lesson">Les</option>
                <option value="blocked">Geblokkeerd (alleen admin)</option>
            </select>

            <label>Piste</label>
            <select name="track_id" id="form_track">
                <?php
                $tracks_res = mysqli_query($db, "SELECT id, name FROM tracks ORDER BY name");
                while ($t = mysqli_fetch_assoc($tracks_res)) {
                    echo '<option value="'.$t['id'].'">'.$t['name'].'</option>';
                }
                ?>
            </select>

            <label>Startdatum-tijd</label>
            <input type="datetime-local" name="start" id="form_start">

            <label>Einddatum-tijd</label>
            <input type="datetime-local" name="end" id="form_end">

            <label>Naam ruiter (als je voor iemand anders boekt)</label>
            <input type="text" name="rider_name" id="form_rider">

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
                <label>Gebruiker (voor wie je boekt)</label>
                <select name="user_id" id="form_user">
                    <?php
                    $user_res = mysqli_query($db, "SELECT id, username, first_name, last_name FROM users ORDER BY username");
                    while ($u = mysqli_fetch_assoc($user_res)) {
                        $selected = ($u['id'] == $user_id) ? ' selected' : '';
                        $name = $u['username'];
                        if ($u['first_name'] || $u['last_name']) {
                            $name .= ' (' . trim($u['first_name'] . ' ' . $u['last_name']) . ')';
                        }
                        echo '<option value="'.$u['id'].'"'.$selected.'>'.$name.'</option>';
                    }
                    ?>
                </select>
            <?php } else { ?>
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <?php } ?>

            <label>Notitie</label>
            <input type="text" name="notes" id="form_notes">

            <div id="instructor_wrap" style="display:none;">
                <label>Lesgever (verplicht bij les)</label>
                <select name="instructor_id" id="form_instructor">
                    <option value="0">Kies lesgever</option>
                    <?php
                    $inst_res = mysqli_query($db, "
                        SELECT DISTINCT u.id, u.username
                        FROM users u
                        INNER JOIN instructor_availability ia ON ia.instructor_id = u.id
                        WHERE u.role IN ('instructor','admin')
                        ORDER BY u.username
                    ");
                    while ($u = mysqli_fetch_assoc($inst_res)) {
                        echo '<option value="'.$u['id'].'">'.$u['username'].'</option>';
                    }
                    ?>
                </select>
            </div>

            <button type="submit" style="margin-top:1rem;">Opslaan</button>

        </form>
    </div>

    <div class="calendar-view">
        <div id="calendar"></div>
    </div>

</div>

<script>
    var events = <?php echo $json_events ? $json_events : '[]'; ?>;
    var currentUserId   = <?php echo (int)$user_id; ?>;
    var currentUserRole = "<?php echo $role; ?>";

    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');

        if (typeof FullCalendar === 'undefined') {
            calendarEl.innerHTML = 'FullCalendar kon niet geladen worden. Controleer internet / adblock.';
            return;
        }

        // --- Instructor visibility toggle ---
        function toggleInstructorUI() {
            var typeSel = document.getElementById('form_type');
            var wrap    = document.getElementById('instructor_wrap');
            var instr   = document.getElementById('form_instructor');
            if (!typeSel || !wrap || !instr) return;

            if (typeSel.value === 'lesson') {
                wrap.style.display = 'block';
                instr.required = true;
            } else {
                wrap.style.display = 'none';
                instr.required = false;
                instr.value = '0';
            }
        }

        var typeSel = document.getElementById('form_type');
        if (typeSel) {
            typeSel.addEventListener('change', toggleInstructorUI);
        }
        toggleInstructorUI();

        // --- Permission check ---
        function canEditEvent(extra) {
            if (!extra) return false;

            if (extra.kind === 'reservation') {
                var isAdmin      = (currentUserRole === 'admin');
                var isOwner      = (extra.user_id == currentUserId);
                var isInstructor = (extra.instructor_id && extra.instructor_id == currentUserId);

                if (extra.type === 'lesson') {
                    return isAdmin;
                }

                return isOwner || isInstructor || isAdmin;
            } else if (extra.kind === 'blocked') {
                return (currentUserRole === 'admin');
            }

            return false;
        }

        function pad2(n) {
            return n.toString().padStart(2, '0');
        }

        function toLocalInputValue(dateObj) {
            var year  = dateObj.getFullYear();
            var month = pad2(dateObj.getMonth() + 1);
            var day   = pad2(dateObj.getDate());
            var hour  = pad2(dateObj.getHours());
            var min   = pad2(dateObj.getMinutes());
            return year + '-' + month + '-' + day + 'T' + hour + ':' + min;
        }

        function toMysqlDateTime(dateObj) {
            var year  = dateObj.getFullYear();
            var month = pad2(dateObj.getMonth() + 1);
            var day   = pad2(dateObj.getDate());
            var hour  = pad2(dateObj.getHours());
            var min   = pad2(dateObj.getMinutes());
            var sec   = pad2(dateObj.getSeconds());
            return year + '-' + month + '-' + day + ' ' + hour + ':' + min + ':' + sec;
        }

        function resetFormForNew() {
            var idInput    = document.getElementById('reservation_id');
            var typeInput  = document.getElementById('form_type');
            var startInput = document.getElementById('form_start');
            var endInput   = document.getElementById('form_end');
            var riderInput = document.getElementById('form_rider');
            var notesInput = document.getElementById('form_notes');
            var trackInput = document.getElementById('form_track');
            var instrInput = document.getElementById('form_instructor');

            if (idInput)    idInput.value = '0';
            if (typeInput)  typeInput.value = 'piste';
            if (startInput) startInput.value = '';
            if (endInput)   endInput.value = '';
            if (riderInput) riderInput.value = '';
            if (notesInput) notesInput.value = '';
            if (trackInput && trackInput.options.length > 0) {
                trackInput.selectedIndex = 0;
            }
            if (instrInput) instrInput.value = '0';
            toggleInstructorUI();
        }

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'nl',
            firstDay: 1,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            editable: true,
            selectable: true,
            selectOverlap: true,
            events: events,
            longPressDelay: 300,
            eventDidMount: function(info) {
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'timeGridWeek,timeGridDay'
            },
            windowResizeDelay: 100,
            select: function(info) {
                var start = info.start;
                var end   = info.end;

                if (!end) {
                    end = new Date(start.getTime() + 30 * 60000);
                }

                var startStr = toLocalInputValue(start);
                var endStr   = toLocalInputValue(end);

                resetFormForNew();

                var startInput = document.getElementById('form_start');
                var endInput   = document.getElementById('form_end');

                if (startInput && endInput) {
                    startInput.value = startStr;
                    endInput.value   = endStr;
                    startInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                calendar.unselect();
            },
            eventDrop: function(info) {
                var extra   = info.event.extendedProps;
                var canEdit = canEditEvent(extra);

                if (!canEdit) {
                    alert('Je mag deze reservering niet verplaatsen. Alleen admin en lesgevers kunnen lessen verplaatsen.');
                    info.revert();
                    return;
                }

                sendUpdate(info.event);
            },
            eventResize: function(info) {
                var extra   = info.event.extendedProps;
                var canEdit = canEditEvent(extra);

                if (!canEdit) {
                    alert('Je mag deze reservering niet verlengen. Alleen admin en lesgevers kunnen lessen verlengen.');
                    info.revert();
                    return;
                }

                sendUpdate(info.event);
            },
            eventClick: function(info) {
                var e     = info.event;
                var extra = e.extendedProps;
                var msg   = '';

                msg += 'Blok: ' + e.title + '\n';
                msg += 'Van: ' + e.start.toLocaleString() + '\n';
                if (e.end) {
                    msg += 'Tot: ' + e.end.toLocaleString() + '\n';
                }
                if (extra && extra.rider_name) {
                    msg += 'Ruiter: ' + extra.rider_name + '\n';
                }
                if (extra && extra.user_name) {
                    msg += 'Geboekt door: ' + extra.user_name + '\n';
                }
                if (extra && extra.notes) {
                    msg += 'Notitie: ' + extra.notes + '\n';
                }
                if (extra && extra.kind === 'blocked') {
                    msg += '(Geblokkeerde tijd)\n';
                }

                var canEdit = canEditEvent(extra);

                var canDelete = false;
                if (extra.kind === 'reservation' && extra.type === 'lesson') {
                    canDelete = (currentUserRole === 'admin');
                } else if (extra.kind === 'reservation' && extra.type === 'piste') {
                    canDelete = (currentUserRole === 'admin' || (extra.user_id == currentUserId && currentUserRole !== 'admin'));
                } else if (extra.kind === 'blocked') {
                    canDelete = (currentUserRole === 'admin');
                }

                var editMessage = '';
                if (extra.kind === 'reservation' && extra.type === 'lesson') {
                    if (currentUserRole === 'admin') {
                        editMessage = '\n\nJe kunt dit blok aanpassen via de kalender (slepen) of het formulier.';
                        if (canDelete) editMessage += '\n[D] Delete';
                    } else {
                        editMessage = '\n\nAlleen admin kan lessen aanpassen.';
                    }
                } else if (extra.kind === 'reservation' && extra.type === 'piste') {
                    if (canEdit) {
                        editMessage = '\n\nJe kunt dit blok aanpassen via de kalender (slepen) of het formulier.';
                        if (canDelete) editMessage += '\n[D] Delete';
                    } else {
                        editMessage = '\n\nJe kunt deze reservering niet aanpassen (niet jouw reservering).';
                    }
                } else if (extra.kind === 'blocked') {
                    if (currentUserRole === 'admin') {
                        editMessage = '\n\nJe kunt deze blokkade aanpassen via de kalender (slepen).';
                        if (canDelete) editMessage += '\n[D] Delete';
                    } else {
                        editMessage = '\n\nAlleen admin kan blokkades aanpassen.';
                    }
                }

                if (canDelete) {
                    var shouldDelete = confirm(msg + editMessage + '\n\nWil je dit blok verwijderen?');
                    if (shouldDelete) {
                        deleteEvent(e.id);
                        return;
                    }
                }

                alert(msg + editMessage);

                if (extra.kind === 'reservation' && canEdit) {
                    var idInput    = document.getElementById('reservation_id');
                    var typeInput  = document.getElementById('form_type');
                    var startInput = document.getElementById('form_start');
                    var endInput   = document.getElementById('form_end');
                    var riderInput = document.getElementById('form_rider');
                    var notesInput = document.getElementById('form_notes');
                    var trackInput = document.getElementById('form_track');
                    var instrInput = document.getElementById('form_instructor');

                    if (idInput)    idInput.value = e.id.substring(1);
                    if (typeInput && extra.type) typeInput.value = extra.type;
                    if (startInput) startInput.value = toLocalInputValue(e.start);
                    if (endInput) {
                        endInput.value = e.end ? toLocalInputValue(e.end) : toLocalInputValue(e.start);
                    }
                    if (riderInput) riderInput.value = extra.rider_name || '';
                    if (notesInput) notesInput.value = extra.notes || '';
                    if (trackInput && extra.track_id) trackInput.value = extra.track_id;
                    if (instrInput && extra.instructor_id) instrInput.value = extra.instructor_id;

                    toggleInstructorUI();

                    if (startInput) {
                        startInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });

        calendar.render();

        var reservationForm = document.getElementById('reservationForm');
        if (reservationForm) {
            reservationForm.addEventListener('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(reservationForm);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'save_reservation.php', true);

                xhr.onload = function() {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        var messageDiv = document.getElementById('form_message');

                        if (response.success) {
                            messageDiv.style.display = 'block';
                            messageDiv.style.backgroundColor = '#d1fae5';
                            messageDiv.style.color = '#065f46';
                            messageDiv.textContent = response.message;

                            setTimeout(function() {
                                reservationForm.reset();
                                resetFormForNew();
                                location.reload();
                            }, 1000);
                        } else {
                            messageDiv.style.display = 'block';
                            messageDiv.style.backgroundColor = '#fee2e2';
                            messageDiv.style.color = '#991b1b';
                            messageDiv.textContent = response.message || 'Onbekende fout';
                        }
                    } catch (e) {
                        var messageDiv = document.getElementById('form_message');
                        messageDiv.style.display = 'block';
                        messageDiv.style.backgroundColor = '#fee2e2';
                        messageDiv.style.color = '#991b1b';
                        messageDiv.textContent = 'Parse fout: ' + e.message + ' | Response: ' + xhr.responseText.substring(0, 200);
                        console.error('Form submission error:', xhr.responseText);
                    }
                };

                xhr.onerror = function() {
                    var messageDiv = document.getElementById('form_message');
                    messageDiv.style.display = 'block';
                    messageDiv.style.backgroundColor = '#fee2e2';
                    messageDiv.style.color = '#991b1b';
                    messageDiv.textContent = 'Netwerkfout bij versturen van formulier';
                };

                xhr.send(formData);
            });
        }

        function sendUpdate(event) {
            var start = toMysqlDateTime(event.start);
            var end   = event.end ? toMysqlDateTime(event.end) : start;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_reservation.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            var body = 'id=' + encodeURIComponent(event.id) +
                       '&start=' + encodeURIComponent(start) +
                       '&end=' + encodeURIComponent(end);

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
                        event.revert();
                    }
                } catch (e) {
                    alert('Fout bij verplaatsen reservering');
                    event.revert();
                }
            };

            xhr.onerror = function() {
                alert('Fout bij communicatie');
                event.revert();
            };

            xhr.send(body);
        }

        function deleteEvent(eventId) {
            var kind = eventId[0];
            var id   = eventId.substring(1);

            if (kind === 'r') {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'delete_reservation.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                var body = 'reservation_id=' + encodeURIComponent(id);

                xhr.onload = function() {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        var messageDiv = document.getElementById('form_message');

                        if (response.success) {
                            messageDiv.style.display = 'block';
                            messageDiv.style.backgroundColor = '#d1fae5';
                            messageDiv.style.color = '#065f46';
                            messageDiv.textContent = response.message;

                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            messageDiv.style.display = 'block';
                            messageDiv.style.backgroundColor = '#fee2e2';
                            messageDiv.style.color = '#991b1b';
                            messageDiv.textContent = response.message;
                        }
                    } catch (e) {
                        alert('Fout bij verwijderen');
                    }
                };

                xhr.send(body);
            }
        }

    }); 
</script>

<?php
include 'footer.php';
?>