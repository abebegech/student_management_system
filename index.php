<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><?php
session_start();
include "db.php";

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
}
?>

<link rel="stylesheet" href="css/style.css">
<script src="js/script.js" defer></script>

<h1>BDU Student Dashboard</h1>

<div class="top-bar">
    <input type="text" id="search" placeholder="Search student...">
    <a href="add.php" class="btn">+ Add Student</a>
    <a href="logout.php" class="btn logout">Logout</a>
</div>
<div class="card">
    <h3>Total Students</h3>
    <h1 id="total"></h1>
</div>

<canvas id="chart"></canvas>

<div id="pagination"></div>
<div class="dashboard">
    <div class="card">Students</div>
    <div class="card">System</div>
    <div class="card">Admin</div>
</div>

<table>
<thead>
<tr>
    <th>ID</th>
    <th>Photo</th>
    <th>Name</th>
    <th>Email</th>
    <th>Department</th>
    <th>Action</th>
</tr>
</thead>

<tbody id="table-data"></tbody>
</table>