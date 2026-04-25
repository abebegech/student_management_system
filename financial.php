<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('student');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get student information
$student = $db->fetch("
    SELECT s.*, u.first_name, u.last_name, d.name as department_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.user_id = :user_id
", ['user_id' => $userId]);

if (!$student) {
    header('Location: ../login.php');
    exit;
}

// Get financial records
$financial_records = $db->fetchAll("
    SELECT fr.*, d.name as department_name
    FROM financial_records fr
    JOIN students s ON fr.student_id = s.id
    JOIN departments d ON s.department_id = d.id
    WHERE fr.student_id = :student_id
    ORDER BY fr.due_date DESC
", ['student_id' => $student['id']]);

// Get financial summary
$financial_summary = $db->fetch("
    SELECT 
        SUM(amount) as total_amount,
        SUM(paid_amount) as paid_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_amount,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_full_amount
    FROM financial_records
    WHERE student_id = :student_id
", ['student_id' => $student['id']]);

// Calculate remaining balance
$remaining_balance = ($financial_summary['total_amount'] ?? 0) - ($financial_summary['paid_amount'] ?? 0);

// Group records by fee type
$records_by_type = [];
foreach ($financial_records as $record) {
    $records_by_type[$record['fee_type']][] = $record;
}

// Calculate totals by type
$totals_by_type = [];
foreach ($records_by_type as $type => $records) {
    $totals_by_type[$type] = [
        'total' => array_sum(array_column($records, 'amount')),
        'paid' => array_sum(array_column($records, 'paid_amount')),
        'remaining' => array_sum(array_column($records, 'amount')) - array_sum(array_column($records, 'paid_amount'))
    ];
}

// Handle payment processing (simulation)
if (isset($_POST['process_payment'])) {
    try {
        $recordId = $_POST['record_id'];
        $amount = floatval($_POST['amount']);
        
        $record = $db->fetch("SELECT * FROM financial_records WHERE id = :id AND student_id = :student_id", 
                              ['id' => $recordId, 'student_id' => $student['id']]);
        
        if (!$record) {
            throw new Exception("Financial record not found");
        }
        
        if ($amount <= 0 || $amount > ($record['amount'] - $record['paid_amount'])) {
            throw new Exception("Invalid payment amount");
        }
        
        $db->beginTransaction();
        
        // Update record
        $newPaidAmount = $record['paid_amount'] + $amount;
        $newStatus = $newPaidAmount >= $record['amount'] ? 'paid' : 'partial';
        
        $db->update("
            UPDATE financial_records 
            SET paid_amount = :paid_amount, status = :status, paid_date = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ", [
            'paid_amount' => $newPaidAmount,
            'status' => $newStatus,
            'id' => $recordId
        ]);
        
        $db->commit();
        
        $message = "Payment of $" . number_format($amount, 2) . " processed successfully!";
        
        // Redirect to refresh data
        header("Location: financial.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "Payment failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Financial | BDU Student System</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card h2 {
            margin: 0 0 1rem 0;
            color: var(--text);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-value.total { color: var(--primary); }
        .stat-value.paid { color: #10B981; }
        .stat-value.pending { color: #F59E0B; }
        .stat-value.overdue { color: #EF4444; }
        
        .stat-label {
            color: var(--muted);
            font-size: 0.875rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .table th {
            background: var(--primary);
            color: white;
        }
        
        .status-paid { color: #10B981; font-weight: bold; }
        .status-pending { color: #F59E0B; font-weight: bold; }
        .status-overdue { color: #EF4444; font-weight: bold; }
        .status-partial { color: #3B82F6; font-weight: bold; }
        
        .fee-type-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .fee-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .fee-type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .fee-type-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--text);
        }
        
        .fee-type-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .progress-bar {
            background: var(--border);
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: #10B981; color: white; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        
        .payment-form {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
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
                <div class="profile-avatar" style="margin: 0 auto 1rem; width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold;">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
                <h3 style="margin: 0; color: var(--text);"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    Student ID: <?php echo htmlspecialchars($student['student_id']); ?>
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="grades.php">📝 Grades</a></li>
                    <li><a href="attendance.php">📅 Attendance</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="#" class="active">💰 Financial</a></li>
                    <li><a href="documents.php">📄 Documents</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">My Financial</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">View and manage your financial obligations</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Financial Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value total">$<?php echo number_format($financial_summary['total_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value paid">$<?php echo number_format($financial_summary['paid_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Paid Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value pending">$<?php echo number_format($financial_summary['pending_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value overdue">$<?php echo number_format($financial_summary['overdue_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
            </div>

            <!-- Financial Chart -->
            <div class="card">
                <h2>📊 Financial Overview</h2>
                <div class="chart-container">
                    <canvas id="financialChart"></canvas>
                </div>
            </div>

            <!-- Fee Breakdown by Type -->
            <div class="card">
                <h2>💰 Fee Breakdown</h2>
                <?php if (empty($records_by_type)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No financial records found.</p>
                <?php else: ?>
                    <?php foreach ($records_by_type as $feeType => $records): ?>
                        <div class="fee-type-card">
                            <div class="fee-type-header">
                                <div>
                                    <div class="fee-type-title"><?php echo ucfirst($feeType); ?> Fees</div>
                                    <p style="color: var(--muted); margin: 0.25rem 0;">
                                        <?php echo count($records); ?> record<?php echo count($records) > 1 ? 's' : ''; ?>
                                    </p>
                                </div>
                                <div class="fee-type-amount">
                                    $<?php echo number_format($totals_by_type[$feeType]['total'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php 
                                    $percentage = $totals_by_type[$feeType]['total'] > 0 ? 
                                        ($totals_by_type[$feeType]['paid'] / $totals_by_type[$feeType]['total']) * 100 : 0;
                                    echo $percentage; ?>%;"></div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; color: var(--muted);">
                                <span>Paid: $<?php echo number_format($totals_by_type[$feeType]['paid'], 2); ?></span>
                                <span>Remaining: $<?php echo number_format($totals_by_type[$feeType]['remaining'], 2); ?></span>
                            </div>
                            
                            <div style="margin-top: 1rem;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Due Date</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($record['due_date'])); ?></td>
                                                <td>$<?php echo number_format($record['amount'], 2); ?></td>
                                                <td>$<?php echo number_format($record['paid_amount'], 2); ?></td>
                                                <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                                <td>
                                                    <?php if ($record['status'] !== 'paid'): ?>
                                                        <button class="btn btn-success btn-sm" onclick="showPaymentForm(<?php echo $record['id']; ?>, <?php echo $record['amount'] - $record['paid_amount']; ?>)">
                                                            Pay
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color: #10B981; font-size: 0.875rem;">✓ Paid</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Detailed Financial Records -->
            <div class="card">
                <h2>📋 Detailed Financial Records</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($financial_records)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--muted;">
                                    No financial records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($financial_records as $record): ?>
                                <tr>
                                    <td><?php echo ucfirst($record['fee_type']); ?></td>
                                    <td><?php echo htmlspecialchars($record['description'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format($record['amount'], 2); ?></td>
                                    <td>$<?php echo number_format($record['paid_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($record['amount'] - $record['paid_amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($record['due_date'])); ?></td>
                                    <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                    <td>
                                        <?php if ($record['status'] !== 'paid'): ?>
                                            <button class="btn btn-success btn-sm" onclick="showPaymentForm(<?php echo $record['id']; ?>, <?php echo $record['amount'] - $record['paid_amount']; ?>)">
                                                Pay
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #10B981; font-size: 0.875rem;">✓ Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--card-bg); padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
            <h3>Process Payment</h3>
            <form method="POST">
                <input type="hidden" id="payment_record_id" name="record_id">
                <div class="form-group">
                    <label for="payment_amount">Payment Amount ($)</label>
                    <input type="number" id="payment_amount" name="amount" min="0.01" step="0.01" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" name="process_payment" class="btn btn-success">Process Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showPaymentForm(recordId, maxAmount) {
            document.getElementById('payment_record_id').value = recordId;
            document.getElementById('payment_amount').value = maxAmount.toFixed(2);
            document.getElementById('payment_amount').max = maxAmount.toFixed(2);
            document.getElementById('paymentModal').style.display = 'block';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        // Financial Chart
        const financialCtx = document.getElementById('financialChart').getContext('2d');
        const financialChart = new Chart(financialCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending', 'Overdue'],
                datasets: [{
                    data: [
                        <?php echo $financial_summary['paid_amount'] ?? 0; ?>,
                        <?php echo $financial_summary['pending_amount'] ?? 0; ?>,
                        <?php echo $financial_summary['overdue_amount'] ?? 0; ?>
                    ],
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#6B7280' }
                    }
                }
            }
        });
    </script>
</body>
</html>
