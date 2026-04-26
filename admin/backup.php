<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$message = '';
$error = '';

// Handle backup request
if (isset($_POST['backup'])) {
    try {
        $backupFile = createDatabaseBackup();
        $message = "Database backup created successfully: " . basename($backupFile);
        
        // Log the backup activity
        $auth->logActivity($auth->getUserId(), 'database_backup', 'system', null);
        
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}

// Handle restore request
if (isset($_POST['restore']) && isset($_FILES['backup_file'])) {
    try {
        $uploadedFile = $_FILES['backup_file'];
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed");
        }
        
        $tempFile = $uploadedFile['tmp_name'];
        restoreDatabaseBackup($tempFile);
        $message = "Database restored successfully";
        
        // Log the restore activity
        $auth->logActivity($auth->getUserId(), 'database_restore', 'system', null);
        
    } catch (Exception $e) {
        $error = "Restore failed: " . $e->getMessage();
    }
}

/**
 * Create database backup
 */
function createDatabaseBackup() {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $backupDir = __DIR__ . '/../assets/backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $handle = fopen($backupFile, 'w');
    
    if (!$handle) {
        throw new Exception("Cannot create backup file");
    }
    
    fwrite($handle, "-- Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Database: student_system\n\n");
    
    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        fwrite($handle, $createTable['Create Table'] . ";\n\n");
        
        // Get table data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            fwrite($handle, "-- Dumping data for table `$table`\n");
            
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $pdo->quote($value);
                }, $row);
                
                fwrite($handle, "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n");
            }
            
            fwrite($handle, "\n");
        }
    }
    
    fclose($handle);
    return $backupFile;
}

/**
 * Restore database from backup
 */
function restoreDatabaseBackup($backupFile) {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Read backup file
    $sql = file_get_contents($backupFile);
    
    if ($sql === false) {
        throw new Exception("Cannot read backup file");
    }
    
    // Split SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $pdo->beginTransaction();
    
    try {
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $pdo->exec($statement);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

// Get existing backup files
$backupDir = __DIR__ . '/../assets/backups/';
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filePath = $backupDir . $file;
            $backupFiles[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'date' => date('Y-m-d H:i:s', filemtime($filePath))
            ];
        }
    }
    rsort($backupFiles); // Sort by date (newest first)
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup | BDU Student System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sidebar-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .sidebar {
            background: var(--card-bg);
            border-right: 1px solid var(--border);
            padding: 1.5rem;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text);
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--primary);
            color: white;
        }
        
        .main-content {
            padding: 2rem;
            background: var(--bg);
        }
        
        .backup-section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .backup-section h2 {
            margin: 0 0 1rem 0;
            color: var(--text);
        }
        
        .backup-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .backup-files {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .file-info h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text);
        }
        
        .file-info p {
            margin: 0.25rem 0;
            color: var(--muted);
            font-size: 0.875rem;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div class="logo-circle" style="margin: 0 auto 1rem;">BDU</div>
                <h3 style="margin: 0; color: var(--text);">Admin Panel</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="users.php">👥 User Management</a></li>
                    <li><a href="students.php">🎓 Students</a></li>
                    <li><a href="teachers.php">👨‍🏫 Teachers</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="#" class="active">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Database Backup</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Create and restore database backups</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Create Backup -->
            <section class="backup-section">
                <h2>💾 Create Backup</h2>
                <p style="color: var(--muted); margin-bottom: 1rem;">
                    Create a complete backup of the database including all tables and data.
                </p>
                
                <form method="POST" class="backup-form">
                    <button type="submit" name="backup" class="btn primary">
                        🔄 Create New Backup
                    </button>
                </form>
            </section>

            <!-- Restore Backup -->
            <section class="backup-section">
                <h2>📂 Restore Backup</h2>
                <p style="color: var(--muted); margin-bottom: 1rem;">
                    <strong>⚠️ Warning:</strong> Restoring a backup will overwrite all current data. This action cannot be undone.
                </p>
                
                <form method="POST" enctype="multipart/form-data" class="backup-form">
                    <div class="field">
                        <label for="backup_file">Select Backup File</label>
                        <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                    </div>
                    <button type="submit" name="restore" class="btn danger" onclick="return confirm('Are you sure you want to restore this backup? This will overwrite all current data.')">
                        🔄 Restore Backup
                    </button>
                </form>
            </section>

            <!-- Existing Backups -->
            <section class="backup-files">
                <h2>📋 Existing Backups</h2>
                
                <?php if (empty($backupFiles)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No backup files found</p>
                <?php else: ?>
                    <?php foreach ($backupFiles as $file): ?>
                        <div class="file-item">
                            <div class="file-info">
                                <h4><?php echo htmlspecialchars($file['name']); ?></h4>
                                <p>Size: <?php echo number_format($file['size'] / 1024 / 1024, 2); ?> MB</p>
                                <p>Created: <?php echo htmlspecialchars($file['date']); ?></p>
                            </div>
                            <div class="file-actions">
                                <a href="../assets/backups/<?php echo urlencode($file['name']); ?>" 
                                   class="btn secondary" 
                                   download>
                                    📥 Download
                                </a>
                                <button onclick="confirmDelete('<?php echo htmlspecialchars($file['name']); ?>')" 
                                        class="btn danger sm">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        function confirmDelete(filename) {
            if (confirm('Are you sure you want to delete this backup file?')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="delete_file" value="' + filename + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Handle file deletion
        <?php if (isset($_POST['delete_file'])): ?>
            <?php
            $fileToDelete = $backupDir . $_POST['delete_file'];
            if (file_exists($fileToDelete)) {
                unlink($fileToDelete);
                echo "<script>alert('Backup file deleted successfully'); location.reload();</script>";
            }
            ?>
        <?php endif; ?>
    </script>
</body>
</html>
