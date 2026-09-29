<?php
// register.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Membership Application | WE KONNECTS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .register-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 40px 20px; box-sizing: border-box; }
        .register-card { width: 100%; max-width: 900px; padding: 40px; }
        .register-card h2 { margin: 0 0 10px 0; font-weight: 600; font-size: 28px; text-align: center; color: var(--primary-orange); }
        .register-card p { margin: 0 0 30px 0; color: rgba(255,255,255,0.8); font-size: 15px; text-align: center; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
        
        .input-group { text-align: left; margin-bottom: 5px; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.9); }
        
        .section-title { grid-column: 1 / -1; font-size: 18px; font-weight: 600; margin-top: 30px; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 8px; color: var(--primary-orange); }
        .full-width { grid-column: 1 / -1; }
        
        select.glass-input option { background: var(--dark-blue); color: var(--white); }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        
        .checkbox-group { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 15px; color: rgba(255,255,255,0.9); font-size: 14px; }
        .checkbox-group input[type="checkbox"] { margin-top: 4px; width: 18px; height: 18px; accent-color: var(--primary-orange); }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="glass-panel register-card">
        
        <div style="text-align: center;">
            <img src="https://wekonnects.com/logo.png" alt="WE KONNECTS" class="logo-img" style="max-width: 220px; margin-bottom: 15px;">
        </div>
        
        <h2>Official Membership Application</h2>
        <p>Please complete all sections thoroughly. The Membership Committee will review your profile.</p>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert-error">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <form action="actions/process_registration.php" method="POST">
            <div class="form-grid">
                
                <div class="section-title">Personal Details</div>
                
                <div class="input-group">
                    <label>Full Name (as per official records) *</label>
                    <input type="text" name="full_name" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Preferred Name (for meetings)</label>
                    <input type="text" name="preferred_name" class="glass-input">
                </div>
                <div class="input-group">
                    <label>Gender *</label>
                    <select name="gender" class="glass-input" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Date of Birth *</label>
                    <input type="date" name="dob" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Mobile Number *</label>
                    <input type="tel" name="mobile_number" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Alternate Contact Number</label>
                    <input type="tel" name="alt_contact" class="glass-input">
                </div>
                <div class="input-group">
                    <label>WhatsApp Number *</label>
                    <input type="tel" name="whatsapp_number" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="glass-input" required>
                </div>
                <div class="input-group full-width">
                    <label>Residential Address *</label>
                    <input type="text" name="residential_address" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>City *</label>
                    <input type="text" name="city" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>State *</label>
                    <input type="text" name="state" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Pincode *</label>
                    <input type="text" name="pincode" class="glass-input" required>
                </div>

                <div class="section-title">Business Information</div>

                <div class="input-group">
                    <label>Business / Company Name *</label>
                    <input type="text" name="company_name" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Brand Name (if any)</label>
                    <input type="text" name="brand_name" class="glass-input">
                </div>
                <div class="input-group">
                    <label>Nature of Business *</label>
                    <input type="text" name="nature_of_business" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Business Category Applied For *</label>
                    <input type="text" name="business_category" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Years in Business *</label>
                    <input type="number" name="years_in_business" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Office Contact Number *</label>
                    <input type="tel" name="office_contact" class="glass-input" required>
                </div>
                <div class="input-group full-width">
                    <label>Business Address *</label>
                    <input type="text" name="business_address" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Business Email *</label>
                    <input type="email" name="business_email" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Website (if any)</label>
                    <input type="url" name="website" class="glass-input" placeholder="https://">
                </div>
                <div class="input-group full-width">
                    <label>Social Media Links</label>
                    <input type="text" name="social_links" class="glass-input" placeholder="LinkedIn, Facebook, Instagram, etc.">
                </div>

                <div class="section-title">Business Details</div>

                <div class="input-group full-width">
                    <label>Brief description of products / services *</label>
                    <textarea name="description" class="glass-input" rows="3" required></textarea>
                </div>
                <div class="input-group full-width">
                    <label>What differentiates your business from competitors? *</label>
                    <textarea name="differentiators" class="glass-input" rows="3" required></textarea>
                </div>
                <div class="input-group full-width">
                    <label>Ideal customer / client profile *</label>
                    <textarea name="ideal_customer" class="glass-input" rows="2" required></textarea>
                </div>
                <div class="input-group">
                    <label>Geographic areas you serve *</label>
                    <input type="text" name="geo_areas" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Average transaction value *</label>
                    <input type="text" name="avg_transaction" class="glass-input" required>
                </div>
                <div class="input-group">
                    <label>Monthly business capacity (approx.) *</label>
                    <input type="text" name="monthly_capacity" class="glass-input" required>
                </div>

                <div class="section-title">Referral Profile</div>

                <div class="input-group full-width">
                    <label>Type of referrals you are looking for *</label>
                    <textarea name="referrals_looking_for" class="glass-input" rows="2" required></textarea>
                </div>
                <div class="input-group full-width">
                    <label>Business categories that can best refer to you *</label>
                    <textarea name="best_referring_categories" class="glass-input" rows="2" required></textarea>
                </div>
                <div class="input-group full-width">
                    <label>How do you qualify a good referral? *</label>
                    <textarea name="qualify_referral" class="glass-input" rows="2" required></textarea>
                </div>

                <div class="section-title">Contribution to WE KONNECTS</div>

                <div class="input-group full-width">
                    <label>How will you help other members generate business? *</label>
                    <textarea name="help_others" class="glass-input" rows="2" required></textarea>
                </div>
                <div class="input-group full-width">
                    <label>Skills, expertise, or experience you bring to the chapter *</label>
                    <textarea name="expertise_brought" class="glass-input" rows="2" required></textarea>
                </div>
                <div class="input-group">
                    <label>Willing to collaborate and share opportunities? *</label>
                    <select name="willing_to_collaborate" class="glass-input" required>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>

                <div class="section-title">Membership Commitment</div>

                <div class="input-group">
                    <label>Can you attend regular meetings? *</label>
                    <select name="attend_regularly" class="glass-input" required>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Will you send a substitute if unable to attend? *</label>
                    <select name="send_substitute" class="glass-input" required>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Are you a member of any other networking group? *</label>
                    <select name="other_networking" class="glass-input" required>
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>If yes, specify:</label>
                    <input type="text" name="other_networking_specify" class="glass-input">
                </div>

                <div class="section-title">Reference Details (Optional)</div>
                
                <div class="input-group">
                    <label>Referring Member Name</label>
                    <input type="text" name="referring_member" class="glass-input">
                </div>
                <div class="input-group">
                    <label>Chapter Name</label>
                    <input type="text" name="referring_chapter" class="glass-input">
                </div>
                <div class="input-group">
                    <label>Contact Number</label>
                    <input type="tel" name="referring_contact" class="glass-input">
                </div>

                <div class="section-title">Declaration & Account Setup</div>

                <div class="input-group full-width">
                    <label>Create a Secure Password (for your WE KONNECTS login) *</label>
                    <input type="password" name="password" class="glass-input" required>
                </div>

                <div class="full-width" style="background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px; margin-top: 10px;">
                    <div class="checkbox-group">
                        <input type="checkbox" id="declaration" name="declaration" required>
                        <label for="declaration"><strong>Declaration:</strong> I hereby apply for membership in WE KONNECTS – Business Referral Organisation. I confirm that the information provided above is true and correct. I agree to abide by all rules, bylaws, and the code of ethics of WE KONNECTS.</label>
                    </div>
                </div>

                <div class="input-group full-width">
                    <button type="submit" class="btn-primary" style="margin-top: 15px; padding: 18px; font-size: 18px;">Sign & Submit Application</button>
                </div>

            </div>
        </form>

        <div style="text-align: center; margin-top: 30px; font-size: 14px;">
            <a href="login.php" style="color: var(--primary-orange); text-decoration: none;">Already a member? Log in here.</a>
        </div>

    </div>
</div>

</body>
</html>