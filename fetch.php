<?php
session_start();
include "db.php";
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$start = ($page - 1) * $limit;
$query = mysqli_query($conn, "SELECT * FROM students ORDER BY id DESC LIMIT $start, $limit");

while($row = mysqli_fetch_assoc($query)){
?>
<tr>
<td><?php echo (int)$row['id']; ?></td>
<td><img src="uploads/<?php echo htmlspecialchars($row['photo']); ?>" alt="Student photo"></td>
<td><?php echo htmlspecialchars($row['name']); ?></td>
<td><?php echo htmlspecialchars($row['email']); ?></td>
<td><?php echo htmlspecialchars($row['department']); ?></td>
<td>
    <div class="row-actions">
        <a class="btn sm ghost" href="edit.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
        <a class="btn sm link-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirmDelete()">Delete</a>
    </div>
</td>
</tr>
<?php } ?>