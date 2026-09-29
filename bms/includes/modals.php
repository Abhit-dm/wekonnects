<?php
// includes/modals.php
// Included at the bottom of index.php - strictly for local chapter members
?>

<div class="modal-overlay" id="modalLinkGiven">
    <div class="modal-box" style="max-height: 90vh; overflow-y: auto;">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalLinkGiven')"></i>
        <h3 style="margin-top:0; color:var(--dark-blue);"><i class="fa-solid fa-arrow-up-right-from-square" style="color:var(--primary-orange);"></i> Give Links</h3>
        
        <form action="actions/submit_slip.php" method="POST" id="bulkReferralForm">
            <input type="hidden" name="slip_type" value="REFERRAL">
            
            <div id="referralBlocksContainer">
                <div class="referral-block" style="background: rgba(0,0,0,0.02); padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; position: relative;">
                    <div class="form-group">
                        <label>Give Link To (Chapter Member)</label>
                        <select name="receiver_member_id[]" class="glass-input" style="color: black;" required>
                            <option value="">-- Select Member --</option>
                            <?php foreach ($chapter_members as $m): ?>
                                <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Link Type</label>
                        <select name="referral_type[]" class="glass-input" style="color: black;" required onchange="toggleOutsideFields(this)">
                            <option value="INSIDE">Inside (I am buying their service)</option>
                            <option value="OUTSIDE">Outside (Someone else is buying)</option>
                        </select>
                    </div>

                    <div class="outsideDetailsBox" style="display: none; background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px; border: 1px dashed #3b82f6; margin-bottom: 15px;">
                        <p style="margin: 0 0 10px 0; font-size: 12px; color: #1e3a8a; font-weight: 600;">Outside Lead Details</p>
                        <div class="form-group">
                            <label>Lead's Name</label>
                            <input type="text" name="outside_ref_name[]" class="glass-input outside_ref_name" style="color: black;" placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Lead's Phone Number</label>
                            <input type="text" name="outside_ref_phone[]" class="glass-input outside_ref_phone" style="color: black;" placeholder="e.g. 9876543210">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Remarks / Details</label>
                        <textarea name="remarks[]" class="glass-input" style="color: black; height: 60px;" required placeholder="What is this link regarding?"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Date</label>
                        <input type="date" name="date_logged[]" class="glass-input" style="color: black;" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <button type="button" onclick="addReferralBlock()" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px dashed #3b82f6; padding: 12px; width: 100%; border-radius: 8px; font-weight: 700; cursor: pointer; margin-bottom: 15px; transition: 0.2s;"><i class="fa-solid fa-plus"></i> Add Another Link</button>

            <button type="submit" style="background:var(--primary-orange); color:white; border:none; padding:15px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Submit All Links</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalDealDone">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalDealDone')"></i>
        <h3 style="margin-top:0; color: #00204a; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <i class="fa-solid fa-handshake-simple" style="color:#10b981;"></i> Deal Done
        </h3>
        <p style="font-size:12px; color:#64748b; margin-top:-5px; margin-bottom:15px;">Thank another member for business they brought you.</p>
        
        <form action="actions/submit_slip.php" method="POST">
            <input type="hidden" name="slip_type" value="TYFCB">
            
            <div class="form-group">
                <label>Thanking (Who gave you the deal?):</label>
                <select name="receiver_member_id" class="glass-input" required style="color: black; background: white;">
                    <option value="">-- Select Member --</option>
                    <?php if(!empty($chapter_members)): foreach($chapter_members as $m): ?>
                        <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Deal Type</label>
                <select name="deal_type" class="glass-input" style="color: black; background: white;" required>
                    <option value="INSIDE">Inside Deal (They bought from me - 2 pts)</option>
                    <option value="OUTSIDE">Outside Deal (Someone else bought - 4 pts)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Deal Amount (₹)</label>
                <input type="number" name="amount" class="glass-input" placeholder="e.g. 25000" min="1" required style="color: black; background: white;">
            </div>

            <div class="form-group">
                <label>Date Deal Closed</label>
                <input type="date" name="date_logged" class="glass-input" value="<?php echo date('Y-m-d'); ?>" required style="color: black; background: white;">
            </div>

            <button type="submit" style="background:#10b981; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:600; cursor:pointer; margin-top: 10px;">Submit Deal Done</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal121">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modal121')"></i>
        <h3 style="margin-top:0; color: #00204a; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <i class="fa-solid fa-handshake" style="color:#3b82f6;"></i> Log a 1-to-1
        </h3>
        
        <form action="actions/submit_slip.php" method="POST">
            <input type="hidden" name="slip_type" value="121">
            
            <div class="form-group">
                <label>Met With:</label>
                <select name="receiver_member_id" class="glass-input" required style="color: black; background: white;">
                    <option value="">-- Select Member --</option>
                    <?php if(!empty($chapter_members)): foreach($chapter_members as $m): ?>
                        <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Date of Meeting</label>
                <input type="date" name="date_logged" class="glass-input" value="<?php echo date('Y-m-d'); ?>" required style="color: black; background: white;">
            </div>

            <div class="form-group">
                <label>Topics Discussed (Optional)</label>
                <input type="text" name="remarks" class="glass-input" placeholder="Brief notes about the meeting..." style="color: black; background: white;">
            </div>

            <button type="submit" style="background:#3b82f6; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:600; cursor:pointer; margin-top: 10px;">Log 1-to-1</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalVisitor">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalVisitor')"></i>
        <h3 style="margin-top:0; color: #00204a; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <i class="fa-solid fa-user-plus" style="color:#8b5cf6;"></i> Register a Visitor
        </h3>
        
        <form action="actions/add_visitor.php" method="POST">
            <div class="form-group">
                <label>Visitor Name</label>
                <input type="text" name="visitor_name" class="glass-input" required style="color: black; background: white;">
            </div>
            
            <div class="form-group">
                <label>Company / Business</label>
                <input type="text" name="company_name" class="glass-input" required style="color: black; background: white;">
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="glass-input" required style="color: black; background: white;">
            </div>
            
            <div class="form-group">
                <label>Date they plan to visit</label>
                <input type="date" name="visit_date" class="glass-input" value="<?php echo date('Y-m-d'); ?>" required style="color: black; background: white;">
            </div>

            <button type="submit" style="background:#8b5cf6; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:600; cursor:pointer; margin-top: 10px;">Register Visitor</button>
        </form>
    </div>
</div>

<script>
    function toggleOutsideFields(selectElement) {
        const block = selectElement.closest('.referral-block');
        const box = block.querySelector('.outsideDetailsBox');
        const nameInput = block.querySelector('.outside_ref_name');
        const phoneInput = block.querySelector('.outside_ref_phone');
        
        if (selectElement.value === 'OUTSIDE') {
            box.style.display = 'block';
            nameInput.required = true;
            phoneInput.required = true;
        } else {
            box.style.display = 'none';
            nameInput.required = false;
            phoneInput.required = false;
            nameInput.value = '';
            phoneInput.value = '';
        }
    }

    function addReferralBlock() {
        const container = document.getElementById('referralBlocksContainer');
        const originalBlock = container.querySelector('.referral-block');
        const newBlock = originalBlock.cloneNode(true);
        
        newBlock.querySelectorAll('input, textarea').forEach(input => {
            if(input.name !== 'date_logged[]') input.value = '';
        });
        
        newBlock.querySelector('.outsideDetailsBox').style.display = 'none';
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove Link';
        removeBtn.style.cssText = 'background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; margin-top: 10px; display: inline-block;';
        removeBtn.onclick = function() { newBlock.remove(); };
        newBlock.appendChild(removeBtn);

        container.appendChild(newBlock);
    }
</script>