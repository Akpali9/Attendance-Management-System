<?php
// Database configuration
$host = 'localhost';
$dbname = 'attendance_system';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        member ENUM('Yes', 'No') NOT NULL,
        invited_by VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        attendee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (attendee_id) REFERENCES attendees(id) ON DELETE CASCADE
    )");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_event'])) {
        // Create new event
        $title = trim($_POST['event_title']);
        
        if (empty($title)) {
            $message = "Event title cannot be empty!";
            $message_type = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO events (title) VALUES (?)");
            if ($stmt->execute([$title])) {
                $message = "Event created successfully!";
            } else {
                $message = "Error creating event.";
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['add_attendee'])) {
        // Add attendee
        $event_id = $_POST['event_id'];
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $member = $_POST['member'];
        $invited_by = trim($_POST['invited_by']);
        $attendance_date = $_POST['attendance_date'];
        
        // Validate inputs
        if (empty($name) || empty($phone) || empty($email) || empty($member) || empty($invited_by)) {
            $message = "All fields are required!";
            $message_type = 'error';
        } else {
            // Check if attendee already exists
            $stmt = $pdo->prepare("SELECT id FROM attendees WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            $existing_attendee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_attendee) {
                $attendee_id = $existing_attendee['id'];
            } else {
                // Create new attendee
                $stmt = $pdo->prepare("INSERT INTO attendees (name, phone, email, member, invited_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $phone, $email, $member, $invited_by])) {
                    $attendee_id = $pdo->lastInsertId();
                } else {
                    $message = "Error adding attendee.";
                    $message_type = 'error';
                }
            }
            
            // Record attendance
            $stmt = $pdo->prepare("INSERT INTO attendance (event_id, attendee_id, attendance_date) VALUES (?, ?, ?)");
            if ($stmt->execute([$event_id, $attendee_id, $attendance_date])) {
                $message = "Attendance recorded successfully!";
            } else {
                $message = "Error recording attendance.";
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['mark_attendance'])) {
        // Mark attendance for existing attendee
        $event_id = $_POST['event_id'];
        $attendee_id = $_POST['attendee_id'];
        $attendance_date = $_POST['attendance_date'];
        
        // Check if attendance already recorded for this date
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE event_id = ? AND attendee_id = ? AND attendance_date = ?");
        $stmt->execute([$event_id, $attendee_id, $attendance_date]);
        $existing_attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_attendance) {
            $stmt = $pdo->prepare("INSERT INTO attendance (event_id, attendee_id, attendance_date) VALUES (?, ?, ?)");
            if ($stmt->execute([$event_id, $attendee_id, $attendance_date])) {
                $message = "Attendance marked successfully!";
            } else {
                $message = "Error marking attendance.";
                $message_type = 'error';
            }
        } else {
            $message = "Attendance already recorded for this date.";
            $message_type = 'info';
        }
    } elseif (isset($_POST['export_csv'])) {
        // Export to CSV
        $event_id = $_POST['event_id'];
        
        // Fixed query - using correct column references
        $stmt = $pdo->prepare("SELECT e.title, a.attendance_date, at.name, at.phone, at.email, at.member, at.invited_by 
                              FROM events e 
                              JOIN attendance a ON e.id = a.event_id 
                              JOIN attendees at ON a.attendee_id = at.id 
                              WHERE e.id = ? 
                              ORDER BY a.attendance_date, at.name");
        $stmt->execute([$event_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($records) > 0) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=attendance_export.csv');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, array('Event Title', 'Attendance Date', 'Name', 'Phone', 'Email', 'Member', 'Invited By'));
            
            foreach ($records as $record) {
                fputcsv($output, $record);
            }
            fclose($output);
            exit();
        } else {
            $message = "No attendance records to export for this event.";
            $message_type = 'info';
        }
    } elseif (isset($_POST['delete_attendance'])) {
        // Delete attendance record
        $attendance_id = $_POST['delete_id'];
        $event_id = $_POST['event_id'];
        
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        if ($stmt->execute([$attendance_id])) {
            $message = "Attendance record deleted successfully!";
        } else {
            $message = "Error deleting attendance record.";
            $message_type = 'error';
        }
    }
}

// Get all events
$events = $pdo->query("SELECT * FROM events ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get selected event ID
$selected_event_id = isset($_GET['event_id']) ? $_GET['event_id'] : (count($events) > 0 ? $events[0]['id'] : null);

// Get attendance records for selected event
$attendance_records = [];
if ($selected_event_id) {
    // Fixed query - using correct column references
    $stmt = $pdo->prepare("SELECT a.id as attendance_id, a.attendance_date, at.id as attendee_id, at.name, at.phone, at.email, at.member, at.invited_by 
                          FROM attendance a 
                          JOIN attendees at ON a.attendee_id = at.id 
                          WHERE a.event_id = ? 
                          ORDER BY a.attendance_date DESC, at.name");
    $stmt->execute([$selected_event_id]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all attendees for the mark attendance form
$all_attendees = $pdo->query("SELECT * FROM attendees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get unique attendance dates for the selected event
$attendance_dates = [];
if ($selected_event_id) {
    $stmt = $pdo->prepare("SELECT DISTINCT attendance_date FROM attendance WHERE event_id = ? ORDER BY attendance_date DESC");
    $stmt->execute([$selected_event_id]);
    $attendance_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background:white;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        
        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
            }
                .panela{
                max-height: 79vh;
                }
        }
        
        .panela, .panel {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .panela{
            height: 128vh;
        }
        
        .header {
            background: rgb(143, 135, 27);;
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .header h1 {
            font-weight: 600;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 25px;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section h2 {
            color:rgb(143, 135, 27);
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        input, select {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: rgb(143, 135, 27);;
            box-shadow: 0 0 0 2px rgba(78, 84, 200, 0.2);
        }
        
        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: rgb(143, 135, 27);;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3f44ae;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .btn-success {
            background: #4cd964;
            color: white;
        }
        
        .btn-success:hover {
            background: #3cb054;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .attendee-list {
            margin-top: 30px;
     
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
             
         max-height: 30vh;
        }
        
        .attendee-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .attendee-info {
            flex: 1;
        }
        
        .attendee-name {
            font-weight: 600;
            color: #333;
        }
        
        .attendee-details {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .attendance-date {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        
        .delete-btn {
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .delete-btn:hover {
            background: #ff2e43;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification.success {
            background: #4cd964;
        }
        
        .notification.error {
            background: #ff4757;
        }
        
        .notification.info {
            background: #2f80ed;
        }
        
        .events-list {
            margin-top: 20px;
          overflow-y: auto ;
         max-height: 30vh;
        }
        
        .event-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .event-item:hover {
            background: #e9ecef;
        }
        
        .event-item.active {
            background: rgb(143, 135, 27);;
            color: white;
        }
        
        .event-title {
            font-weight: 600;
            font-size: 18px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: rgb(143, 135, 27);;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .date-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .date-filter label {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .filter-btn {
            padding: 10px 15px;
            background: rgb(143, 135, 27);;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php if (!empty($message)): ?>
        <div class="notification <?php echo $message_type; ?>" id="notification">
            <i class="fas fa-<?php echo $message_type == 'error' ? 'exclamation-circle' : ($message_type == 'info' ? 'info-circle' : 'check-circle'); ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="container">
        <div class="panela">
            <div class="header"  style="background-color: white;">
                   <img src="./img/logo.png" style="height: 30%; width: 150px; margin-left: 20px;">
                    <h1 style="color: black">Love Ambassadors Events Registrations</h1>
            </div>
            <div class="content">
                <div class="form-section">
                    <h2><i class="fas fa-plus-circle"></i> Create New Event</h2>
                    <form method="POST">
                        <div class="form-group">
                           <b> <label for="event_title">Event Title</label></b>
                            <input type="text" id="event_title" name="event_title" placeholder="Enter event title" required>
                        </div>
                        <button type="submit" name="create_event" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Event
                        </button>
                    </form>
                </div>
                
                <div class="form-section">
                    <h2><i class="fas fa-calendar"></i> Your Events</h2>
                    <div class="events-list">
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                                <div class="event-item <?php echo $event['id'] == $selected_event_id ? 'active' : ''; ?>">
                                    <a href="?event_id=<?php echo $event['id']; ?>" style="text-decoration: none; color: inherit;">
                                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div class="event-date">Created: <?php echo date('M j, Y', strtotime($event['created_at'])); ?></div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No events created yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel">
            <div class="header">
                <h1>
                    <?php if ($selected_event_id): ?>
                        <?php 
                        $event_title = '';
                        foreach ($events as $event) {
                            if ($event['id'] == $selected_event_id) {
                                $event_title = $event['title'];
                                break;
                            }
                        }
                        echo htmlspecialchars($event_title); 
                        ?>
                    <?php else: ?>
                        Select an Event
                    <?php endif; ?>
                </h1>
                <p>
                    <?php if ($selected_event_id): ?>
                       
                    <?php else: ?>
                        Please create an event first
                    <?php endif; ?>
                </p>
            </div>
            <div class="content">
                <?php if ($selected_event_id): ?>
                    <div class="stats" style="margin-bottom:40px">
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php
                                $unique_attendees = [];
                                foreach ($attendance_records as $record) {
                                    if (!in_array($record['attendee_id'], $unique_attendees)) {
                                        $unique_attendees[] = $record['attendee_id'];
                                    }
                                }
                                echo count($unique_attendees);
                                ?>
                            </div>
                            <div class="stat-label">Registered Attendee</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($attendance_records); ?></div>
                            <div class="stat-label">Total Attendance Records</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-user-plus"></i> Add New Attendee</h2>
                        <form method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo date('Y-m-d'); ?>">
                            
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" placeholder="Enter attendee's full name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" placeholder="Enter phone number" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" placeholder="Enter email address" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="member">Are you a Member? *</label>
                                <select id="member" name="member" required>
                                    <option value="">Select an option</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="invited_by">Invited By *</label>
                                <input type="text" id="invited_by" name="invited_by" placeholder="Who invited this attendee?" required>
                            </div>
                            
                            <button type="submit" name="add_attendee" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add & Record Attendance
                            </button>
                        </form>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-check-circle"></i> Mark Attendance for Existing Attendee</h2>
                        <form method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                            
                            <div class="form-group">
                                <label for="attendee_id">Select Attendee *</label>
                                <select id="attendee_id" name="attendee_id" required>
                                    <option value="">Select an attendee</option>
                                    <?php foreach ($all_attendees as $attendee): ?>
                                        <option value="<?php echo $attendee['id']; ?>">
                                            <?php echo htmlspecialchars($attendee['name'] . ' - ' . $attendee['email']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="attendance_date">Attendance Date *</label>
                                <input type="date" id="attendance_date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <button type="submit" name="mark_attendance" class="btn btn-success">
                                <i class="fas fa-check"></i> Mark Attendance
                            </button>
                        </form>
                    </div>
                    
                    <div class="form-section">
                        <h2><i class="fas fa-list"></i> Attendance Records</h2>
                        
                        <div class="date-filter">
                            <label>Filter by Date:</label>
                            <select id="date_filter">
                                <option value="">All Dates</option>
                                <?php foreach ($attendance_dates as $date): ?>
                                    <option value="<?php echo $date; ?>"><?php echo date('M j, Y', strtotime($date)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="filter-btn" onclick="filterRecords()">Apply Filter</button>
                        </div>
                        
                        <div class="attendee-list">
                            <?php if (count($attendance_records) > 0): ?>
                                <?php foreach ($attendance_records as $record): ?>
                                    <div class="attendee-item" data-date="<?php echo $record['attendance_date']; ?>">
                                        <div class="attendee-info">
                                            <div class="attendee-name"><?php echo htmlspecialchars($record['name']); ?></div>
                                            <div class="attendee-details">
                                                <?php echo htmlspecialchars($record['phone']); ?> • 
                                                <?php echo htmlspecialchars($record['email']); ?> • 
                                                Member: <?php echo $record['member']; ?> • 
                                                Invited by: <?php echo htmlspecialchars($record['invited_by']); ?>
                                            </div>
                                            <div class="attendance-date">
                                                Attendance Date: <?php echo date('M j, Y', strtotime($record['attendance_date'])); ?>
                                            </div>
                                        </div>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo $record['attendance_id']; ?>">
                                            <button type="submit" name="delete_attendance" class="delete-btn" onclick="return confirm('Are you sure you want to delete this attendance record?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No attendance records yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="buttons">
                        <form method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                            <button type="submit" name="export_csv" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>Please create and select an event to manage attendance.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterRecords() {
            const dateFilter = document.getElementById('date_filter').value;
            const records = document.querySelectorAll('.attendee-item');
            
            records.forEach(record => {
                if (!dateFilter || record.getAttribute('data-date') === dateFilter) {
                    record.style.display = 'flex';
                } else {
                    record.style.display = 'none';
                }
            });
        }
        
        // Hide notification after 5 seconds
        setTimeout(() => {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>
