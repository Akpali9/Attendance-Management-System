<?php
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(50) NOT NULL,
    role ENUM('superadmin', 'admin') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS class_groups (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS members (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(50) NOT NULL,
    email VARCHAR(50),
    phone VARCHAR(20),
    class_group_id INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_group_id) REFERENCES class_groups(id)
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT(6) UNSIGNED NOT NULL,
    admin_id INT(6) UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME NOT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (admin_id) REFERENCES admins(id)
)";
$conn->query($sql);

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
                 VALUES ('superadmin', '$hashed_password', 'Admin', 'superadmin')");
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
        $admin_id = $_SESSION['admin_id'];
        $date = date('Y-m-d');
        $time = date('H:i:s');
        
        $sql = "INSERT INTO attendance (member_id, admin_id, attendance_date, attendance_time) 
                VALUES ($member_id, $admin_id, '$date', '$time')";
        $conn->query($sql);
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
            // Prevent superadmin from deleting themselves
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
        // Move members to default group before deletion
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

// Get attendance records
$attendance = [];
$result = $conn->query("SELECT a.*, m.fullname AS member_name, g.group_name, ad.fullname AS admin_name 
                        FROM attendance a
                        JOIN members m ON a.member_id = m.id
                        JOIN class_groups g ON m.class_group_id = g.id
                        JOIN admins ad ON a.admin_id = ad.id
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
$total_attendance = count($attendance);
$today_attendance = 0;
foreach ($attendance as $record) {
    if ($record['attendance_date'] == date('Y-m-d')) {
        $today_attendance++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Ambassador Attendance</title>
    <link rel="icon" href="./img/lam-logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                
                <div class="group-list">
                    <?php foreach ($groups as $group): 
                        $group_class = strtolower(substr($group['group_name'], -1));
                    ?>
                    <div class="group-card <?php echo $group_class; ?>">
                        <div class="group-name">
                            <i class="fas fa-users"></i> <?php echo $group['group_name']; ?>
                            <span class="group-badge"><?php echo $group['member_count']; ?> members</span>
                        </div>
                        <div class="group-members">
                            <?php 
                            $group_members = array_filter($members, function($m) use ($group) {
                                return $m['class_group_id'] == $group['id'];
                            });
                            foreach (array_slice($group_members, 0, 5) as $member): 
                            ?>
                                <div class="member-item <?php echo $group_class; ?>">
                                    <div class="member-avatar"><?php echo substr($member['fullname'], 0, 1); ?></div>
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
                
                <div class="public-card">
                    <div class="public-card-header">
                        <i class="fas fa-history"></i> Recent Attendance Records
                    </div>
                    <div class="public-card-body">
                        <?php if (!empty($public_attendance)): ?>
                            <?php foreach ($public_attendance as $record): ?>
                                <div class="attendance-record">
                                    <div class="attendance-avatar">
                                        <?php echo substr($record['member_name'], 0, 1); ?>
                                    </div>
                                    <div class="attendance-info">
                                        <div class="attendance-name"><?php echo $record['member_name']; ?>
                                            <span class="group-badge"><?php echo $record['group_name']; ?></span>
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
                <div class="logo"style=" margin-right: 20px;">
                    <img src="./img/logo.png" style="height: inherit; width: 50px; margin-left: 20px;">
                </div>
                
                <div class="nav"style=" padding-right: 20px;">
                    <a href="index.php" class="active">Home</a>
                    <a href="#members">Members</a>
                    <a href="#attendance">Attendance</a>
                    <a href="#groups">Departments</a>
                    <a href="#admins">Admins</a>
                </div>
                
                <div class="auth-info">
                    <div class="user-info"style=" margin-right: 3px;">
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
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['fullname']; ?>! Here's your system overview.</p>
            </div>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_members; ?></div>
                    <div class="stat-label">Total Members</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_attendance; ?></div>
                    <div class="stat-label">Attendance Records</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_attendance; ?></div>
                    <div class="stat-label">Today's Attendance</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_groups; ?></div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
            
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <span>Mark Today's Attendance</span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="member_id">Select Worker's Name</label>
                                <select class="form-control" name="member_id" id="member_id" required>
                                    <option value="">-- Select Worker --</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
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
                                <i class="fas fa-user-plus"></i> Add Church Worker
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="card" id="attendance">
                <div class="card-header">
                    <span>Recent Attendance</span>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Worker</th>
                                <th>Department</th>
                                <th>Marked By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $record): ?>
                                <tr>
                                    <td><?php echo $record['attendance_date']; ?></td>
                                    <td><?php echo $record['attendance_time']; ?></td>
                                    <td><?php echo $record['member_name']; ?></td>
                                    <td><span class="group-badge"><?php echo $record['group_name']; ?></span></td>
                                    <td><?php echo $record['admin_name']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attendance)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;">No attendance records found</td>
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
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?php echo $member['id']; ?></td>
                                    <td><?php echo $member['fullname']; ?></td>
                                    <td><span class="group-badge"><?php echo $member['group_name']; ?></span></td>
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
                                <script>
                                  
                                </script>
                            <?php endforeach; ?>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No members found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card" id="groups">
                <div class="card-header">
                    <span>Departments</span>
                </div>
                <div class="card-body">
                    <div class="grid">
                        <div class="card">
                            <div class="card-header">
                                <span>Add Department</span>
                            </div>
                            <div class="card-body">
                                <form method="POST">
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
                            <div class="card-header">
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
                                            <span class="group-badge"><?php echo $group['member_count']; ?> members</span>
                                        </div>
                                        <form method="POST" style="margin-top: 15px;">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <?php if ($group['member_count'] == 0): ?>
                                                <button type="submit" name="delete_group" class="btn btn-danger" style="width: 100%;">
                                                    <i class="fas fa-trash-alt"></i> Delete Department
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
                        <button type="submit" name="add_admin" class="btn btn-superadmin">
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
