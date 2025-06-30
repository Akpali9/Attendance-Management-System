<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS admins (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(50) NOT NULL,
        role ENUM('superadmin', 'admin') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS class_groups (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS members (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(50) NOT NULL,
        email VARCHAR(50),
        phone VARCHAR(20),
        class_group_id INT(6) UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_group_id) REFERENCES class_groups(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS attendance (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id INT(6) UNSIGNED NOT NULL,
        admin_id INT(6) UNSIGNED NOT NULL,
        attendance_date DATE NOT NULL,
        attendance_time TIME NOT NULL,
        FOREIGN KEY (member_id) REFERENCES members(id),
        FOREIGN KEY (admin_id) REFERENCES admins(id)
    )"
];

foreach ($tables as $sql) {
    $conn->query($sql);
}

// Insert default groups if none exist
$result = $conn->query("SELECT COUNT(*) as count FROM class_groups");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $groups = ['Video Production Department','Content Creation Department', 'Sound Engineering Department', 'Facility Maintenance Department', 'Resource production Department', 'Word Bank Department', 'Loveway Choir Department', 'Loveway Minstrels Department', 'LAM Theatre (Stage drama & motion picture)', 'Full House (spoken word & hip-hop', 'LAM Dance Department', 'Super Infants Department (3months - 1year)', 'Super Toddlers Department (2years - 3years)', 'Super Kids 2 Department (6years - 7years)','Super Kids 3 Department (8years - 10years)', 'Teens Church (14years - 16years)', 'Pre-Teens Department (11years - 13years)', 'Ushering Department', 'Greeters Department', 'Pastoral Aides Department', 'Protocols Department (Crowd Control Unit)', 'Altar Keepers Department', 'Sanctuary Keepers Department', 'Exterior Keepers Department', 'Church Admin Department', 'Ministry Information Department', 'Service Coordination Department', 'Brand Management Department', 'Royal Host Department', 'Real Friends Department', 'PCD Admin Department', 'Cell Minstry Department', 'Maturity OPerations Department', 'Community Outreach Department', 'Diplomatic Outreach Department', 'Healing Hands Medical Department', 'Sports & Fitness Department','Super Kids 1 Department (4years-5years)', 'Graphics, Animation & Projection Department', 'Livestreaming Department', 'Photography Department'];
    foreach ($groups as $group) {
        $conn->query("INSERT INTO class_groups (group_name) VALUES ('$group')");
    }
}

// Insert default superadmin if none exists
$result = $conn->query("SELECT COUNT(*) as count FROM admins");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password, fullname, role) 
                 VALUES ('superadmin', '$hashed_password', 'Super Admin', 'superadmin')");
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        $fullname = $conn->real_escape_string($_POST['fullname']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $class_group_id = intval($_POST['class_group_id']);
        
        $sql = "INSERT INTO members (fullname, email, phone, class_group_id) 
                VALUES ('$fullname', '$email', '$phone', $class_group_id)";
        $conn->query($sql);
    }
    
    if (isset($_POST['delete_member'])) {
        $member_id = intval($_POST['member_id']);
        $sql = "DELETE FROM members WHERE id = $member_id";
        $conn->query($sql);
    }
    
    if (isset($_POST['mark_attendance'])) {
        $member_id = intval($_POST['member_id']);
        
        if (!already_attended_today($member_id)) {
            $admin_id = $_SESSION['admin_id'];
            $date = date('Y-m-d');
            $time = date('H:i:s');
            
            $sql = "INSERT INTO attendance (member_id, admin_id, attendance_date, attendance_time) 
                    VALUES ($member_id, $admin_id, '$date', '$time')";
            $conn->query($sql);
            $attendance_success = "Attendance marked successfully!";
        } else {
            $attendance_error = "This member has already been marked present today!";
        }
    }
    
    if (isset($_POST['add_admin'])) {
        if (is_superadmin()) {
            $username = $conn->real_escape_string($_POST['username']);
            $fullname = $conn->real_escape_string($_POST['fullname']);
            $password = $conn->real_escape_string($_POST['password']);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO admins (username, password, fullname) 
                    VALUES ('$username', '$hashed_password', '$fullname')";
            $conn->query($sql);
        }
    }
    
    if (isset($_POST['delete_admin'])) {
        if (is_superadmin()) {
            $admin_id = intval($_POST['admin_id']);
            if ($admin_id != $_SESSION['admin_id']) {
                $sql = "DELETE FROM admins WHERE id = $admin_id";
                $conn->query($sql);
            }
        }
    }
    
    if (isset($_POST['add_group'])) {
        $group_name = $conn->real_escape_string($_POST['group_name']);
        $sql = "INSERT INTO class_groups (group_name) VALUES ('$group_name')";
        $conn->query($sql);
    }
    
    if (isset($_POST['delete_group'])) {
        $group_id = intval($_POST['group_id']);
        $default_group = $conn->query("SELECT id FROM class_groups ORDER BY id LIMIT 1")->fetch_assoc();
        if ($default_group) {
            $default_id = $default_group['id'];
            $conn->query("UPDATE members SET class_group_id = $default_id WHERE class_group_id = $group_id");
        }
        $sql = "DELETE FROM class_groups WHERE id = $group_id";
        $conn->query($sql);
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
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
    }
}

// Get attendance records for this month
$current_month = date('Y-m');
$attendance = [];
$result = $conn->query("SELECT a.*, m.fullname AS member_name, g.group_name, ad.fullname AS admin_name 
                        FROM attendance a
                        JOIN members m ON a.member_id = m.id
                        JOIN class_groups g ON m.class_group_id = g.id
                        JOIN admins ad ON a.admin_id = ad.id
                        WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = '$current_month'
                        ORDER BY a.attendance_date DESC, a.attendance_time DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
}

// Get attendance records for public view
$public_attendance = [];
$result = $conn->query("SELECT m.fullname AS member_name, g.group_name,
                        DATE_FORMAT(a.attendance_date, '%M %d, %Y') AS formatted_date, 
                        DATE_FORMAT(a.attendance_time, '%h:%i %p') AS formatted_time
                        FROM attendance a
                        JOIN members m ON a.member_id = m.id
                        JOIN class_groups g ON m.class_group_id = g.id
                        ORDER BY a.attendance_date DESC, a.attendance_time DESC
                        LIMIT 50");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $public_attendance[] = $row;
    }
}

// Calculate statistics
$total_members = count($members);
$total_groups = count($groups);
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];
$today_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = CURDATE()")->fetch_assoc()['count'];
$monthly_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'")->fetch_assoc()['count'];

// Get daily attendance for the current month
$daily_attendance = [];
$result = $conn->query("SELECT attendance_date, COUNT(*) as count 
                        FROM attendance 
                        WHERE DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'
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
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Date', 'Member Name', 'Group', 'Time', 'Marked By'));
    
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

// Theme handling
$theme = 'light';
if (isset($_POST['theme_toggle'])) {
    $theme = ($_POST['theme'] == 'light') ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
} elseif (isset($_SESSION['theme'])) {
    $theme = $_SESSION['theme'];
} else {
    $_SESSION['theme'] = $theme;
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Ambassador Attendance</title>
    <link rel="icon" href="./img/lam-logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
  <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3f37c9;
            --secondary: #4cc9f0;
            --success: #2ec4b6;
            --warning: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-bg: #ffffff;
            --body-bg: #f0f2f5;
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

        .public-card-body {
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

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
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
                  <p>Attendance records of all Church Workers</p>
                </div>
            </div>
            
            <div class="container">
                <div class="public-stats">
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $total_members; ?></div>
                        <div class="public-stat-label">Total Members</div>
                    </div>
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $total_attendance; ?></div>
                        <div class="public-stat-label">Attendance Records</div>
                    </div>
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $today_attendance; ?></div>
                        <div class="public-stat-label">Today's Attendance</div>
                    </div>
                    <div class="public-stat">
                        <div class="public-stat-value"><?php echo $total_groups; ?></div>
                        <div class="public-stat-label">Departments</div>
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
                                    foreach (array_slice($group_members, 0, 5) as $member): 
                                    ?>
                                        <div class="team-member">
                                            <div class="member-avatar-small team-<?php echo $group_class; ?>">
                                                <?php echo substr($member['fullname'], 0, 1); ?>
                                            </div>
                                            <div><?php echo $member['fullname']; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($group_members) > 5): ?>
                                        <div class="text-center" style="margin-top: 10px; font-size: 0.9rem;">
                                            +<?php echo count($group_members) - 5; ?> more members
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="public-card">
                    <div class="public-card-header">
                        <i class="fas fa-history"></i> Recent Attendance Records
                    </div>
                    <div class="public-card-body">
                        <?php if (!empty($public_attendance)): ?>
                            <?php foreach ($public_attendance as $record): 
                                $group_class = strtolower(str_replace(' ', '-', $record['group_name']));
                            ?>
                                <div class="attendance-record">
                                    <div class="attendance-avatar" style="background: var(--group-<?php echo $group_class; ?>);">
                                        <?php echo substr($record['member_name'], 0, 1); ?>
                                    </div>
                                    <div class="attendance-info">
                                        <div class="attendance-name"><?php echo $record['member_name']; ?>
                                            <span class="group-badge group-<?php echo $group_class; ?>" style="background: var(--group-<?php echo $group_class; ?>);"><?php echo $record['group_name']; ?></span>
                                        </div>
                                        <div class="attendance-time"><?php echo $record['formatted_date']; ?> at <?php echo $record['formatted_time']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No attendance records found</p>
                        <?php endif; ?>
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
                
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
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
    ?>
        <div class="header">
            <div class="container header-content">
                <div class="logo">
                       <img src="./img/logo.png" style="height: inherit; width: 50px; margin-left: 20px;">

                </div>
                
                <div class="nav">
                    <a href="index.php" class="active">Dashboard</a>
                    <a href="#members">Members</a>
                    <a href="#attendance">Attendance</a>
                    <a href="#groups">Department</a>
                    <a href="#admins">Admins</a>
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
                <div>
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo $_SESSION['fullname']; ?>! Here's your system overview.</p>
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
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $monthly_attendance; ?></div>
                    <div class="stat-label">This Month</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_attendance; ?></div>
                    <div class="stat-label">Today Present</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_members - $today_attendance; ?></div>
                    <div class="stat-label">Today Absent</div>
                </div>
            </div>
            
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <span>Mark Today's Attendance</span>
                    </div>
                    <div class="card-body">
                        <?php if (isset($attendance_success)): ?>
                            <div class="alert alert-success"><?php echo $attendance_success; ?></div>
                        <?php endif; ?>
                        <?php if (isset($attendance_error)): ?>
                            <div class="alert alert-danger"><?php echo $attendance_error; ?></div>
                        <?php endif; ?>
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
            
            <div class="card">
                <div class="card-header">
                    <span>Monthly Attendance Calendar: <?php echo date('F Y'); ?></span>
                </div>
                <div class="card-body">
                    <div class="calendar">
                        <?php
                        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($days as $day): ?>
                            <div class="calendar-header"><?php echo $day; ?></div>
                        <?php endforeach; ?>
                        
                        <?php
                        $first_day = date('Y-m-01');
                        $last_day = date('Y-m-t');
                        $start_day = date('w', strtotime($first_day));
                        
                        // Fill empty days at the beginning
                        for ($i = 0; $i < $start_day; $i++) {
                            echo '<div class="calendar-day"></div>';
                        }
                        
                        // Create calendar days
                        $current_day = 1;
                        $total_days = date('t');
                        $current_month = date('m');
                        $current_year = date('Y');
                        
                        while ($current_day <= $total_days) {
                            $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $current_day);
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
            
            <div class="card" id="attendance">
                <div class="card-header">
                    <span>Recent Attendance Records</span>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
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
                                    <td><?php echo $record['attendance_date']; ?></td>
                                    <td><?php echo $record['attendance_time']; ?></td>
                                    <td><?php echo $record['member_name']; ?></td>
                                    <td><span class="group-badge" style="background: var(--group-<?php echo $group_class; ?>);"><?php echo $record['group_name']; ?></span></td>
                                    <td><?php echo $record['admin_name']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attendance)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;">No attendance records found for this month</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card" id="members">
                <div class="card-header">
                    <span>Worker's List</span>
                </div>
                <div class="card-body">
                    <table>
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
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                            <button type="submit" name="delete_member" class="action-btn btn-danger">
                                                <i class="fas fa-trash-alt"></i> Delete
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
            <div class="card" id="admins">
                <div class="card-header">
                    <span>Administrator Management</span>
                    <span class="superadmin-badge">Super Admin Only</span>
                </div>
                <div class="card-body">
                    <h3>Add New Admin</h3>
                    <form method="POST" style="margin-bottom: 30px;">
                        <div class="grid">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" name="username" id="username" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input type="text" class="form-control" name="fullname" id="fullname" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                        </div>
                        <button type="submit" name="add_admin" class="btn btn-success">
                            <i class="fas fa-user-shield"></i> Add Administrator
                        </button>
                    </form>
                    
                    <h3>Admin List</h3>
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
                                            <span class="superadmin-badge">Super Admin</span>
                                        <?php else: ?>
                                            Admin
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <form method="POST" style="display:inline;">
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
            <?php endif; ?>
            
            <div class="footer">
              <p>Love Ambassador Ministry &copy; <?php echo date('Y'); ?> | All rights reserved</p>

                <p style="margin-top: 10px;">
                    <a href="?page=public" class="btn btn-outline">
                        <i class="fas fa-eye"></i> View Public Attendance
                    </a>
                </p>
            </div>
        </div>
    <?php } ?>
</body>
</html>
