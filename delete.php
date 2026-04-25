<?php
session_start();
include "db.php";

$id = (int)($_GET['id'] ?? 0);
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

if ($id > 0) {
    mysqli_query($conn, "DELETE FROM students WHERE id=$id");
}

header("Location: index.php");
exit;
?>