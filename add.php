<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$error = "";

if(isset($_POST['submit'])){
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $dept = mysqli_real_escape_string($conn, $_POST['department'] ?? '');

    $photo = $_FILES['photo']['name'] ?? '';
    $tmp = $_FILES['photo']['tmp_name'] ?? '';

    if (empty($name) || empty($email) || empty($dept) || empty($photo) || empty($tmp)) {
        $error = "Please fill in all fields and upload a photo.";
    } else {
        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        $safePhoto = preg_replace('/[^a-zA-Z0-9._-]/', '_', $photo);
        $target = "uploads/" . time() . "_" . $safePhoto;

        if (!move_uploaded_file($tmp, $target)) {
            $error = "Photo upload failed. Please try again.";
        } else {
            $photoDb = basename($target);

            mysqli_query($conn, "INSERT INTO students(name,email,department,photo)
            VALUES('$name','$email','$dept','$photoDb')");

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student | BDU Student System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/script.js" defer></script>
</head>
<body>
    <div class="layout">
        <header class="header animate-on-load">
            <div class="header-left">
                <div class="logo-circle">BDU</div>
                <div>
                    <h1 class="title">Add Student</h1>
                    <p class="subtitle">Create a new student record with photo and department.</p>
                </div>
            </div>
            <div class="header-right">
                <a href="index.php" class="btn">Back</a>
            </div>
        </header>

        <main class="main">
            <section class="card animate-on-load form-card">
                <div class="card-header">
                    <h2>Student Details</h2>
                    <p class="card-subtitle">All fields are required.</p>
                </div>

                <?php if (!empty($error)) { ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>

                <form method="POST" enctype="multipart/form-data" class="form">
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Full name</label>
                            <input id="name" name="name" placeholder="e.g. Abebe Kebede" required>
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" placeholder="e.g. abebe@example.com" required>
                        </div>

                        <div class="field">
                            <label for="department">Department</label>
                            <input id="department" name="department" placeholder="e.g. Computer Science" required>
                        </div>

                        <div class="field">
                            <label for="photo">Photo</label>
                            <input id="photo" type="file" name="photo" accept="image/*" required>
                            <div class="hint">JPG/PNG recommended. Clear face photo looks best.</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn">Cancel</a>
                        <button class="btn primary" name="submit" type="submit">Save Student</button>
                    </div>
                </form>
            </section>
        </main>

        <footer class="footer">
            <span>© <?php echo date('Y'); ?> BDU Student System.</span>
        </footer>
    </div>
</body>
</html>