<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/PDFGenerator.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle fee record creation
if (isset($_POST['add_fee'])) {
    try {
        $data = [
            'student_id' => $_POST['student_id'],
            'fee_type' => $_POST['fee_type'],
            'amount' => $_POST['amount'],
            'due_date' => $_POST['due_date'],
            'description' => $_POST['description'] ?? '',
            'created_by' => $auth->getUserId()
        ];
        
        $sql = "INSERT INTO financial_records (student_id, fee_type, amount, due_date, description, created_by) 
                VALUES (:student_id, :fee_type, :amount, :due_date, :description, :created_by)";
        
        $recordId = $db->insert($sql, $data);
        $auth->logActivity($auth->getUserId(), 'add_fee_record', 'financial_records', $recordId, null, $data);
        
        $message = "Fee record added successfully!";
        
    } catch (Exception $e) {
        $error = "Failed to add fee record: " . $e->getMessage();
    }
}

// Handle payment processing
if (isset($_POST['process_payment'])) {
    try {
        $recordId = $_POST['record_id'];
        $paidAmount = $_POST['paid_amount'];
        $paymentMethod = $_POST['payment_method'];
        $transactionId = $_POST['transaction_id'] ?? '';
        
        // Get current record
        $record = $db->fetch("SELECT * FROM financial_records WHERE id = :id", ['id' => $recordId]);
        
        if (!$record) {
            throw new Exception("Fee record not found");
        }
        
        $db->beginTransaction();
        
        // Update financial record
        $newPaidAmount = $record['paid_amount'] + $paidAmount;
        $newStatus = $newPaidAmount >= $record['amount'] ? 'paid' : 'partial';
        
        $updateSql = "UPDATE financial_records 
                     SET paid_amount = :paid_amount, status = :status, paid_date = CURRENT_DATE,
                         payment_method = :payment_method, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id";
        
        $db->update($updateSql, [
            'paid_amount' => $newPaidAmount,
            'status' => $newStatus,
            'payment_method' => $paymentMethod,
            'id' => $recordId
        ]);
        
        // Create receipt record
        $receiptData = [
            'financial_record_id' => $recordId,
            'receipt_number' => 'REC-' . date('Y') . '-' . str_pad($recordId, 6, '0', STR_PAD_LEFT),
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'generated_by' => $auth->getUserId()
        ];
        
        $receiptSql = "INSERT INTO financial_receipts (financial_record_id, receipt_number, payment_method, transaction_id, generated_by) 
                       VALUES (:financial_record_id, :receipt_number, :payment_method, :transaction_id, :generated_by)";
        
        $receiptId = $db->insert($receiptSql, $receiptData);
        
        $db->commit();
        $auth->logActivity($auth->getUserId(), 'process_payment', 'financial_records', $recordId, null, $receiptData);
        
        $message = "Payment processed successfully!";
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Failed to process payment: " . $e->getMessage();
    }
}

// Handle PDF receipt generation
if (isset($_POST['generate_receipt']) && isset($_POST['record_id'])) {
    try {
        require_once '../includes/PDFGenerator.php';
        $pdfGen = new PDFGenerator();
        
        $receiptData = generateFinancialReceipt($_POST['record_id'], $db, $auth);
        
        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="receipt_' . $_POST['record_id'] . '.pdf"');
        echo $receiptData;
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to generate receipt: " . $e->getMessage();
    }
}

// Get financial records with filters
$where = ["1=1"];
$params = [];

if (!empty($_GET['student_id'])) {
    $where[] = "s.student_id LIKE :student_id";
    $params['student_id'] = '%' . $_GET['student_id'] . '%';
}

if (!empty($_GET['fee_type'])) {
    $where[] = "fr.fee_type = :fee_type";
    $params['fee_type'] = $_GET['fee_type'];
}

if (!empty($_GET['status'])) {
    $where[] = "fr.status = :status";
    $params['status'] = $_GET['status'];
}

if (!empty($_GET['date_from'])) {
    $where[] = "fr.due_date >= :date_from";
    $params['date_from'] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $where[] = "fr.due_date <= :date_to";
    $params['date_to'] = $_GET['date_to'];
}

$whereClause = implode(' AND ', $where);

// Get financial records
$records = $db->fetchAll("
    SELECT fr.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
           d.name as department_name, fr.receipt_id
    FROM financial_records fr
    JOIN students s ON fr.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE $whereClause
    ORDER BY fr.due_date DESC, fr.created_at DESC
", $params);

// Get summary statistics
$summary = $db->fetch("
    SELECT 
        COUNT(*) as total_records,
        SUM(amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_amount,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount
    FROM financial_records fr
    JOIN students s ON fr.student_id = s.id
    WHERE $whereClause
", $params);

// Get students for dropdown
$students = $db->fetchAll("
    SELECT s.id, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as full_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY u.last_name, u.first_name
");

function generateFinancialReceipt($recordId, $db, $auth) {
    // Get complete record data
    $record = $db->fetch("
        SELECT fr.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               u.email, u.phone, d.name as department_name,
               fr.receipt_id, fr.receipt_number, fr.payment_date
        FROM financial_records fr
        JOIN students s ON fr.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE fr.id = :id
    ", ['id' => $recordId]);
    
    // Get receipt details
    $receipt = null;
    if ($record['receipt_id']) {
        $receipt = $db->fetch("
            SELECT * FROM financial_receipts WHERE id = :id
        ", ['id' => $record['receipt_id']]);
    }
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4F46E5; padding-bottom: 20px; }
            .header h1 { color: #4F46E5; margin: 0; font-size: 2rem; }
            .header p { margin: 5px 0; color: #6B7280; }
            .receipt-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
            .info-section { background: #f8f9fa; padding: 20px; border-radius: 8px; }
            .info-section h3 { margin: 0 0 15px 0; color: #4F46E5; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
            .info-item { margin-bottom: 10px; display: flex; justify-content: space-between; }
            .info-label { font-weight: 600; color: #374151; }
            .info-value { color: #6B7280; }
            .payment-details { margin: 30px 0; }
            .payment-details table { width: 100%; border-collapse: collapse; }
            .payment-details th, .payment-details td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
            .payment-details th { background: #4F46E5; color: white; }
            .amount-paid { font-size: 1.5rem; font-weight: bold; color: #10B981; }
            .amount-due { font-size: 1.2rem; font-weight: bold; color: #EF4444; }
            .footer { margin-top: 50px; text-align: center; color: #6B7280; font-size: 0.875rem; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; color: rgba(79, 70, 229, 0.1); font-weight: bold; z-index: -1; }
        </style>
    </head>
    <body>
        <div class='watermark'>PAID</div>
        
        <div class='header'>
            <h1>Payment Receipt</h1>
            <p>Bahir Dar University - Student Management System</p>
            <p>Receipt #: " . htmlspecialchars($receipt['receipt_number'] ?? 'N/A') . "</p>
            <p>Date: " . date('F j, Y') . "</p>
        </div>
        
        <div class='receipt-info'>
            <div class='info-section'>
                <h3>Student Information</h3>
                <div class='info-item'>
                    <span class='info-label'>Student ID:</span>
                    <span class='info-value'>" . htmlspecialchars($record['student_id']) . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Name:</span>
                    <span class='info-value'>" . htmlspecialchars($record['student_name']) . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Department:</span>
                    <span class='info-value'>" . htmlspecialchars($record['department_name']) . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Email:</span>
                    <span class='info-value'>" . htmlspecialchars($record['email']) . "</span>
                </div>
            </div>
            
            <div class='info-section'>
                <h3>Payment Information</h3>
                <div class='info-item'>
                    <span class='info-label'>Fee Type:</span>
                    <span class='info-value'>" . ucfirst($record['fee_type']) . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Description:</span>
                    <span class='info-value'>" . htmlspecialchars($record['description'] ?? 'N/A') . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Payment Method:</span>
                    <span class='info-value'>" . htmlspecialchars($receipt['payment_method'] ?? 'N/A') . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Transaction ID:</span>
                    <span class='info-value'>" . htmlspecialchars($receipt['transaction_id'] ?? 'N/A') . "</span>
                </div>
            </div>
        </div>
        
        <div class='payment-details'>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Due Date</th>
                        <th>Total Amount</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>" . ucfirst($record['fee_type']) . "</td>
                        <td>" . date('M j, Y', strtotime($record['due_date'])) . "</td>
                        <td class='amount-due'>$" . number_format($record['amount'], 2) . "</td>
                        <td class='amount-paid'>$" . number_format($record['paid_amount'], 2) . "</td>
                        <td>$" . number_format($record['amount'] - $record['paid_amount'], 2) . "</td>
                        <td>" . ucfirst($record['status']) . "</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class='footer'>
            <p>This receipt serves as proof of payment. Please keep it for your records.</p>
            <p>For inquiries, contact the Bursar's Office | Bahir Dar University</p>
            <p>Generated on: " . date('F j, Y H:i:s') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Ledger | BDU Student System</title>
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
            padding: 1rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 0.875rem;
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .filter-group input, .filter-group select {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg);
            color: var(--text);
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
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
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-success { background: #10B981; color: white; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        
        .action-buttons {
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
            
            .filters {
                grid-template-columns: 1fr;
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
                    <li><a href="#" class="active">💰 Financial Ledger</a></li>
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Financial Ledger</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Manage student fees, payments, and generate receipts</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $summary['total_records']; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($summary['total_amount'], 2); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($summary['total_paid'], 2); ?></div>
                    <div class="stat-label">Paid Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($summary['pending_amount'], 2); ?></div>
                    <div class="stat-label">Pending Amount</div>
                </div>
            </div>

            <!-- Add Fee Record -->
            <div class="card">
                <h2>➕ Add Fee Record</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student">Student</label>
                            <select id="student" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_type">Fee Type</label>
                            <select id="fee_type" name="fee_type" required>
                                <option value="">Select Type</option>
                                <option value="tuition">Tuition</option>
                                <option value="lab">Lab</option>
                                <option value="library">Library</option>
                                <option value="sports">Sports</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="number" id="amount" name="amount" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                    
                    <button type="submit" name="add_fee" class="btn btn-primary">Add Fee Record</button>
                </form>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label for="filter_student">Student ID</label>
                    <input type="text" id="filter_student" placeholder="Search student ID..." 
                           value="<?php echo htmlspecialchars($_GET['student_id'] ?? ''); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="filter_fee_type">Fee Type</label>
                    <select id="filter_fee_type">
                        <option value="">All Types</option>
                        <option value="tuition" <?php echo ($_GET['fee_type'] ?? '') === 'tuition' ? 'selected' : ''; ?>>Tuition</option>
                        <option value="lab" <?php echo ($_GET['fee_type'] ?? '') === 'lab' ? 'selected' : ''; ?>>Lab</option>
                        <option value="library" <?php echo ($_GET['fee_type'] ?? '') === 'library' ? 'selected' : ''; ?>>Library</option>
                        <option value="sports" <?php echo ($_GET['fee_type'] ?? '') === 'sports' ? 'selected' : ''; ?>>Sports</option>
                        <option value="other" <?php echo ($_GET['fee_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_status">Status</label>
                    <select id="filter_status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo ($_GET['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo ($_GET['status'] ?? '') === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="overdue" <?php echo ($_GET['status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_date_from">Date From</label>
                    <input type="date" id="filter_date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="filter_date_to">Date To</label>
                    <input type="date" id="filter_date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
                </div>
            </div>

            <!-- Financial Records Table -->
            <div class="card">
                <h2>💰 Financial Records</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Fee Type</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 2rem; color: var(--muted;">
                                    No financial records found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['department_name']); ?></td>
                                    <td><?php echo ucfirst($record['fee_type']); ?></td>
                                    <td>$<?php echo number_format($record['amount'], 2); ?></td>
                                    <td>$<?php echo number_format($record['paid_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($record['amount'] - $record['paid_amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($record['due_date'])); ?></td>
                                    <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($record['status'] !== 'paid'): ?>
                                                <button class="btn btn-success btn-sm" onclick="processPayment(<?php echo $record['id']; ?>, <?php echo $record['amount'] - $record['paid_amount']; ?>)">
                                                    Pay
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($record['paid_amount'] > 0): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="generate_receipt" value="1">
                                                    <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Receipt</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
            <form method="POST" id="paymentForm">
                <input type="hidden" id="payment_record_id" name="record_id">
                <div class="form-group">
                    <label for="paid_amount">Amount to Pay:</label>
                    <input type="number" id="paid_amount" name="paid_amount" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="payment_method">Payment Method:</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="cash">Cash</option>
                        <option value="card">Credit Card</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="mobile">Mobile Money</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transaction_id">Transaction ID:</label>
                    <input type="text" id="transaction_id" name="transaction_id" placeholder="Optional">
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" name="process_payment" class="btn btn-primary">Process Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function processPayment(recordId, amount) {
            document.getElementById('payment_record_id').value = recordId;
            document.getElementById('paid_amount').value = amount;
            document.getElementById('paymentModal').style.display = 'block';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function clearFilters() {
            window.location.href = 'financial_ledger.php';
        }
        
        // Auto-submit filters when changed
        document.querySelectorAll('.filter-group input, .filter-group select').forEach(element => {
            if (element.id !== 'filter_student') {
                element.addEventListener('change', function() {
                    const form = document.createElement('form');
                    form.method = 'GET';
                    form.style.display = 'none';
                    
                    document.querySelectorAll('.filter-group input, .filter-group select').forEach(input => {
                        if (input.value) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = input.id.replace('filter_', '');
                            hidden.value = input.value;
                            form.appendChild(hidden);
                        }
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });
        
        // Search on Enter key for student filter
        document.getElementById('filter_student').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.createElement('form');
                form.method = 'GET';
                form.style.display = 'none';
                
                if (this.value) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'student_id';
                    hidden.value = this.value;
                    form.appendChild(hidden);
                }
                
                document.querySelectorAll('.filter-group input, .filter-group select').forEach(input => {
                    if (input.id !== 'filter_student' && input.value) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = input.id.replace('filter_', '');
                        hidden.value = input.value;
                        form.appendChild(hidden);
                    }
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    </script>
</body>
</html>
