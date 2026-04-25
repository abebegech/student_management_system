<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$dataRes = mysqli_query($conn, "SELECT * FROM students WHERE id=$id");
$data = $dataRes ? mysqli_fetch_assoc($dataRes) : null;
if (!$data) {
    header("Location: index.php");
    exit;
}

$error = "";

if(isset($_POST['update'])){
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $dept = mysqli_real_escape_string($conn, $_POST['department'] ?? '');

    $photo = $_FILES['photo']['name'] ?? '';
    $tmp = $_FILES['photo']['tmp_name'] ?? '';

    if (empty($name) || empty($email) || empty($dept)) {
        $error = "Please fill in name, email, and department.";
    } else {
        if (!empty($photo) && !empty($tmp)) {
            if (!is_dir("uploads")) {
                mkdir("uploads", 0777, true);
            }

            $safePhoto = preg_replace('/[^a-zA-Z0-9._-]/', '_', $photo);
            $target = "uploads/" . time() . "_" . $safePhoto;

            if (!move_uploaded_file($tmp, $target)) {
                $error = "Photo upload failed. Please try again.";
            } else {
                $photoDb = basename($target);
                mysqli_query($conn, "UPDATE students SET name='$name', email='$email', department='$dept', photo='$photoDb' WHERE id=$id");
            }
        } else {
            mysqli_query($conn, "UPDATE students SET name='$name', email='$email', department='$dept' WHERE id=$id");
        }

        if (empty($error)) {
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
    <title>Edit Student | BDU Student System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/script.js" defer></script>
</head>
<body>
    <div class="layout">
        <header class="header animate-on-load">
            <div class="header-left">
                <div class="logo-circle">BDU</div>
                <div>
                    <h1 class="title">Edit Student</h1>
                    <p class="subtitle">Update student information and optionally replace the photo.</p>
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
                    <p class="card-subtitle">Make changes then save.</p>
                </div>

                <?php if (!empty($error)) { ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>

                <div class="profile-row">
                    <div class="avatar">
                        <img src="<?php echo 'uploads/' . htmlspecialchars($data['photo']); ?>" alt="Student photo">
                    </div>
                    <div class="profile-meta">
                        <div class="profile-name"><?php echo htmlspecialchars($data['name']); ?></div>
                        <div class="profile-sub"><?php echo htmlspecialchars($data['email']); ?></div>
                        <div class="profile-sub"><?php echo htmlspecialchars($data['department']); ?></div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="form">
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Full name</label>
                            <input id="name" name="name" value="<?php echo htmlspecialchars($data['name']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="department">Department</label>
                            <input id="department" name="department" value="<?php echo htmlspecialchars($data['department']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="photo">Replace photo (optional)</label>
                            <input id="photo" type="file" name="photo" accept="image/*">
                            <div class="hint">Leave empty to keep the current photo.</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn">Cancel</a>
                        <button class="btn primary" name="update" type="submit">Save Changes</button>
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