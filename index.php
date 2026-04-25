<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BDU Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/script.js" defer></script>
</head>
<body>
    <div class="layout">
        <header class="header animate-on-load">
            <div class="header-left">
                <div class="logo-circle">BDU</div>
                <div>
                    <h1 class="title">Student Management Dashboard</h1>
                    <p class="subtitle">Monitor students, departments, and system activity in real time.</p>
                </div>
            </div>
            <div class="header-right">
                <span class="badge">Admin Panel</span>
            </div>
        </header>

        <main class="main">
            <section class="toolbar animate-on-load">
                <div class="toolbar-left">
                    <input type="text" id="search" class="search-input" placeholder="Search student by name, email, or ID...">
                </div>
                <div class="toolbar-right">
                    <a href="add.php" class="btn primary">+ Add Student</a>
                    <a href="logout.php" class="btn danger">Logout</a>
                </div>
            </section>

            <section class="stats-grid">
                <article class="card stat-card animate-on-load">
                    <div class="card-label">Total Students</div>
                    <div class="card-value" id="total">0</div>
                    <div class="card-footer">Live count from database</div>
                </article>
                <article class="card stat-card muted animate-on-load">
                    <div class="card-label">System Status</div>
                    <div class="card-value small">Online</div>
                    <div class="status-dot online"></div>
                </article>
                <article class="card stat-card muted animate-on-load">
                    <div class="card-label">Admin</div>
                    <div class="card-value small">You are logged in</div>
                </article>
            </section>

            <section class="content-grid">
                <article class="card chart-card animate-on-load">
                    <div class="card-header">
                        <h2>Students by Department</h2>
                        <p class="card-subtitle">Visual overview of student distribution.</p>
                    </div>
                    <canvas id="chart"></canvas>
                </article>

                <article class="card table-card animate-on-load">
                    <div class="card-header">
                        <h2>Students List</h2>
                        <p class="card-subtitle">Search, paginate, and manage student records.</p>
                    </div>
                    <div id="pagination" class="pagination"></div>
                    <div class="table-wrapper">
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
                    </div>
                </article>
            </section>
        </main>

        <footer class="footer">
            <span>© <?php echo date('Y'); ?> BDU Student System. All rights reserved.</span>
        </footer>
    </div>
</body>
</html>