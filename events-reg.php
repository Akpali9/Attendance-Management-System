<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'event_registration');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS event_guests (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        invite_code VARCHAR(20),
        invited_by VARCHAR(100),
        registration_number VARCHAR(20) NOT NULL UNIQUE,
        member_type ENUM('first-time', 'regular') DEFAULT 'first-time',
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS event_invites (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invite_code VARCHAR(20) NOT NULL UNIQUE,
        inviter_name VARCHAR(100) NOT NULL,
        max_uses INT(6) DEFAULT 1,
        times_used INT(6) DEFAULT 0,
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        error_log("Error creating table: " . $conn->error);
    }
}

// Insert some sample invite codes if none exist
$result = $conn->query("SELECT COUNT(*) as count FROM event_invites");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $sample_invites = [
        ['EVENT2024', 'John Smith', 5],
        ['VIPGUEST', 'Sarah Johnson', 3],
        ['WELCOME25', 'Michael Brown', 10]
    ];
    
    $stmt = $conn->prepare("INSERT INTO event_invites (invite_code, inviter_name, max_uses) VALUES (?, ?, ?)");
    foreach ($sample_invites as $invite) {
        $stmt->bind_param("ssi", $invite[0], $invite[1], $invite[2]);
        $stmt->execute();
    }
    $stmt->close();
}

// Handle form submission
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = htmlspecialchars($_POST['fullname']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone']);
    $invite_code = isset($_POST['invite_code']) ? $_POST['invite_code'] : '';
    
    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id, member_type, registration_number FROM event_guests WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing_guest = $result->fetch_assoc();
        $notification = "<div class='notification warning'>You're already registered! Your registration number is: " . $existing_guest['registration_number'] . "</div>";
    } else {
        // Validate invite code if provided
        $invited_by = null;
        if (!empty($invite_code)) {
            $invite_stmt = $conn->prepare("SELECT inviter_name, times_used, max_uses FROM event_invites WHERE invite_code = ?");
            $invite_stmt->bind_param("s", $invite_code);
            $invite_stmt->execute();
            $invite_result = $invite_stmt->get_result();
            
            if ($invite_result->num_rows > 0) {
                $invite_data = $invite_result->fetch_assoc();
                if ($invite_data['times_used'] < $invite_data['max_uses']) {
                    $invited_by = $invite_data['inviter_name'];
                    
                    // Update invite usage count
                    $update_stmt = $conn->prepare("UPDATE event_invites SET times_used = times_used + 1 WHERE invite_code = ?");
                    $update_stmt->bind_param("s", $invite_code);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    $notification = "<div class='notification error'>This invite code has reached its maximum usage limit.</div>";
                }
            } else {
                $notification = "<div class='notification error'>Invalid invite code. Please check and try again.</div>";
            }
            $invite_stmt->close();
        }
        
        if (empty($notification)) {
            // Generate registration number
            $registration_number = "EVT" . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Determine member type (for demo purposes, we'll randomly assign)
            $member_type = (rand(0, 1) == 1) ? 'regular' : 'first-time';
            
            $insert_stmt = $conn->prepare("INSERT INTO event_guests (fullname, email, phone, invite_code, invited_by, registration_number, member_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssssss", $fullname, $email, $phone, $invite_code, $invited_by, $registration_number, $member_type);
            
            if ($insert_stmt->execute()) {
                $notification = "<div class='notification success'>Registration successful! Your registration number is: " . $registration_number . "</div>";
            } else {
                $notification = "<div class='notification error'>Registration failed. Please try again.</div>";
            }
            $insert_stmt->close();
        }
    }
    $check_stmt->close();
}

// Get statistics for display
$total_registrations = $conn->query("SELECT COUNT(*) as count FROM event_guests")->fetch_assoc()['count'];
$first_time_guests = $conn->query("SELECT COUNT(*) as count FROM event_guests WHERE member_type = 'first-time'")->fetch_assoc()['count'];
$regular_guests = $conn->query("SELECT COUNT(*) as count FROM event_guests WHERE member_type = 'regular'")->fetch_assoc()['count'];
$invited_guests = $conn->query("SELECT COUNT(*) as count FROM event_guests WHERE invited_by IS NOT NULL")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration</title>
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
            --body-bg: #f8f9fa;
            --text: #333333;
            --header-bg: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        .btn {
            display: inline-block;
            padding: 10px 20px;
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

        .btn-success {
            background: var(--success);
        }

        .btn-success:hover {
            background: #1a936f;
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

        .event-hero {
            background: linear-gradient(rgba(67, 97, 238, 0.8), rgba(63, 55, 201, 0.8)), url('https://images.unsplash.com/photo-1540575467063-178a50c2df87?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            color: white;
            padding: 80px 0;
            text-align: center;
            margin-bottom: 40px;
        }

        .event-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .event-date {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }

        .stats {
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

        .notification {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .footer {
            text-align: center;
            padding: 20px 0;
            color: var(--gray);
            border-top: 1px solid var(--light-gray);
            margin-top: 30px;
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
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container header-content">
            <div class="logo">
                <i class="fas fa-calendar-alt"></i> Event Registration
            </div>
            
            <div class="nav">
                <a href="#" class="active">Home</a>
                <a href="#">About</a>
                <a href="#">FAQ</a>
                <a href="#">Contact</a>
            </div>
        </div>
    </div>

    <div class="event-hero">
        <div class="container">
            <h1 class="event-title">Exclusive Annual Gala</h1>
            <p class="event-date">December 15, 2023 | 7:00 PM | Grand Ballroom</p>
            <a href="#register" class="btn btn-success">Register Now</a>
        </div>
    </div>

    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_registrations; ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $first_time_guests; ?></div>
                <div class="stat-label">First-Time Attendees</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $regular_guests; ?></div>
                <div class="stat-label">Returning Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $invited_guests; ?></div>
                <div class="stat-label">Invited Guests</div>
            </div>
        </div>

        <div class="card" id="register">
            <div class="card-header">
                <span>Event Registration</span>
            </div>
            <div class="card-body">
                <?php if (!empty($notification)) echo $notification; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="fullname">Full Name *</label>
                        <input type="text" class="form-control" id="fullname" name="fullname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="invite_code">Invitation Code (if any)</label>
                        <input type="text" class="form-control" id="invite_code" name="invite_code" placeholder="Enter invitation code">
                        <small>Sample codes: EVENT2024, VIPGUEST, WELCOME25</small>
                    </div>
                    
                    <button type="submit" class="btn">Register Now</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>About the Event</span>
            </div>
            <div class="card-body">
                <p>Join us for an exclusive evening of networking, dining, and entertainment at our Annual Gala event. This year's theme is "A Night of Innovation" where we'll be celebrating the latest advancements in technology and business.</p>
                
                <h3>Event Details</h3>
                <ul>
                    <li>Date: December 15, 2023</li>
                    <li>Time: 7:00 PM - 11:00 PM</li>
                    <li>Location: Grand Ballroom, City Center</li>
                    <li>Dress Code: Formal Attire</li>
                </ul>
                
                <h3>Registration Information</h3>
                <p>All attendees must register in advance. Each registration will receive a unique registration number. If you were invited by a member, please enter the invitation code provided to you.</p>
                
                <p>First-time attendees will receive a special welcome package, while returning members will enjoy exclusive benefits from previous events.</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Exclusive Event &copy; 2023 | All rights reserved</p>
    </div>

    <script>
        // Simple form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                let valid = true;
                const fullname = document.getElementById('fullname');
                const email = document.getElementById('email');
                
                if (!fullname.value.trim()) {
                    valid = false;
                    highlightError(fullname);
                } else {
                    removeHighlight(fullname);
                }
                
                if (!email.value.trim() || !isValidEmail(email.value)) {
                    valid = false;
                    highlightError(email);
                } else {
                    removeHighlight(email);
                }
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly.');
                }
            });
            
            function highlightError(element) {
                element.style.borderColor = '#f72585';
            }
            
            function removeHighlight(element) {
                element.style.borderColor = '';
            }
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
