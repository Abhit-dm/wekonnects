<?php
// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize and fetch form inputs
    $name = htmlspecialchars(strip_tags(trim($_POST['name'])));
    $phone = htmlspecialchars(strip_tags(trim($_POST['phone'])));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $category = htmlspecialchars(strip_tags(trim($_POST['category'])));
    $city = isset($_POST['city']) ? htmlspecialchars(strip_tags(trim($_POST['city']))) : 'Not Provided';
    $message = htmlspecialchars(strip_tags(trim($_POST['message'])));

    // 2. Setup email parameters
    $to = "wekonnectsforu@gmail.com";
    $subject = "New Membership Inquiry - WeKonnects Group";

    // 3. Construct the email body
    $email_content = "You have received a new membership inquiry from the WeKonnects website.\n\n";
    $email_content .= "==============================================\n";
    $email_content .= "Name: $name\n";
    $email_content .= "Phone: $phone\n";
    $email_content .= "Email: $email\n";
    $email_content .= "City: $city\n";
    $email_content .= "Business Category: $category\n";
    $email_content .= "==============================================\n\n";
    $email_content .= "Message / Business Details:\n$message\n";

    // 4. Set headers
    $headers = "From: no-reply@wekonnects.com\r\n"; 
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // 5. Send the email and provide feedback
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (mail($to, $subject, $email_content, $headers)) {
            echo "<script>
                    alert('Thank you! Your interest in joining a WeKonnects group has been submitted successfully.');
                    window.location.href = 'index.html';
                  </script>";
        } else {
            echo "<script>
                    alert('Oops! Something went wrong, and we couldn\'t send your message. Please try again later.');
                    window.history.back();
                  </script>";
        }
    } else {
        echo "<script>
                alert('There was a problem with the email address provided. Please check it and try again.');
                window.history.back();
              </script>";
    }
} else {
    header("Location: contact.html");
    exit;
}
?>