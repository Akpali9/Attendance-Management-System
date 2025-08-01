<?php
// Security enhancements - must be set BEFORE session_start()
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
             (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $is_https ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 0); // Session cookie expires when browser closes

session_start();

// Initialize theme
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';

// Handle theme toggle separately (non-critical action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme_toggle'])) {
    $new_theme = ($_POST['theme'] == 'light') ? 'dark' : 'light';
    $_SESSION['theme'] = $new_theme;
    
    // Redirect to prevent form resubmission
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $redirect_url");
    exit();
}

// Regenerate session ID to prevent session fixation (preserve CSRF token)
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
    // Generate CSRF token immediately after session creation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} elseif (time() - $_SESSION['created'] > 1800) {
    // Preserve existing CSRF token during regeneration
    $csrf_token_backup = $_SESSION['csrf_token'] ?? null;
    
    session_regenerate_id(true);
    $_SESSION['created'] = time();
    
    // Restore CSRF token after regeneration
    if ($csrf_token_backup) {
        $_SESSION['csrf_token'] = $csrf_token_backup;
    }
}

// Database configuration - In production, store in environment variables
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("System maintenance in progress. Please try again later.");
}

// Create tables if they don't exist (using prepared statements)
$tables = [
    "CREATE TABLE IF NOT EXISTS admins (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(50) NOT NULL,
        role ENUM('superadmin', 'admin') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS class_groups (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS members (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(50) NOT NULL,
        email VARCHAR(50),
        phone VARCHAR(20),
        class_group_id INT(6) UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS attendance (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id INT(6) UNSIGNED NOT NULL,
        admin_id INT(6) UNSIGNED NOT NULL,
        attendance_date DATE NOT NULL,
        attendance_time TIME NOT NULL,
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
];

foreach ($tables as $sql) {
    $conn->query($sql);
}

// Insert default groups if none exist (using prepared statements)
$result = $conn->query("SELECT COUNT(*) as count FROM class_groups");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $groups = ['Church Administrations director','Church Admin Department (Team 1) ','Church Admin Department (Team 2) ','Ministry Information Department (Team 1)','Ministry Information Department (Team 2)','Service Management Director','Service Coordination Department (Team 1)','Service Coordination Department (Team 2)','Creative & Brand Management Department (Team 1)','Creative & Brand Management Department (Team 2)','Worship Director', 'Loveway Choir (Team 1)','Loveway Choir (Team 2)','Loveway Minstrels (Team 1)', 'Loveway Minstrels (Team 2)','Pastoral Worship Team (Team 1)','Pastoral Worship Team (Team 2)','Stage Arts Director','LAM Theatre Department (Team 1)','LAM Theatre Department (Team 2)','Full House Department (Team 1)','Full House Department (Team 2)','LAM Dance Department (Team 1)','LAM Dance Department (Team 2)','Royal Breed Director','Super Infants Department (3months-1year Team1)','Super Infants Department (3months-1year Team2)','Super Toddler Department (2years-3years Team1)','Super Toddler Department (2years-3years Team2)','Super Kids 1 Department (4years-5years Team1)','Super Kids 1 Department (4years-5years Team2)','Super Kids 2 Department (6years-7years Team1)','Super Kids 2 Department (6years-7years Team2)', 'Super Kids 3 Department (8years-10years Team1)','Super Kids 3 Department (8years-10years Team2)','Super Kids Director','G-Royal Director','Pre-Teens Department (11years-13years Team1)','Pre-Teens Department (11years-13years Team2)','Teens Department (14years-16years Team1)','Teens Department (14years-16years Team2)','Multmedia Production Director','Video Production (Team1)','Video Production (Team2)','Stage & Lighting (Team1)','Stage & Lighting (Team2)','Graphics, Animation & Projection (GAP) Department (Team1)','Graphics, Animation & Projection (GAP) Department (Team2)','New Media Director','Internet Ministry Department (Team1)','Internet Ministry Department (Team2)','Content Creation Department (Team1)','Content Creation Department (Team2)','Photography Department (Team1)','Photography Department (Team2)','Sound & Power Director','Sound Engineering Department (Team1)','Sound Engineering Department (Team2)','Facility Maintenance Department (Team1)','Facility Maintenance Department (Team2)','Ministry Resource Director','Resource Production Department (Team1)','Resource Production Department (Team2)','Word bank Marketers Department (Team1)','Word bank Marketers Department (Team2)','Venue Management Director','Sanctuary Keepers Department (Team1)','Sanctuary Keepers Department (Team2)','Exterior Keepers Department (Team1)','Exterior Keepers Department (Team2)','Altar keepers Team (Team1)','Altar keepers Team (Team2)','Impression Director','Greeters Department (Team1)','Greeters Department (Team2)','Usher Department (Team1)','Usher Department (Team2)','Operation Director','Protocols Department (Team1)','Protocols Department (Team2)','Marshals Department (Team1)','Marshals Department (Team2)','Pastoral Care Director','Cell Trainings Department (Team1)','Cell Trainings Department (Team2)','Cell Ministry Department (Team1)','Strategic Mission director','Diplomatic Outreach Department (Team1)','Diplomatic Outreach Department (Team2)','Charity Outreach Department (Team1)','Charity Outreach Department (Team2)','New Assimilation Director','Royal Host Department (Team1)','Royal Host Department (Team2)','Real Friends Department (Team1)','Real Friends Department (Team2)','Spiritual Maturity Director','Maturity Admin Department (Team1)','Maturity Admin Department (Team2)','Maturity Operations Department (Team1)','Maturity Operations Department (Team2)','Physical Health Director','Healing Hands Department (Team1)','Healing Hands Department (Team2)','Sports and Fitness Department (Team1)','Sports and Fitness Department (Team2)'];
    
    $stmt = $conn->prepare("INSERT INTO class_groups (group_name) VALUES (?)");
    foreach ($groups as $group) {
        $stmt->bind_param("s", $group);
        $stmt->execute();
    }
    $stmt->close();
}

// Insert default superadmin if none exists
$result = $conn->query("SELECT COUNT(*) as count FROM admins");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (username, password, fullname, role) VALUES (?, ?, ?, 'superadmin')");
    $username = 'superadmin';
    $fullname = 'Admin';
    $stmt->bind_param("sss", $username, $hashed_password, $fullname);
    $stmt->execute();
    $stmt->close();
}

// Update existing admins if needed
$stmt = $conn->prepare("UPDATE admins SET role = 'superadmin' WHERE username = ?");
$username = 'superadmin';
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->close();

// CSRF token generation (ensure token always exists)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// NEW: Date selection variables
$selected_day = date('Y-m-d');
if (isset($_GET['attendance_day'])) {
    $selected_day = $_GET['attendance_day'];
}

$current_year = date('Y');
$selected_year = $current_year;
if (isset($_GET['attendance_year'])) {
    $selected_year = intval($_GET['attendance_year']);
}

// FIX: Define current month variable
$current_month = date('Y-m');

// Authentication functions
function login($username, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, password, fullname, role FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            // Password rehashing if needed
            if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $newHash, $admin['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['fullname'] = $admin['fullname'];
            $_SESSION['role'] = $admin['role'];
            return true;
        }
    }
    return false;
}

function is_logged_in() {
    return isset($_SESSION['admin_id']);
}

function is_superadmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function logout() {
    $_SESSION = array();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Check if member already marked attendance today
function already_attended_today($member_id) {
    global $conn;
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT id FROM attendance WHERE member_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $member_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Handle form submissions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (login($_POST['username'], $_POST['password'])) {
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $login_error = "Invalid username or password";
                }
            }
            break;
        case 'logout':
            logout();
            break;
    }
}

// Notification messages
$notification = [];
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle CSRF validation failure with redirect instead of die()
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log('CSRF validation failed for IP: ' . $_SERVER['REMOTE_ADDR']);
        
        // Set notification
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Security validation failed. Please try again.'
        ];
        
        // Redirect to appropriate page
        if (is_logged_in()) {
            header("Location: index.php?page=dashboard");
        } else {
            header("Location: index.php");
        }
        exit();
    }
    
    if (isset($_POST['add_member'])) {
        $fullname = htmlspecialchars($_POST['fullname'], ENT_QUOTES, 'UTF-8');
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = preg_replace('/[^0-9+]/', '', $_POST['phone']);
        $class_group_id = intval($_POST['class_group_id']);
        
        $stmt = $conn->prepare("INSERT INTO members (fullname, email, phone, class_group_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $fullname, $email, $phone, $class_group_id);
        if ($stmt->execute()) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Worker added successfully!'
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Failed to add worker: ' . $conn->error
            ];
        }
        $stmt->close();
        header("Location: index.php?page=dashboard#members");
        exit();
    }
    
    if (isset($_POST['delete_member'])) {
        $member_id = intval($_POST['member_id']);
        // First delete attendance records
        $stmt = $conn->prepare("DELETE FROM attendance WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the member
        $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        if ($stmt->execute()) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Worker deleted successfully!'
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Failed to delete worker: ' . $conn->error
            ];
        }
        $stmt->close();
        header("Location: index.php?page=dashboard#members");
        exit();
    }
    
    if (isset($_POST['mark_attendance'])) {
        $member_id = intval($_POST['member_id']);
        
        if (!already_attended_today($member_id)) {
            $admin_id = $_SESSION['admin_id'];
            $date = date('Y-m-d');
            $time = date('H:i:s');
            
            $stmt = $conn->prepare("INSERT INTO attendance (member_id, admin_id, attendance_date, attendance_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $member_id, $admin_id, $date, $time);
            if ($stmt->execute()) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Attendance marked successfully!'
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Failed to mark attendance: ' . $conn->error
            ];
        }
        $stmt->close();
        } else {
            $_SESSION['notification'] = [
                'type' => 'warning',
                'message' => 'This worker has already been marked present today!'
            ];
        }
        header("Location: index.php?page=dashboard");
        exit();
    }
    
    if (isset($_POST['add_admin'])) {
        if (is_superadmin()) {
            $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
            
            // Check if username exists
            $check_stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'Username already exists! Choose a different username.'
                ];
            } else {
                $fullname = htmlspecialchars($_POST['fullname'], ENT_QUOTES, 'UTF-8');
                $password = $_POST['password'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO admins (username, password, fullname) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hashed_password, $fullname);
                if ($stmt->execute()) {
                    $_SESSION['notification'] = [
                        'type' => 'success',
                        'message' => 'Admin added successfully!'
                    ];
                } else {
                    $_SESSION['notification'] = [
                        'type' => 'error',
                        'message' => 'Failed to add admin: ' . $conn->error
                    ];
                }
                $stmt->close();
            }
            $check_stmt->close();
            header("Location: index.php?page=dashboard#admins");
            exit();
        }
    }
    
    if (isset($_POST['delete_admin'])) {
        if (is_superadmin()) {
            $admin_id = intval($_POST['admin_id']);
            // Prevent superadmin from deleting themselves
            if ($admin_id != $_SESSION['admin_id']) {
                // Reassign attendance records to current admin
                $current_admin_id = $_SESSION['admin_id'];
                $stmt = $conn->prepare("UPDATE attendance SET admin_id = ? WHERE admin_id = ?");
                $stmt->bind_param("ii", $current_admin_id, $admin_id);
                $stmt->execute();
                $stmt->close();
                
                // Then delete admin
                $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                if ($stmt->execute()) {
                    $_SESSION['notification'] = [
                        'type' => 'success',
                        'message' => 'Admin deleted successfully!'
                    ];
                } else {
                    $_SESSION['notification'] = [
                        'type' => 'error',
                        'message' => 'Failed to delete admin: ' . $conn->error
                    ];
                }
                $stmt->close();
                header("Location: index.php?page=dashboard#admins");
                exit();
            }
        }
    }
    
    if (isset($_POST['add_group'])) {
        $group_name = htmlspecialchars($_POST['group_name'], ENT_QUOTES, 'UTF-8');
        $stmt = $conn->prepare("INSERT INTO class_groups (group_name) VALUES (?)");
        $stmt->bind_param("s", $group_name);
        if ($stmt->execute()) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Department added successfully!'
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Failed to add department: ' . $conn->error
            ];
        }
        $stmt->close();
        header("Location: index.php?page=dashboard#department");
        exit();
    }
    
    if (isset($_POST['delete_group'])) {
        $group_id = intval($_POST['group_id']);
        $default_group = $conn->query("SELECT id FROM class_groups ORDER BY id LIMIT 1")->fetch_assoc();
        if ($default_group) {
            $default_id = $default_group['id'];
            $stmt = $conn->prepare("UPDATE members SET class_group_id = ? WHERE class_group_id = ?");
            $stmt->bind_param("ii", $default_id, $group_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("DELETE FROM class_groups WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        if ($stmt->execute()) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Department deleted successfully!'
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Failed to delete department: ' . $conn->error
            ];
        }
        $stmt->close();
        header("Location: index.php?page=dashboard#department");
        exit();
    }
}

// Get class groups
$groups = [];
$result = $conn->query("SELECT g.*, 
                        (SELECT COUNT(*) FROM members m WHERE m.class_group_id = g.id) AS member_count 
                        FROM class_groups g ORDER BY group_name");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
}

// Get members with group info
$members = [];
$result = $conn->query("SELECT m.*, g.group_name 
                        FROM members m 
                        JOIN class_groups g ON m.class_group_id = g.id 
                        ORDER BY g.group_name, m.fullname");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

// Get admins
$admins = [];
if (is_superadmin()) {
    $result = $conn->query("SELECT * FROM admins ORDER BY role DESC, fullname");
    if ($result) {
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $admins[] = $row;
            }
        }
    } else {
        error_log("Admin query failed: " . $conn->error);
    }
}

// NEW: Get attendance records for selected day
$attendance = [];
$result = $conn->query("SELECT a.*, m.fullname AS member_name, g.group_name, ad.fullname AS admin_name, 
                        DATE_FORMAT(a.attendance_time, '%h:%i %p') AS formatted_time 
                        FROM attendance a
                        JOIN members m ON a.member_id = m.id
                        JOIN class_groups g ON m.class_group_id = g.id
                        JOIN admins ad ON a.admin_id = ad.id
                        WHERE a.attendance_date = '$selected_day'
                        ORDER BY a.attendance_time DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
}

// Handle worker attendance report
$worker_attendance = [];
$worker_info = [];
if (isset($_GET['view_worker_attendance']) && isset($_GET['worker_id'])) {
    $worker_id = intval($_GET['worker_id']);
    $start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';
    
    // Prepare SQL query with date filtering
    $worker_attendance_sql = "SELECT a.*, m.fullname AS member_name, g.group_name, ad.fullname AS admin_name, 
                              DATE_FORMAT(a.attendance_date, '%M %d, %Y') AS formatted_date,
                              DATE_FORMAT(a.attendance_time, '%h:%i %p') AS formatted_time
                              FROM attendance a
                              JOIN members m ON a.member_id = m.id
                              JOIN class_groups g ON m.class_group_id = g.id
                              JOIN admins ad ON a.admin_id = ad.id
                              WHERE a.member_id = $worker_id";
    
    // Add date filters if provided
    if (!empty($start_date)) {
        $worker_attendance_sql .= " AND a.attendance_date >= '$start_date'";
    }
    if (!empty($end_date)) {
        $worker_attendance_sql .= " AND a.attendance_date <= '$end_date'";
    }
    
    $worker_attendance_sql .= " ORDER BY a.attendance_date DESC, a.attendance_time DESC";
    
    // Execute query
    $result = $conn->query($worker_attendance_sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $worker_attendance[] = $row;
        }
    }
    
    // Get worker info
    $worker_result = $conn->query("SELECT m.*, g.group_name 
                                  FROM members m 
                                  JOIN class_groups g ON m.class_group_id = g.id 
                                  WHERE m.id = $worker_id");
    if ($worker_result->num_rows > 0) {
        $worker_info = $worker_result->fetch_assoc();
    }
}

// Calculate statistics
$total_members = count($members);
$total_groups = count($groups);
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];
$today_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = CURDATE()")->fetch_assoc()['count'];

// NEW: Calculate absent workers for today
$today_absent = $total_members - $today_attendance;

// Yearly attendance calculation
$yearly_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE YEAR(attendance_date) = $selected_year")->fetch_assoc()['count'];

// Get the selected month for attendance calendar
$calendar_month = isset($_GET['calendar_month']) ? $_GET['calendar_month'] : date('Y-m');
if (isset($_POST['calendar_month'])) {
    $calendar_month = $_POST['calendar_month'];
}

// Get daily attendance for the selected month
$daily_attendance = [];
$result = $conn->query("SELECT attendance_date, COUNT(*) as count 
                        FROM attendance 
                        WHERE DATE_FORMAT(attendance_date, '%Y-%m') = '$calendar_month'
                        GROUP BY attendance_date 
                        ORDER BY attendance_date");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $daily_attendance[$row['attendance_date']] = $row['count'];
    }
}

// Generate CSV content for export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Workers_Attendance_Report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Date', 'Name', 'Department', 'Time','Marked-By'));
    
    $result = $conn->query("SELECT a.attendance_date, m.fullname AS member_name, g.group_name, 
                            a.attendance_time, ad.fullname AS admin_name 
                            FROM attendance a
                            JOIN members m ON a.member_id = m.id
                            JOIN class_groups g ON m.class_group_id = g.id
                            JOIN admins ad ON a.admin_id = ad.id
                            ORDER BY a.attendance_date DESC, a.attendance_time DESC");
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Get usernames for client-side validation
$admin_usernames = [];
if (is_superadmin()) {
    foreach ($admins as $admin) {
        $admin_usernames[] = $admin['username'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY"> -->
    <title>Love Ambassador Attendance</title>
    <link rel="icon" href="./img/lam-logo.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" 
      integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" 
      crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-<?php echo $theme; ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        
        :root {
            --primary: #4361ee;
            --primary-dark: #3f37c9;
            --secondary: #4cc9f0;
            --success: #2ec4b6;
            --warning: #f72585;
            --light: white;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-bg: #ffffff;
            --body-bg:rgb(7, 7, 8);
            --text: #333333;
            --header-bg: linear-gradient(135deg, var(--primary), var(--primary-dark));
            --group-dev: #4cc9f0;
            --group-design: #7209b7;
            --group-marketing: #f72585;
            --group-management: #2ec4b6;
        }

        .dark-theme {
            --primary: #5e72e4;
            --primary-dark: #4a56d0;
            --secondary: #63d3ff;
            --success: #34d1bf;
            --warning: #ff3b7f;
            --light: #2d3748;
            --dark: #e2e8f0;
            --gray: #a0aec0;
            --light-gray: #4a5568;
            --card-bg: #1a202c;
            --body-bg: #121212;
            --text: #e2e8f0;
            --header-bg: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        body {
            background-color: var(--body-bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .header {
            background: var(--header-bg);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .logo i {
            margin-right: 10px;
        }

        .nav {
            display: flex;
            gap: 20px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .nav a:hover, .nav a.active {
            background: rgba(255,255,255,0.2);
        }

        .auth-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .superadmin-badge {
            background: var(--warning);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-danger {
            background: var(--warning);
        }

        .btn-danger:hover {
            background: #d81159;
        }

        .btn-success {
            background: var(--success);
        }

        .btn-success:hover {
            background: #1a936f;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
            border: 1px solid var(--light-gray);
        }

        .card-header {
            padding: 15px 20px;
            background: var(--light);
            border-bottom: 1px solid var(--light-gray);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 20px;
        }
        .card-body-list {
            padding: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
            border: 1px solid var(--light-gray);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray);
            font-size: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--card-bg);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background: var(--light);
            font-weight: 600;
        }

        tr:hover {
            background: rgba(67, 97, 238, 0.05);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            font-size: 1rem;
            background: var(--card-bg);
            color: var(--text);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            padding: 30px;
        }

        .login-title {
            text-align: center;
            margin-bottom: 20px;
            color: var(--text);
        }

        .alert {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #ffe3e3;
            color: #dc3545;
            border: 1px solid #f8d7da;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .dark-theme .alert-danger {
            background: #4a1a1a;
            color: #ff7a7a;
            border-color: #5c2525;
        }

        .dark-theme .alert-success {
            background: #1a3a1a;
            color: #7aff7a;
            border-color: #255c25;
        }

        .footer {
            text-align: center;
            padding: 20px 0;
            color: var(--gray);
            border-top: 1px solid var(--light-gray);
            margin-top: 30px;
        }

        .group-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
        }

        .group-dev {
            background: var(--group-dev);
        }

        .group-design {
            background: var(--group-design);
        }

        .group-marketing {
            background: var(--group-marketing);
        }

        .group-management {
            background: var(--group-management);
        }

        .public-container {
            min-height: 100vh;
            background: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
            padding: 0px 0;
        }

        .dark-theme .public-container {
            background: linear-gradient(120deg, #1a1a2e 0%, #16213e 100%);
        }

        .public-header {
            background: url(./img/reg.jpg) center;
            color: white;
            padding: 80px 0 40px;
            text-align: center;
            margin-bottom: 40px;
            background-size: cover;
        }

        .public-logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .public-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .public-card-header {
            padding: 20px;
            background: var(--primary);
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .public-card-body, .public-card-body-list {
            padding: 25px;

        }

        .public-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .public-stat {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid var(--light-gray);
        }

        .public-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 5px 0;
            color: var(--primary);
        }

        .public-stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .attendance-record {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .attendance-record:last-child {
            border-bottom: none;
        }
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; 
            background: url(./img/excel.jpg);
            background-size: cover;
        }

        .attendance-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .attendance-info {
            flex: 1;
        }

        .attendance-time {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .group-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }

        .group-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 200px;
            transition: transform 0.3s;
            border: 1px solid var(--light-gray);
        }

        .group-card:hover {
            transform: translateY(-5px);
        }

        .group-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .group-name i {
            font-size: 1.4rem;
        }

        .group-members {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 10px;
        }

        .member-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 4px;
            background: var(--light);
        }

        .member-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-top: 20px;
        }

        .calendar-day {
            padding: 10px;
            text-align: center;
            border-radius: 4px;
            background: var(--light);
            min-height: 60px;
        }

        .calendar-header {
            text-align: center;
            font-weight: 600;
            padding: 5px;
            background: var(--primary);
            color: white;
            border-radius: 4px;
        }

        .present-day {
            background: var(--success);
            color: white;
        }

        .absent-day {
            background: #ffcccc;
        }

        .dark-theme .absent-day {
            background: #4a1a1a;
        }

        .theme-toggle {
            background: var(--light);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text);
        }

        .theme-toggle:hover {
            background: var(--light-gray);
        }

        .export-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .export-btn:hover {
            background: #1a936f;
        }
        
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .filter-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .filter-btn:hover {
            background: var(--primary-dark);
        }
        
        .group-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .team-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-top: 4px solid var(--group-dev);
        }
        
        .team-card:hover {
            transform: translateY(-5px);
        }
        
        .team-card.design {
            border-top-color: var(--group-design);
        }
        
        .team-card.marketing {
            border-top-color: var(--group-marketing);
        }
        
        .team-card.management {
            border-top-color: var(--group-management);
        }
        
        .team-title {
            font-size: 1.4rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .team-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .team-dev .team-icon {
            background: var(--group-dev);
        }
        
        .team-design .team-icon {
            background: var(--group-design);
        }
        
        .team-marketing .team-icon {
            background: var(--group-marketing);
        }
        
        .team-management .team-icon {
            background: var(--group-management);
        }
        
        .team-members {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .team-member {
            display: flex;
            align-items: center;
            padding: 8px;
            background: var(--light);
            border-radius: 6px;
        }
        
        .member-avatar-small {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .team-dev .member-avatar-small {
            background: var(--group-dev);
        }
        
        .team-design .member-avatar-small {
            background: var(--group-design);
        }
        
        .team-marketing .member-avatar-small {
            background: var(--group-marketing);
        }
        
        .team-management .member-avatar-small {
            background: var(--group-management);
        }

        /* Worker Attendance Report Styles */
        .report-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .worker-avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
        }

        .worker-info {
            flex: 1;
        }

        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .report-stat {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid var(--light-gray);
        }

        .report-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 5px 0;
            color: var(--primary);
        }

        .report-stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .date-range-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .date-input-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 200px;
        }
        
        /* Add this style for month selector */
        .month-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .month-selector label {
            font-weight: bold;
        }
        
        .month-selector select {
            padding: 8px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            background: var(--card-bg);
            color: var(--text);
        }
        
        .month-selector button {
            padding: 8px 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* Search box */
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-box input {
            padding-left: 40px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        /* NEW: Date selector styles */
        .date-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .date-selector label {
            font-weight: bold;
        }
        
        .year-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .year-selector label {
            font-weight: bold;
        }

        /* Username feedback */
        .username-feedback {
            display: none;
            color: var(--warning);
            margin-top: 5px;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            .head{
                font-size: 13px;
                width: 90%;
            }
            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .auth-info {
                margin-top: 10px;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .group-list {
                flex-direction: column;
            }
           .card-body-list, .public-card-body-list{
            overflow-x: scroll;
            
        }    
        }
    </style>
    <script>
        // Function to filter members by group
        function filterMembers() {
            const groupId = document.getElementById('group_filter').value;
            const memberSelect = document.getElementById('member_id');
            
            for (let i = 0; i < memberSelect.options.length; i++) {
                const option = memberSelect.options[i];
                if (option.value === "") continue;
                
                if (groupId === "all" || option.getAttribute('data-group') === groupId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
            
            // Reset to first option
            memberSelect.value = "";
        }
        
        // Search function for workers table
        function searchWorkers() {
            const input = document.getElementById('search-worker');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('workers-table');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) { // Start from 1 to skip header
                const tds = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < tds.length; j++) {
                    if (tds[j]) {
                        const txtValue = tds[j].textContent || tds[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
        
        // Client-side username check
        function checkUsernameAvailability() {
            const username = document.getElementById('username').value;
            const feedback = document.getElementById('username-feedback');
            const takenUsernames = [<?php 
                foreach($admin_usernames as $uname) echo "'" . addslashes($uname) . "',"; 
            ?>];
            
            if (takenUsernames.includes(username)) {
                feedback.style.display = 'block';
                return false;
            } else {
                feedback.style.display = 'none';
                return true;
            }
        }
        
        // Form validation
        function validateAdminForm() {
            if (!checkUsernameAvailability()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Username Taken',
                    text: 'This username is already in use. Please choose another.',
                    background: 'var(--card-bg)',
                    color: 'var(--text)'
                });
                return false;
            }
            return true;
        }
        
        // Worker deletion confirmation
        function confirmDelete(memberId, memberName) {
            Swal.fire({
                title: 'Delete Worker?',
                html: `Are you sure you want to permanently delete <b>${memberName}</b> and all their attendance records?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete!',
                background: 'var(--card-bg)',
                color: 'var(--text)'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('delete-form-' + memberId);
                    form.submit();
                }
            });
        }
    </script>
</head>
<body>
    <?php 
    // Determine which page to show
    $page = 'login';
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
    }
    
    // Public page
    if ($page === 'public') {
    ?>
        <div class="public-container">
            <div class="public-header">
                <div class="container">
                    <div class="public-logo">
                    Workers Attendance
                  </div>
                  <p>Attendance Records of all Church Workers</p>
                  <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme" value="<?php echo $theme; ?>">
                        <button type="submit" name="theme_toggle" class="theme-toggle">
                            <i class="fas fa-<?php echo $theme == 'light' ? 'moon' : 'sun'; ?>"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="container">
                <div class="public-stats">
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $total_members; ?></div>
                        <div class="public-stat-label">Total Members</div>
                    </div>
                    <!-- CHANGED: Absent Today -->
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $today_absent; ?></div>
                        <div class="public-stat-label">Absent Today</div>
                    </div>
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $today_attendance; ?></div>
                        <div class="public-stat-label">Today's Attendance</div>
                    </div>
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $yearly_attendance; ?></div>
                        <div class="public-stat-label"><?php echo date('Y'); ?> Attendance</div>
                    </div>
                </div>
                
                <div class="public-card">
                    <div class="public-card-header">
                        <i class="fas fa-users"></i> Our Departments
                    </div>
                    <div class="public-card-body">
                        <div class="group-grid">
                            <?php foreach ($groups as $group): 
                                $group_class = strtolower(str_replace(' ', '-', $group['group_name']));
                            ?>
                            <div class="team-card team-<?php echo $group_class; ?>">
                                <div class="team-title">
                                    <div class="team-icon team-<?php echo $group_class; ?>">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div><?php echo $group['group_name']; ?></div>
                                </div>
                                <div class="team-members">
                                    <?php 
                                    $group_members = array_filter($members, function($m) use ($group) {
                                        return $m['class_group_id'] == $group['id'];
                                    });
                                    foreach (array_slice($group_members, 0, 4) as $member): 
                                    ?>
                                        <div class="team-member">
                                            <div class="member-avatar-small team-<?php echo $group_class; ?>">
                                                <?php echo substr($member['fullname'], 0, 1); ?>
                                            </div>
                                            <div><?php echo $member['fullname']; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($group_members) > 4): ?>
                                        <div class="text-center" style="margin-top: 10px; font-size: 0.9rem;">
                                            +<?php echo count($group_members) - 4; ?> more members
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- NEW: Date selector for public dashboard -->
                <div class="public-card">
                    <div class="public-card-header">
                        <i class="fas fa-history"></i> Daily Attendance Records
                    </div>
                    <div class="public-card-body">
                        <form method="GET" class="date-selector">
                            <input type="hidden" name="page" value="public">
                            <label for="attendance_day">Select Date:</label>
                            <input type="date" name="attendance_day" value="<?php echo $selected_day; ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                            <button type="submit" class="btn">Load</button>
                        </form>
                        
                        <div class="attendance-list">
                            <?php if (!empty($attendance)): ?>
                                <?php foreach ($attendance as $record): ?>
                                    <div class="attendance-record">
                                        <div class="attendance-avatar" style="background: var(--primary);">
                                            <?php echo substr($record['member_name'], 0, 1); ?>
                                        </div>
                                        <div class="attendance-info">
                                            <div class="attendance-name"><?php echo $record['member_name']; ?></div>
                                            <div class="attendance-group"><?php echo $record['group_name']; ?></div>
                                        </div>
                                        <div class="attendance-time"><?php echo $record['formatted_time']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No attendance records found for this date</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-lock"></i> Admin Login
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p>Love Ambassador Ministry &copy; <?php echo date('Y'); ?> | All rights reserved</p>
    </div>
        </div>
    <?php
    // Admin login page
    } elseif (!is_logged_in()) {
    ?>
        <div class="login-container">
            <div class="login-card">
                <h2 class="login-title">Admin Login</h2>
                
                <form method="POST" action="?action=login">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn" style="width:100%;">Login</button>
                    
                    <div style="text-align:center; margin-top:15px;">
                        
                        <p style="margin-top: 10px;">
                            <a href="?page=public" class="btn btn-outline" style="margin-top: 10px;">
                                <i class="fas fa-eye"></i> View Public Attendance
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    
    <?php 
    // Admin dashboard
    } else {
        // Check if we are viewing a worker's attendance
        if (isset($_GET['view_worker_attendance'])) {
            $worker_id = intval($_GET['worker_id']);
            if (empty($worker_info)) {
                echo "<script>alert('Worker not found'); window.location.href='index.php';</script>";
                exit;
            }
            $group_class = strtolower(str_replace(' ', '-', $worker_info['group_name']));
    ?>
        <div class="header">
            <div class="container header-content">
                <div class="logo">
                       <img src="./img/logo.png" style="height: inherit; width: 50px; margin-left: 20px;">

                </div>
                
                <div class="nav">
    <a href="#dashboard" class="nav-link">Dashboard</a>
    <a href="#members"class="nav-link">Members</a>
    <a href="#attendance"class="nav-link">Attendance</a>
    <?php if (is_superadmin()): ?>
    <a href="#department" class="nav-link">Department</a>
        <a href="#admins"class="nav-link">Admins</a>
    <?php endif; ?>
</div>
                
                <div class="auth-info">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme" value="<?php echo $theme; ?>">
                        <button type="submit" name="theme_toggle" class="theme-toggle">
                            <i class="fas fa-<?php echo $theme == 'light' ? 'moon' : 'sun'; ?>"></i>
                        </button>
                    </form>
                    <div class="user-info">
                        <div class="avatar"><?php echo substr($_SESSION['fullname'], 0, 1); ?></div>
                        <span><?php echo $_SESSION['fullname']; ?>
                            <?php if (is_superadmin()): ?>
                                <span class="superadmin-badge">Admin</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <a href="?action=logout" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
        
        <div class="container">
            <div style="margin: 20px 0;">
                <a href="?page=dashboard" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="report-header">
                <div class="worker-avatar-lg" style="background: var(--group-<?php echo $group_class; ?>);">
                    <?php echo substr($worker_info['fullname'], 0, 1); ?>
                </div>
                <div class="worker-info">
                    <h1><?php echo $worker_info['fullname']; ?></h1>
                    <p>
                        <span class="group-badge" style="background: var(--group-<?php echo $group_class; ?>);">
                            <?php echo $worker_info['group_name']; ?>
                        </span>
                    </p>
                    <p>
                        <?php if ($worker_info['email']): ?>
                            <i class="fas fa-envelope"></i> <?php echo $worker_info['email']; ?>
                        <?php endif; ?>
                    </p>
                    <p>
                        <?php if ($worker_info['phone']): ?>
                            <i class="fas fa-phone"></i> <?php echo $worker_info['phone']; ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="report-stats">
                <div class="report-stat">
                    <div class="report-stat-value">
                        <?php 
                        $total_worker_attendance = $conn->query("SELECT COUNT(*) as count 
                                                                FROM attendance 
                                                                WHERE member_id = ".$worker_info['id'])->fetch_assoc()['count'];
                        echo $total_worker_attendance;
                        ?>
                    </div>
                    <div class="report-stat-label">Total Attendance</div>
                </div>
                
                <div class="report-stat">
                    <div class="report-stat-value">
                        <?php 
                        $current_month_attendance = $conn->query("SELECT COUNT(*) as count 
                                                         FROM attendance 
                                                         WHERE member_id = ".$worker_info['id']."
                                                         AND DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'")->fetch_assoc()['count'];
                        echo $current_month_attendance;
                        ?>
                    </div>
                    <div class="report-stat-label">This Month</div>
                </div>
                
                <div class="report-stat">
                    <div class="report-stat-value">
                        <?php 
                        $last_month = date('Y-m', strtotime('first day of last month'));
                        $last_month_attendance = $conn->query("SELECT COUNT(*) as count 
                                                      FROM attendance 
                                                      WHERE member_id = ".$worker_info['id']."
                                                      AND DATE_FORMAT(attendance_date, '%Y-%m') = '$last_month'")->fetch_assoc()['count'];
                        echo $last_month_attendance;
                        ?>
                    </div>
                    <div class="report-stat-label">Last Month</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <span>Attendance Records</span>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <input type="hidden" name="view_worker_attendance" value="1">
                        <input type="hidden" name="worker_id" value="<?php echo $worker_info['id']; ?>">
                        
                        <div class="date-range-selector">
                            <div class="date-input-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" 
                                       value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                            </div>
                            
                            <div class="date-input-group">
                                <label for="end_date">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" 
                                       value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                            </div>
                            
                            <div style="display: flex; align-items: flex-end; gap: 10px;">
                                <button type="submit" class="btn" style="height: 42px;">
                                    <i class="fas fa-filter"></i> Apply Filter
                                </button>
                                <a href="?view_worker_attendance=1&worker_id=<?php echo $worker_info['id']; ?>" 
                                   class="btn btn-outline" style="height: 42px;">
                                    <i class="fas fa-sync"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (!empty($worker_attendance)): ?>
                        <div class=" card-body-list">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Department</th>
                                    <th>Marked By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($worker_attendance as $record): ?>
                                    <tr>
                                        <td><?php echo $record['formatted_date']; ?></td>
                                        <td><?php echo $record['formatted_time']; ?></td>
                                        <td><?php echo $record['group_name']; ?></td>
                                        <td><?php echo $record['admin_name']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                                </div>
                    <?php else: ?>
                        <p>No attendance records found for this worker.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Love Ambassador Ministry &copy; <?php echo date('Y'); ?> | All rights reserved</p>
        </div>
    <?php
        } else {
    ?>
        <div class="header">
            <div class="container header-content">
                <div class="logo">
                       <img src="./img/logo.png" style="height: inherit; width: 50px; margin-left: 20px;">

                </div>
                
                <div class="nav">
    <a href="#dashboard" class="nav-link">Dashboard</a>
    <a href="#members"class="nav-link">Members</a>
    <a href="#attendance"class="nav-link">Attendance</a>
    <?php if (is_superadmin()): ?>
    <a href="#department" class="nav-link">Department</a>
        <a href="#admins"class="nav-link">Admins</a>
    <?php endif; ?>
</div>
                
                <div class="auth-info">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme" value="<?php echo $theme; ?>">
                        <button type="submit" name="theme_toggle" class="theme-toggle">
                            <i class="fas fa-<?php echo $theme == 'light' ? 'moon' : 'sun'; ?>"></i>
                        </button>
                    </form>
                    <div class="user-info">
                        <div class="avatar"><?php echo substr($_SESSION['fullname'], 0, 1); ?></div>
                        <span><?php echo $_SESSION['fullname']; ?>
                            <?php if (is_superadmin()): ?>
                                <span class="superadmin-badge">Admin</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <a href="?action=logout" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
        
        <div class="container">
            <div style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center;">
                <div id="dashboard">
                    <h1>Dashboard</h1>
                    <p class="head">Welcome back, <?php echo $_SESSION['fullname']; ?>! Here's your system overview.</p>
                </div>
                <a href="?export=csv" class="export-btn" style="text-decoration:none;">
                    <i class="fas fa-download"></i> Export Attendance
                </a>
            </div>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_members; ?></div>
                    <div class="stat-label">Total Workers</div>
                </div>
                
                <!-- CHANGED: Absent Today -->
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_absent; ?></div>
                    <div class="stat-label">Absent Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_attendance; ?></div>
                    <div class="stat-label">Today Present</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $yearly_attendance; ?></div>
                    <div class="stat-label"><?php echo $selected_year; ?> Total</div>
                </div>
            </div>
            
            <!-- NEW: Year selector for statistics -->
            <div class="year-selector">
                <form method="GET">
                    <input type="hidden" name="page" value="dashboard">
                    <label for="attendance_year">Select Year for Statistics:</label>
                    <select name="attendance_year" id="attendance_year">
                        <?php
                        $current_year = date('Y');
                        for ($year = $current_year; $year >= 2020; $year--) {
                            $selected = ($year == $selected_year) ? 'selected' : '';
                            echo "<option value='$year' $selected>$year</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" class="btn">Update</button>
                </form>
            </div>
            
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <span>Mark Today's Attendance</span>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <div class="form-group" style="flex: 1;">
                                <label for="group_filter">Filter by Departments</label>
                                <select class="form-control" id="group_filter" onchange="filterMembers()">
                                    <option value="all">All Departments</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo $group['group_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="filter-btn" onclick="filterMembers()" style="margin-top: 25px;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="form-group">
                                <label for="member_id">Select Workers</label>
                                <select class="form-control" name="member_id" id="member_id" required>
                                    <option value="">-- Select Worker --</option>
                                    <?php foreach ($members as $member): 
                                        $group_class = strtolower(str_replace(' ', '-', $member['group_name']));
                                    ?>
                                        <option value="<?php echo $member['id']; ?>" data-group="<?php echo $member['class_group_id']; ?>">
                                            <?php echo $member['fullname']; ?> (<?php echo $member['group_name']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="mark_attendance" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Mark Present
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <span>Add New Worker</span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input type="text" class="form-control" name="fullname" id="fullname" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" name="phone" id="phone">
                            </div>
                            
                            <div class="form-group">
                                <label for="class_group_id">Department</label>
                                <select class="form-control" name="class_group_id" id="class_group_id" required>
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo $group['group_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="add_member" class="btn">
                                <i class="fas fa-user-plus"></i> Add Worker
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- NEW: Date selector for daily attendance -->
            <div class="card">
                <div class="card-header">
                    <span>Daily Attendance Records: <?php echo date('F j, Y', strtotime($selected_day)); ?></span>
                </div>
                <div class="card-body-list">
                    <form method="GET" class="date-selector">
                        <input type="hidden" name="page" value="dashboard">
                        <label for="attendance_day">Select Date:</label>
                        <input type="date" name="attendance_day" value="<?php echo $selected_day; ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                        <button type="submit" class="btn">Load</button>
                    </form>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Member</th>
                                <th>Department</th>
                                <th>Marked By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $record): 
                                $group_class = strtolower(str_replace(' ', '-', $record['group_name']));
                            ?>
                                <tr>
                                    <td><?php echo $record['formatted_time']; ?></td>
                                    <td><?php echo $record['member_name']; ?></td>
                                    <td><span class="group-badge" style="background: var(--group-<?php echo $group_class; ?>);"><?php echo $record['group_name']; ?></span></td>
                                    <td><?php echo $record['admin_name']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attendance)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;">No attendance records for this date</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card" id="attendance">
                <div class="card-header">
                    <span>Attendance Calendar: <?php echo date('F Y', strtotime($calendar_month)); ?></span>
                </div>
                <div class="card-body-list">
                    <form method="POST" class="month-selector">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <label for="calendar_month">Select Month:</label>
                        <select name="calendar_month" id="calendar_month">
                            <?php
                            // Generate month options for the last 6 months and next 6 months
                            $current = new DateTime();
                            $current->modify('-6 months');
                            
                            for ($i = 0; $i < 13; $i++) {
                                $month_value = $current->format('Y-m');
                                $month_name = $current->format('F Y');
                                $selected = ($month_value == $calendar_month) ? 'selected' : '';
                                echo "<option value=\"$month_value\" $selected>$month_name</option>";
                                $current->modify('+1 month');
                            }
                            ?>
                        </select>
                        <button type="submit">Show</button>
                    </form>
                    
                    <div class="calendar">
                        <?php
                        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($days as $day): ?>
                            <div class="calendar-header"><?php echo $day; ?></div>
                        <?php endforeach; ?>
                        
                        <?php
                        $first_day = $calendar_month . '-01';
                        $last_day = date('Y-m-t', strtotime($first_day));
                        $start_day = date('w', strtotime($first_day));
                        
                        // Fill empty days at the beginning
                        for ($i = 0; $i < $start_day; $i++) {
                            echo '<div class="calendar-day"></div>';
                        }
                        
                        // Create calendar days
                        $current_day = 1;
                        $total_days = date('t', strtotime($first_day));
                        $current_year = date('Y', strtotime($first_day));
                        $current_month_num = date('m', strtotime($first_day));
                        
                        while ($current_day <= $total_days) {
                            $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month_num, $current_day);
                            $day_of_week = date('w', strtotime($current_date));
                            
                            $attendance_count = isset($daily_attendance[$current_date]) ? $daily_attendance[$current_date] : 0;
                            $is_today = ($current_date == date('Y-m-d')) ? 'border: 2px solid var(--primary);' : '';
                            
                            $class = '';
                            if ($attendance_count > 0) {
                                $class = 'present-day';
                                $attendance_text = $attendance_count;
                            } else {
                                $attendance_text = '';
                            }
                            
                            echo '<div class="calendar-day ' . $class . '" style="' . $is_today . '">';
                            echo '<div>' . $current_day . '</div>';
                            if ($attendance_count > 0) {
                                echo '<div style="font-size:0.8rem;">' . $attendance_count . ' present</div>';
                            }
                            echo '</div>';
                            
                            $current_day++;
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="card" id="members">
                <div class="card-header">
                    <span>Worker's List</span>
                </div>
                <div class="card-body-list">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="search-worker" 
                               placeholder="Search workers by name, department, email, or phone" 
                               onkeyup="searchWorkers()">
                    </div>
                    
                    <table id="workers-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php foreach ($members as $member): 
                                $group_class = strtolower(str_replace(' ', '-', $member['group_name']));
                            ?>
                                <tr>
                                    <td><?php echo $member['id']; ?></td>
                                    <td><?php echo $member['fullname']; ?></td>
                                    <td><span class="group-badge" style="background: var(--group-<?php echo $group_class; ?>);"><?php echo $member['group_name']; ?></span></td>
                                    <td><?php echo $member['email'] ? $member['email'] : 'N/A'; ?></td>
                                    <td><?php echo $member['phone'] ? $member['phone'] : 'N/A'; ?></td>
                                   
                                    <td>
                                        <a href="?view_worker_attendance=1&worker_id=<?php echo $member['id']; ?>" 
                                        class="btn" style="margin-right:5px; margin-bottom: 15px;">
                                        View Attendance
                                        </a>
                                        <form method="POST" id="delete-form-<?php echo $member['id']; ?>" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                          <button type="button" onclick="confirmDelete(<?php echo $member['id']; ?>, <?php echo json_encode($member['fullname']); ?>)" 
                                                class="action-btn btn-danger">
                                                <i class="fa-solid fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No workers found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
             <?php if (is_superadmin()): ?>
                <div class="card" id="groups">
                <div class="card-header">
                    <span>Departments</span>
                     <span class="superadmin-badge">Super Admin Only</span>
                </div>
                <div class="card-body">
                    
                        <div class="card">
                            <div class="card-header">
                                <span>Add New Department</span>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <div class="form-group">
                                        <label for="group_name">Departmental Name</label>
                                        <input type="text" class="form-control" name="group_name" id="group_name" required>
                                    </div>
                                    <button type="submit" name="add_group" class="btn">
                                        <i class="fas fa-plus-circle"></i> Add Department
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                         <div class="card">
                            <div class="card-header" id="department">
                                <span>Departmental Statistics</span>
                            </div>
                            <div class="card-body">
                                <div class="group-list">
                                    <?php foreach ($groups as $group): 
                                        $group_class = strtolower(substr($group['group_name'], -1));
                                    ?>
                                    <div class="group-card <?php echo $group_class; ?>">
                                        <div class="group-name">
                                            <i class="fas fa-users"></i> <?php echo $group['group_name']; ?>
                                        </div>
                                            <div class="group-members-count">
                                            <?php echo $group['member_count']; ?> members
                                        </div>
                                        <form method="POST" style="margin-top: 15px;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <?php if ($group['member_count'] == 0): ?>
                                                <button type="submit" name="delete_group" class="btn btn-danger" style="width: 100%;">
                                                    <i class="fas fa-trash-alt"></i> Delete Group
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn" style="width: 100%; background: #ddd; cursor: not-allowed;" disabled>
                                                    Group has members
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    
                </div>
            </div>
              <?php endif; ?>
            <?php if (is_superadmin()): ?>
             <div class="card" id="members">
                <div class="card-header">
                    <span>Administrator Management</span>
                    <span class="superadmin-badge">Super Admin Only</span>
                </div>
                  <div class="card-body">
                        <div class="card" >
                            <div class="card-header">
                                <span>Add New Admin</span>
                            </div>
                            <div class="card-body">
                                <form method="POST" onsubmit="return validateAdminForm()">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" class="form-control" name="username" id="username" 
                                               required oninput="checkUsernameAvailability()">
                                        <div class="username-feedback" id="username-feedback">
                                            Username already taken!
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="fullname">Full Name</label>
                                        <input type="text" class="form-control" name="fullname" id="fullname" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control" name="password" id="password" required>
                                    </div>
                                    <button type="submit" name="add_admin" class="btn">
                                        <i class="fas fa-user-shield"></i> Add Administrator
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card" style="margin-top:30px;">
                            <div class="card-header" id="admins">
                             <h3>Admin List</h3>
                            </div>
                        <div class="card-body-list">
                         
                  <table>
    
                  <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Full Name</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
      <?php foreach ($admins as $admin): ?>
    <tr>
        <td><?php echo $admin['id']; ?></td>
        <td><?php echo $admin['username']; ?></td>
        <td><?php echo $admin['fullname']; ?></td>
        <td>
            <?php if ($admin['role'] === 'superadmin'): ?>
                <span class="superadmin-badge">Admin</span>
            <?php else: ?>
                Admin
            <?php endif; ?>
        </td>
        <td>
            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                    <button type="submit" name="delete_admin" class="action-btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </form>
            <?php else: ?>
                <span>Current User</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
    </tbody>
</table>                               
                </div>
            </div>
    
              
                     
                    </div>
                </div>
     </div>
   <?php endif; ?>       

            <div class="footer">
              <p>Love Ambassador Ministry &copy; <?php echo date('Y'); ?> | All rights reserved</p>

                <p style="margin-top: 10px;">
                    <a href="?page=public" class="btn btn-outline" style="margin-top: 10px;">
                        <i class="fas fa-eye"></i> View Public Attendance
                    </a>
                </p>
            </div>
        </div>
    <?php 
        }
    } 
    ?>
    
    <script>
        // Show notifications if any
        <?php if (!empty($notification)): ?>
            Swal.fire({
                icon: '<?php echo $notification['type']; ?>',
                title: '<?php echo ucfirst($notification['type']); ?>',
                text: '<?php echo $notification['message']; ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                background: 'var(--card-bg)',
                color: 'var(--text)'
            });
        <?php endif; ?>
        
        // Show login error if exists
        <?php if (isset($login_error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?php echo $login_error; ?>',
                background: 'var(--card-bg)',
                color: 'var(--text)'
            });
        <?php endif; ?>
        
        // Initialize username check on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkUsernameAvailability();
        });
    </script>
</body>
</html>
