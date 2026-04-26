<?php
/**
 * AJAX Search API for Live Search Functionality
 * Handles student, course, and user searches with filtering
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/Auth.php';
require_once '../includes/Database.php';

session_start();
$auth = new Auth();

// Require authentication for all searches
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'students';
$filters = $_GET['filters'] ?? [];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$results = [];
$total = 0;

try {
    switch ($type) {
        case 'students':
            $sql = "SELECT s.id, s.student_id, s.gpa, s.year_level, s.status,
                           CONCAT(u.first_name, ' ', u.last_name) as full_name,
                           u.email, u.phone, d.name as department_name
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    JOIN departments d ON s.department_id = d.id
                    WHERE (u.first_name LIKE :query OR 
                           u.last_name LIKE :query OR 
                           s.student_id LIKE :query OR 
                           u.email LIKE :query)";
            
            $params = ['query' => "%$query%"];
            
            // Apply filters
            if (!empty($filters['department'])) {
                $sql .= " AND d.name = :department";
                $params['department'] = $filters['department'];
            }
            
            if (!empty($filters['year_level'])) {
                $sql .= " AND s.year_level = :year_level";
                $params['year_level'] = $filters['year_level'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND s.status = :status";
                $params['status'] = $filters['status'];
            }
            
            // Get total count
            $countSql = str_replace("SELECT s.id, s.student_id, s.gpa, s.year_level, s.status,
                           CONCAT(u.first_name, ' ', u.last_name) as full_name,
                           u.email, u.phone, d.name as department_name", 
                          "SELECT COUNT(*)", $sql);
            $total = $db->count($countSql, $params);
            
            // Get paginated results
            $sql .= " ORDER BY u.first_name, u.last_name LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $results = $db->fetchAll($sql, $params);
            break;
            
        case 'courses':
            $sql = "SELECT c.id, c.course_code, c.course_name, c.credits, c.semester, c.year_level,
                           d.name as department_name,
                           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                           COUNT(e.student_id) as enrolled_students
                    FROM courses c
                    JOIN departments d ON c.department_id = d.id
                    JOIN users u ON c.teacher_id = u.id
                    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
                    WHERE (c.course_code LIKE :query OR c.course_name LIKE :query) AND c.is_active = 1";
            
            $params = ['query' => "%$query%"];
            
            // Apply filters
            if (!empty($filters['department'])) {
                $sql .= " AND d.name = :department";
                $params['department'] = $filters['department'];
            }
            
            if (!empty($filters['teacher_id'])) {
                $sql .= " AND c.teacher_id = :teacher_id";
                $params['teacher_id'] = $filters['teacher_id'];
            }
            
            // Get total count
            $countSql = str_replace("SELECT c.id, c.course_code, c.course_name, c.credits, c.semester, c.year_level,
                           d.name as department_name,
                           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                           COUNT(e.student_id) as enrolled_students", 
                          "SELECT COUNT(*)", $sql);
            $total = $db->count($countSql, $params);
            
            // Get paginated results
            $sql .= " GROUP BY c.id ORDER BY c.course_code LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $results = $db->fetchAll($sql, $params);
            break;
            
        case 'users':
            // Only admins can search all users
            if (!$auth->hasRole('admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit;
            }
            
            $sql = "SELECT id, username, email, role, first_name, last_name, phone, is_active, created_at
                    FROM users 
                    WHERE (username LIKE :query OR 
                           email LIKE :query OR 
                           first_name LIKE :query OR 
                           last_name LIKE :query)";
            
            $params = ['query' => "%$query%"];
            
            // Apply filters
            if (!empty($filters['role'])) {
                $sql .= " AND role = :role";
                $params['role'] = $filters['role'];
            }
            
            if (!empty($filters['is_active'])) {
                $sql .= " AND is_active = :is_active";
                $params['is_active'] = $filters['is_active'];
            }
            
            // Get total count
            $total = $db->count(str_replace("SELECT id, username, email, role, first_name, last_name, phone, is_active, created_at", 
                                          "SELECT COUNT(*)", $sql), $params);
            
            // Get paginated results
            $sql .= " ORDER BY first_name, last_name LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $results = $db->fetchAll($sql, $params);
            break;
            
        case 'grades':
            // Teachers can search grades for their courses, admins for all
            $userId = $auth->getUserId();
            $userRole = $auth->getUserRole();
            
            if ($userRole === 'teacher') {
                $sql = "SELECT g.id, g.assessment_type, g.assessment_name, g.score, g.max_score, g.graded_date,
                               c.course_code, c.course_name,
                               CONCAT(u.first_name, ' ', u.last_name) as student_name,
                               s.student_id
                        FROM grades g
                        JOIN enrollments e ON g.enrollment_id = e.id
                        JOIN courses c ON e.course_id = c.id
                        JOIN students s ON e.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        WHERE c.teacher_id = :teacher_id AND 
                              (u.first_name LIKE :query OR 
                               u.last_name LIKE :query OR 
                               s.student_id LIKE :query OR 
                               c.course_code LIKE :query)";
                $params = ['teacher_id' => $userId, 'query' => "%$query%"];
            } else {
                $sql = "SELECT g.id, g.assessment_type, g.assessment_name, g.score, g.max_score, g.graded_date,
                               c.course_code, c.course_name,
                               CONCAT(u.first_name, ' ', u.last_name) as student_name,
                               s.student_id
                        FROM grades g
                        JOIN enrollments e ON g.enrollment_id = e.id
                        JOIN courses c ON e.course_id = c.id
                        JOIN students s ON e.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        WHERE (u.first_name LIKE :query OR 
                               u.last_name LIKE :query OR 
                               s.student_id LIKE :query OR 
                               c.course_code LIKE :query)";
                $params = ['query' => "%$query%"];
            }
            
            // Apply filters
            if (!empty($filters['assessment_type'])) {
                $sql .= " AND g.assessment_type = :assessment_type";
                $params['assessment_type'] = $filters['assessment_type'];
            }
            
            if (!empty($filters['course_id'])) {
                $sql .= " AND c.id = :course_id";
                $params['course_id'] = $filters['course_id'];
            }
            
            // Get total count
            $total = $db->count(str_replace("SELECT g.id, g.assessment_type, g.assessment_name, g.score, g.max_score, g.graded_date,
                               c.course_code, c.course_name,
                               CONCAT(u.first_name, ' ', u.last_name) as student_name,
                               s.student_id", 
                              "SELECT COUNT(*)", $sql), $params);
            
            // Get paginated results
            $sql .= " ORDER BY g.graded_date DESC LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $results = $db->fetchAll($sql, $params);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid search type']);
            exit;
    }
    
    // Format response
    $response = [
        'success' => true,
        'data' => $results,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ],
        'filters' => [
            'departments' => $type === 'students' || $type === 'courses' ? $db->fetchAll("SELECT name FROM departments ORDER BY name") : [],
            'years' => $type === 'students' ? $db->fetchAll("SELECT DISTINCT year_level FROM students ORDER BY year_level") : [],
            'statuses' => $type === 'students' ? $db->fetchAll("SELECT DISTINCT status FROM students ORDER BY status") : [],
            'roles' => $type === 'users' ? [['role' => 'student'], ['role' => 'teacher'], ['role' => 'admin']] : []
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}
?>
