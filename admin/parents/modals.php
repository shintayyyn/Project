<!-- Edit Parent Modal -->
<div class="modal fade" id="editParentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editParentForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Parent</h5>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="p_id" id="edit_p_id">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="p_fname" id="edit_p_fname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="p_lname" id="edit_p_lname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="p_mname" id="edit_p_mname">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control" name="p_suffix" id="edit_p_suffix">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="p_gender" id="edit_p_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="p_bdate" id="edit_p_bdate" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="p_cnum" id="edit_p_cnum" pattern="^09[0-9]{9}$" maxlength="11" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="p_email" id="edit_p_email" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="p_status" id="edit_p_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- 🔹 Parent Address Section -->
                    <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="p_address" id="edit_p_address" rows="2"></textarea>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="sameAsChildAddress">
                            <label class="form-check-label" for="sameAsChildAddress">
                                Same as Child's Address
                            </label>
                        </div>
                    </div>
                </div>

                    <div class="row">
                        <div class="col-lg-12 mb-3">
                            <label class="form-label">Child</label>
                            <select class="form-select" name="child_id" id="edit_child_id">
                                <option value="--">Select Child</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
          <div class="col-md-12 mb-3 text-center">
              <label class="form-label d-block">Profile Picture</label>
              <input type="file" class="form-control" name="avatar" id="edit_p_avatar" accept="image/*">
              <small class="text-muted">Allowed: JPG, PNG. Max size: 2MB</small>
              <div id="avatarPreview" class="mt-2">
                  <img src="" id="avatarPreviewImg" class="rounded-circle" style="width:100px; height:100px; object-fit:cover; display:none;">
              </div>
          </div>
      </div>


                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
  $('#sameAsChildAddress').on('change', function() {
    if ($(this).is(':checked')) {
        const childAddress = $('#edit_child_id option:selected').data('address'); 
        $('#edit_p_address').val(childAddress || '');
    } else {
        $('#edit_p_address').val('');
    }
});

// Allowed patterns
const nameRegex    = /^[A-Za-z0-9\s,.\-]*$/;
const addressRegex = /^[A-Za-z0-9\s,.\-]*$/;
const emailRegex   = /^[A-Za-z0-9._%+\-@]*$/; // only chars, real validation still backend

function enforcePattern(input, regex) {
    input.addEventListener("input", function() {
        if (!regex.test(this.value)) {
            this.value = this.value.replace(/[^A-Za-z0-9\s,.\-]/g, ""); // strip disallowed
        }
    });
}

// Attach to fields
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("input[name=p_fname], input[name=p_lname], input[name=p_mname], input[name=p_suffix]")
        .forEach(el => enforcePattern(el, nameRegex));

    document.querySelector("input[name=p_address]")?.addEventListener("input", function() {
        if (!addressRegex.test(this.value)) {
            this.value = this.value.replace(/[^A-Za-z0-9\s,.\-]/g, "");
        }
    });

    document.querySelector("input[name=p_email]")?.addEventListener("input", function() {
        if (!emailRegex.test(this.value)) {
            this.value = this.value.replace(/[^A-Za-z0-9._%+\-@]/g, "");
        }
    });
});

document.getElementById('edit_p_avatar').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            const img = document.getElementById('avatarPreviewImg');
            img.src = ev.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});


</script>