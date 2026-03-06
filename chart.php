<?php
include "db.php";

$query = mysqli_query($conn, "
SELECT department, COUNT(*) as total 
FROM students 
GROUP BY department
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode($data);
?>