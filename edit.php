<?php
include "db.php";

$id = $_GET['id'];
$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id=$id"));

if(isset($_POST['update'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $dept = $_POST['department'];

    mysqli_query($conn, "UPDATE students SET name='$name', email='$email', department='$dept' WHERE id=$id");

    header("Location: index.php");
}
?>

<link rel="stylesheet" href="css/style.css">

<div class="center-box">
<form method="POST">
    <h2>Edit Student</h2>
    <input name="name" value="<?= $data['name'] ?>"><br>
    <input name="email" value="<?= $data['email'] ?>"><br>
    <input name="department" value="<?= $data['department'] ?>"><br>
    <button name="update">Update</button>
</form>
</div>