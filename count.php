<?php
include "db.php";

$res = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
$data = mysqli_fetch_assoc($res);

echo $data['total'];
?>