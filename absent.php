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
if (isset($_GET['export']) && $_GET['export'] == 'absent_csv') {
    $export_date = isset($_GET['export_date']) ? $_GET['export_date'] : date('Y-m-d');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Absent_Workers_' . $export_date . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Name', 'Department', 'Email', 'Phone'));
    
    // Query to find workers who didn't mark attendance on the selected day
    $absent_query = "SELECT m.fullname, g.group_name, m.email, m.phone 
                     FROM members m 
                     JOIN class_groups g ON m.class_group_id = g.id 
                     WHERE m.id NOT IN (
                         SELECT member_id 
                         FROM attendance 
                         WHERE attendance_date = '$export_date'
                     ) 
                     ORDER BY g.group_name, m.fullname";
    
    $result = $conn->query($absent_query);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
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
    <title>Absent Workers | Love Ambassador Attendance</title>
    <link rel="icon" href="./img/lam-logo.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" 
      integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" 
      crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-<?php echo $theme; ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
</head>
<body>
        <div class="absent-export-container" style="margin: 20px 0; padding: 15px; background: var(--card-bg); border-radius: 8px; border: 1px solid var(--light-gray);">
    <h3 style="margin-bottom: 15px;"><img src="./img/lam-logo.jpg" style="height: 40px; width:40px;">Export Absent Workers</h3>
    <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="page" value="dashboard">
        <div class="form-group" style="flex: 1; min-width: 200px;">
            <label for="export_date">Select Date</label>
            <input type="date" class="form-control" name="export_date" id="export_date" 
                   value="<?php echo isset($_GET['export_date']) ? $_GET['export_date'] : date('Y-m-d'); ?>"
                   max="<?php echo date('Y-m-d'); ?>">
        </div>
        <button type="submit" name="export" value="absent_csv" class="btn" style="background: var(--warning);">
            <i class="fas fa-file-export"></i> Export Absent Workers
        </button>
    </form>
    <?php
    // Show absent count when date is selected
    if (isset($_GET['export_date'])) {
        $export_date = $_GET['export_date'];
        $absent_count_query = "SELECT COUNT(*) as count 
                               FROM members m 
                               WHERE m.id NOT IN (
                                   SELECT member_id 
                                   FROM attendance 
                                   WHERE attendance_date = '$export_date'
                               )";
        $absent_count_result = $conn->query($absent_count_query);
        $absent_count = $absent_count_result->fetch_assoc()['count'];
        
        echo "<p style='margin-top: 15px;'>$absent_count workers were absent on " . date('F j, Y', strtotime($export_date)) . "</p>";
    }
    ?>
</div>

<style>
    
:root {

      --warning: #f72585;
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

.absent-export-container {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.absent-export-container h3 {
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.absent-export-container h3:before {
   
    font-size: 1.5rem;
}
</style>


</body>
</html>