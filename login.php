<?php
session_start();
include "db.php";

if(isset($_POST['login'])){
    $user = $_POST['username'];
    $pass = md5($_POST['password']);

    $res = mysqli_query($conn, "SELECT * FROM admin WHERE username='$user' AND password='$pass'");

    if(mysqli_num_rows($res) > 0){
        $_SESSION['admin'] = $user;
        header("Location: index.php");
    } else {
        echo "Wrong login!";
    }
}
?>

<link rel="stylesheet" href="css/style.css">

<div class="center-box">
<form method="POST">
    <h2>Admin Login</h2>
    <input name="username" placeholder="Username"><br><br>
    <input type="password" name="password" placeholder="Password"><br><br>
    <button name="login">Login</button>
</form>
</div>