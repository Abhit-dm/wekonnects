<?php
// includes/mailer.php

function sendWeKonnectsEmail($to_email, $to_name, $subject, $message_body) {
    // Standard headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: WE KONNECTS <noreply@wekonnects.com>" . "\r\n"; // Change to your actual domain email
    
    // Beautiful Branded HTML Template
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
            .header { background: linear-gradient(135deg, #00204a, #003a8c); padding: 30px 20px; text-align: center; border-bottom: 4px solid #ff6b00; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px; }
            .content { padding: 30px; color: #333333; line-height: 1.6; font-size: 16px; }
            .content h2 { color: #00204a; font-size: 20px; margin-top: 0; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0; }
            .btn { display: inline-block; padding: 12px 25px; background-color: #ff6b00; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>WE KONNECTS</h1>
            </div>
            <div class="content">
                <h2>Hello ' . htmlspecialchars($to_name) . ',</h2>
                <p>' . $message_body . '</p>
                <br>
                <p>Best regards,<br><strong>The WE KONNECTS Team</strong></p>
            </div>
            <div class="footer">
                &copy; ' . date("Y") . ' WE KONNECTS. All rights reserved.<br>
                This is an automated notification, please do not reply.
            </div>
        </div>
    </body>
    </html>
    ';

    // Send the email using PHP's native mail function
    return mail($to_email, $subject, $html_message, $headers);
}
?>