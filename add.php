<?php
include "db.php";

if(isset($_POST['submit'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $dept = $_POST['department'];

    $photo = $_FILES['photo']['name'];
    $tmp = $_FILES['photo']['tmp_name'];

    move_uploaded_file($tmp, "uploads/".$photo);

    mysqli_query($conn, "INSERT INTO students(name,email,department,photo)
    VALUES('$name','$email','$dept','$photo')");

    header("Location: index.php");
}
?>

<link rel="stylesheet" href="css/style.css">

<div class="center-box">
<form method="POST" enctype="multipart/form-data">
    <h2>Add Student</h2>
    <input name="name" placeholder="Name"><br><br>
    <input name="email" placeholder="Email"><br><br>
    <input name="department" placeholder="Department"><br><br>
    <input type="file" name="photo"><br><br>
    <button name="submit">Add</button>
</form>
</div>