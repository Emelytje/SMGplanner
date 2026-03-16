<?php
session_start();

$db = mysqli_connect(
    'sql100.infinityfree.com',   // hostname van InfinityFree
    'if0_40646646',              // username
    '3ei15DdrKEF',               // password
    'if0_40646646_manege_db'     // volledige database naam
);

if (!$db) {
    die("Databaseverbinding mislukt: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($db, "utf8mb4");
?>