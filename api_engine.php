<?php
header('Content-Type: application/json');
require_once 'bms/config/database.php'; // Change to 'config/database.php' if your database file is in a folder

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // 1. FETCH IMPACT METRICS (Existing)
    if ($action === 'get_impact_metrics') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM group_members WHERE membership_status = 'Active'");
        $active_members = $stmt->fetch()['count'];

        $stmt = $pdo->query("SELECT SUM(amount) as total FROM slips WHERE slip_type = 'TYFCB'");
        $closed_business = $stmt->fetch()['total'];

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM visitors");
        $total_visitors = $stmt->fetch()['count'];

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM slips WHERE slip_type = 'REFERRAL'");
        $referral_count = $stmt->fetch()['count'];
        $ongoing_business = $referral_count * 25000;

        echo json_encode([
            'members' => $active_members ?: 0,
            'closed' => $closed_business ?: 0,
            'ongoing' => $ongoing_business ?: 0,
            'visitors' => $total_visitors ?: 0
        ]);
        exit;
    }

    // 2. FETCH ACTIVE CITIES (Existing)
    if ($action === 'get_cities') {
        $stmt = $pdo->query("SELECT DISTINCT TRIM(REPLACE(group_name, 'WK ', '')) as city FROM groups WHERE status = 'Active'");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        exit;
    }

    // 3. FETCH LIVE MEETINGS FOR A SELECTED CITY
    if ($action === 'search_meetings') {
        $city = isset($_GET['city']) ? $_GET['city'] : '';
        
        $stmt = $pdo->prepare("SELECT id, group_name, meeting_time, default_venue FROM groups WHERE status = 'Active' AND group_name LIKE :city");
        $stmt->execute(['city' => '%' . $city . '%']);
        $groups = $stmt->fetchAll();

        $results = [];
        foreach ($groups as $group) {
            // Get active member count
            $memStmt = $pdo->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = :group_id AND membership_status = 'Active'");
            $memStmt->execute(['group_id' => $group['id']]);
            $memberCount = $memStmt->fetch()['count'];

            // Check the chapter_meetings table for the next SCHEDULED meeting
            $meetStmt = $pdo->prepare("SELECT meeting_date, venue FROM chapter_meetings WHERE group_id = :group_id AND meeting_type IN ('Meeting', 'Event') AND status = 'Scheduled' AND meeting_date >= CURDATE() ORDER BY meeting_date ASC LIMIT 1");
            $meetStmt->execute(['group_id' => $group['id']]);
            $nextMeeting = $meetStmt->fetch();

            $venue = $group['default_venue'];
            $timeObj = DateTime::createFromFormat('H:i:s', $group['meeting_time']);
            $formattedTime = $timeObj ? $timeObj->format('h:i A') : $group['meeting_time'];

            if ($nextMeeting) {
                // If a meeting is scheduled, show the exact date and specific venue
                if (!empty($nextMeeting['venue'])) {
                    $venue = $nextMeeting['venue'];
                }
                $dateObj = new DateTime($nextMeeting['meeting_date']);
                $dateString = $dateObj->format('D, M d, Y') . ' at ' . $formattedTime;
            } else {
                // Fallback if the Head Table hasn't scheduled the next one yet
                $dateString = "Next meeting TBA at " . $formattedTime;
            }

            $results[] = [
                'id' => (int)$group['id'],
                'name' => $group['group_name'],
                'time' => $dateString,
                'location' => !empty($venue) ? $venue : 'Venue TBA',
                'members' => $memberCount
            ];
        }

        echo json_encode($results);
        exit;
    }

        // PUBLIC: REGISTER FOR KARTHIKA VANA MAHOTSAVAM
        if ($action === 'submit_karthika_registration') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                header('Allow: POST');
                echo json_encode(['error' => 'Submit this form using POST.']);
                exit;
            }

            if (!empty($_POST['website'])) {
                echo json_encode(['success' => true, 'message' => 'Registration received.']);
                exit;
            }

            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $phone_digits = preg_replace('/\D/', '', $phone);
            $event_tickets = filter_var($_POST['event_tickets'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999]]);
            $kalyanam_tickets = filter_var($_POST['kalyanam_tickets'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999]]);

            if (!preg_match('/^.{1,150}$/us', $name) || preg_match('/[\x00-\x1F\x7F]/', $name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Enter a valid name (up to 150 characters).']);
                exit;
            }
            if (!preg_match('/^\+?[0-9() -]{7,20}$/D', $phone) || strlen($phone_digits) < 7 || strlen($phone_digits) > 15) {
                http_response_code(400);
                echo json_encode(['error' => 'Enter a valid phone number.']);
                exit;
            }
            if ($event_tickets === false || $kalyanam_tickets === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Ticket quantities must be whole numbers between 0 and 999999.']);
                exit;
            }
            if ($event_tickets === 0 && $kalyanam_tickets === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Choose at least one ticket.']);
                exit;
            }

            if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                $upload_error = $_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE;
                $message = ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE)
                    ? 'The screenshot exceeds the server upload limit. Choose a smaller image.'
                    : 'Upload a valid payment screenshot.';
                echo json_encode(['error' => $message]);
                exit;
            }
            $screenshot = $_FILES['payment_screenshot'];
            if (!is_uploaded_file($screenshot['tmp_name']) || $screenshot['size'] > 5 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'The screenshot must be a valid image no larger than 5 MB.']);
                exit;
            }
            try {
                if (!class_exists('finfo')) {
                    throw new RuntimeException('The PHP fileinfo extension is required for payment screenshot uploads.');
                }
                $file_info = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $file_info->file($screenshot['tmp_name']);
                $allowed_images = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
                if (!isset($allowed_images[$mime_type])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Upload a JPEG, PNG, or WebP screenshot.']);
                    exit;
                }

                require_once __DIR__ . '/bms/includes/karthika_event.php';
                ensure_karthika_registrations_table($pdo);
                $upload_directory = karthika_private_upload_directory();
                $filename = bin2hex(random_bytes(16)) . $allowed_images[$mime_type];
                $upload_path = $upload_directory . DIRECTORY_SEPARATOR . $filename;
                $total_amount = ($event_tickets * 499) + ($kalyanam_tickets * 999);

                if (!move_uploaded_file($screenshot['tmp_name'], $upload_path)) {
                    throw new RuntimeException('Unable to store the uploaded payment screenshot.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO karthika_registrations
                        (full_name, phone, event_ticket_count, kalyanam_couple_count, total_amount, payment_screenshot)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $phone, $event_tickets, $kalyanam_tickets, $total_amount, $filename]);
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Your registration has been received. Payment verification is pending.'
                ]);
                exit;
            } catch (Exception $e) {
                if (isset($upload_path) && is_file($upload_path)) {
                    unlink($upload_path);
                }
                error_log('Karthika registration could not be saved: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'We could not save your registration right now. Please try again later.']);
                exit;
            }
        }

    // 4. NEW: FETCH BLOOD DONORS
    if ($action === 'get_blood_donors') {
        $blood_group = isset($_GET['group']) ? $_GET['group'] : '';
        
        // Only fetch active members who actually have a blood group listed
        $query = "SELECT first_name, last_name, blood_group, phone, city, profile_photo 
                  FROM users 
                  WHERE blood_group IS NOT NULL AND blood_group != '' AND status = 'Active'";
        
        $params = [];
        // If a specific group was searched, filter by it
        if (!empty($blood_group)) {
            $query .= " AND REPLACE(blood_group, ' ', '') = :bg"; // Removing spaces just in case
            $params['bg'] = str_replace(' ', '', $blood_group);
        }
        
        $query .= " ORDER BY first_name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $donors = $stmt->fetchAll();
        
        echo json_encode($donors);
        exit;
    }
// 5. SAVE DIRECTORY GATEKEEPER LEADS
    if ($action === 'save_directory_lead') {
        // Capture the lead data from the front-end form
        $name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
        $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
        $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';
        $reason = isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '';

        // IMPORTANT: If you want to save these leads, run this SQL command in phpMyAdmin first:
        // CREATE TABLE directory_leads (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(100), phone VARCHAR(20), reason VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO directory_leads (name, email, phone, reason) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $reason]);
        } catch(PDOException $e) {
            // Ignore error if table doesn't exist yet, just let the user through
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // 6. SEARCH SECURE DIRECTORY & CALCULATE STAR TIERS
    if ($action === 'search_members') {
        $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
        $city = isset($_GET['city']) ? $_GET['city'] : '';
        $chapter = isset($_GET['chapter']) ? $_GET['chapter'] : '';
        $category = isset($_GET['category']) ? $_GET['category'] : '';

        // Query users, JOIN the businesses table for company name, and JOIN business_categories for their exact category
        $query = "SELECT u.id, u.first_name, u.last_name, b.company_name, bc.category_name as business_category, u.city, u.phone, u.profile_photo, 
                         COALESCE(g.group_name, 'Independent') as chapter_name,
                         (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL') as referral_count
                  FROM users u
                  LEFT JOIN businesses b ON u.id = b.user_id
                  LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
                  LEFT JOIN groups g ON gm.group_id = g.id
                  LEFT JOIN business_categories bc ON gm.business_category_id = bc.id
                  WHERE u.status = 'Active'";
        
        $params = [];

        if (!empty($keyword)) {
            $query .= " AND (u.first_name LIKE :kw OR u.last_name LIKE :kw OR b.company_name LIKE :kw OR bc.category_name LIKE :kw)";
            $params['kw'] = "%$keyword%";
        }
        if (!empty($city)) {
            $query .= " AND u.city = :city";
            $params['city'] = $city;
        }
        if (!empty($chapter)) {
            $query .= " AND g.group_name LIKE :chapter";
            $params['chapter'] = "%$chapter%";
        }
        if (!empty($category)) {
            $query .= " AND bc.category_name = :category";
            $params['category'] = $category;
        }

        // Order by the most active members first
        $query .= " ORDER BY referral_count DESC LIMIT 50"; 

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $members = $stmt->fetchAll();

        // Process Gamification Tiers based on their referral count
        $results = [];
        foreach ($members as $m) {
            $refs = (int)$m['referral_count'];
            
            if ($refs >= 20) {
                $tier = "Super Star ⭐⭐⭐";
                $color = "bg-yellow-100 text-yellow-700 border-yellow-400 shadow-[0_0_15px_rgba(250,204,21,0.5)]";
            } elseif ($refs >= 5) {
                $tier = "Rising Star ⭐⭐";
                $color = "bg-blue-100 text-blue-700 border-blue-400";
            } else {
                $tier = "Star Member ⭐";
                $color = "bg-gray-100 text-gray-700 border-gray-300";
            }

            $results[] = [
                'name' => trim($m['first_name'] . ' ' . $m['last_name']),
                'company' => !empty($m['company_name']) ? $m['company_name'] : 'Independent Professional',
                'category' => !empty($m['business_category']) ? $m['business_category'] : 'General',
                'chapter' => $m['chapter_name'],
                'city' => !empty($m['city']) ? $m['city'] : 'Location TBA',
                'phone' => $m['phone'],
                'photo' => $m['profile_photo'],
                'tier_name' => $tier,
                'tier_color' => $color
            ];
        }

        echo json_encode($results);
        exit;
    }
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>