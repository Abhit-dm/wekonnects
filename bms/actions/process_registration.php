<?php
// actions/process_registration.php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

// Modern PHP 8+ custom sanitization function
function clean_str($key) {
    return isset($_POST[$key]) ? trim(strip_tags($_POST[$key])) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- 1. SANITIZE PERSONAL DETAILS ---
    $full_name = clean_str('full_name');$name_parts = explode(' ', $full_name, 2);$first_name = $name_parts[0];$last_name  = isset($name_parts[1]) ?$name_parts[1] : '';

    $preferred_name = clean_str('preferred_name');$gender         = clean_str('gender');
    $dob            = clean_str('dob');$mobile_number  = clean_str('mobile_number');
    $alt_contact    = clean_str('alt_contact');$whatsapp       = clean_str('whatsapp_number');
    
    // Email and Int still use standard filters
    $email          = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    $res_address    = clean_str('residential_address');$city           = clean_str('city');
    $state          = clean_str('state');$pincode        = clean_str('pincode');
    
    $password       =$_POST['password']; 
    $password_hash  = password_hash($password, PASSWORD_DEFAULT);

    // --- STRICT CONSTRAINT: 1 Member = 1 Mobile Number ---
    try {
        $checkPhone =$pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $checkPhone->execute([$mobile_number]);
        if ($checkPhone->fetch()) {$_SESSION['error_message'] = "Registration Failed: This Mobile Number is already registered to an existing member. Please use a different number or log in.";
            header("Location: ../register.php");
            exit;
        }
    } catch (PDOException $e) {$_SESSION['error_message'] = "System verification error.";
        header("Location: ../register.php");
        exit;
    }

    // --- 2. SANITIZE BUSINESS INFORMATION ---
    $company_name   = clean_str('company_name');$brand_name     = clean_str('brand_name');
    $nature_of_biz  = clean_str('nature_of_business');$biz_category   = clean_str('business_category');
    $years          = filter_input(INPUT_POST, 'years_in_business', FILTER_SANITIZE_NUMBER_INT);$office_contact = clean_str('office_contact');
    $biz_address    = clean_str('business_address');$biz_email      = filter_input(INPUT_POST, 'business_email', FILTER_SANITIZE_EMAIL);
    $website        = filter_input(INPUT_POST, 'website', FILTER_SANITIZE_URL);$social_links   = clean_str('social_links');

    // --- 3. SANITIZE BUSINESS DETAILS & REFERRALS ---
    $description    = clean_str('description');
    $diffs          = clean_str('differentiators');$ideal_customer = clean_str('ideal_customer');
    $geo_areas      = clean_str('geo_areas');$avg_trans      = clean_str('avg_transaction');
    $monthly_cap    = clean_str('monthly_capacity');$refs_looking   = clean_str('referrals_looking_for');
    $best_refs      = clean_str('best_referring_categories');$qualify_ref    = clean_str('qualify_referral');
    
    $help_others    = clean_str('help_others');$expertise      = clean_str('expertise_brought');

    // --- 4. SANITIZE COMMITMENTS & REFERENCES ---
    $will_collab    = clean_str('willing_to_collaborate');$attend_reg     = clean_str('attend_regularly');
    $send_sub       = clean_str('send_substitute');$other_net      = clean_str('other_networking');
    $other_net_spec = clean_str('other_networking_specify');$ref_member     = clean_str('referring_member');
    $ref_chapter    = clean_str('referring_chapter');$ref_contact    = clean_str('referring_contact');
    
    // Checkbox boolean
    $declaration    = isset($_POST['declaration']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        // --- STEP A: Insert into `users` table ---
        $sqlUser = "INSERT INTO users 
            (first_name, last_name, preferred_name, gender, dob, phone, alt_contact, whatsapp_number, email, residential_address, city, state, pincode, password_hash, system_role, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'MEMBER', 'Pending_Setup')";
        
        $stmtUser =$pdo->prepare($sqlUser);$stmtUser->execute([
            $first_name,$last_name, $preferred_name,$gender, $dob,$mobile_number, $alt_contact,$whatsapp, $email,$res_address, $city,$state, $pincode,$password_hash
        ]);
        
        $new_user_id =$pdo->lastInsertId();

        // --- STEP B: Insert into `businesses` table ---
        $sqlBiz = "INSERT INTO businesses 
            (user_id, company_name, brand_name, nature_of_business, business_category_applied, years_in_business, address, office_contact, business_email, website, social_links, description, differentiators, ideal_customer, geo_areas, avg_transaction, monthly_capacity, referrals_looking_for, best_referring_categories, qualify_referral, help_others, expertise_brought, willing_to_collaborate, attend_regularly, send_substitute, other_networking, other_networking_specify, referring_member, referring_chapter, referring_contact, declaration_agreed) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmtBiz = $pdo->prepare($sqlBiz);
        $stmtBiz->execute([$new_user_id, $company_name,$brand_name, $nature_of_biz,$biz_category, $years,$biz_address, $office_contact,$biz_email, $website,$social_links, $description,$diffs, $ideal_customer,$geo_areas, $avg_trans,$monthly_cap, $refs_looking,$best_refs, $qualify_ref,$help_others, $expertise,$will_collab, $attend_reg,$send_sub, $other_net,$other_net_spec, $ref_member,$ref_chapter, $ref_contact,$declaration
        ]);

        $pdo->commit();

        $_SESSION['error_message'] = "<span style='color:#10b981;'><i class='fa-solid fa-circle-check'></i> Application Submitted Successfully! Our membership committee will review your profile shortly.</span>";
        header("Location: ../login.php");
        exit;

    } catch (PDOException $e) {$pdo->rollBack();
        
        if ($e->getCode() == 23000) {$_SESSION['error_message'] = "An application with this email address has already been submitted.";
        } else {
            $_SESSION['error_message'] = "System Error: " . $e->getMessage();
        }
        header("Location: ../register.php");
        exit;
    }
} else {
    header("Location: ../register.php");
    exit;
}
?>