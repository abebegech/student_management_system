<?php
include "db.php";
$limit = 5;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;
$query = mysqli_query($conn, "SELECT * FROM students ORDER BY id DESC");

while($row = mysqli_fetch_assoc($query)){
?>
<tr>
<td><?= $row['id'] ?></td>
<td><img src="uploads/<?= $row['photo'] ?>"></td>
<td><?= $row['name'] ?></td>
<td><?= $row['email'] ?></td>
<td><?= $row['department'] ?></td>
<td>
<a href="edit.php?id=<?= $row['id'] ?>">Edit</a>
<a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirmDelete()">Delete</a>
</td>
</tr>
<?php } ?>