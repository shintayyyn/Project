<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTeacherModalLabel">Add New Teacher</h5>
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            <div class="modal-body">
                <form id="addTeacherForm" method="POST" novalidate>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control name-input" name="t_fname" id="t_fname"
                                   pattern="[A-Za-z\-\s]+" required minlength="2" maxlength="50"
                                   oninput="this.value = this.value.replace(/[^A-Za-z\s-]/g, '')">
                            <div class="invalid-feedback">Please enter a valid first name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control name-input" name="t_lname" id="t_lname"
                                   pattern="[A-Za-z\-\s]+" required minlength="2" maxlength="50"
                                   oninput="this.value = this.value.replace(/[^A-Za-z\s-]/g, '')">
                            <div class="invalid-feedback">Please enter a valid last name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control name-input" name="t_mname"
                                   pattern="[A-Za-z\-\s]*" maxlength="50"
                                   oninput="this.value = this.value.replace(/[^A-Za-z\s-]/g, '')">
                            <div class="invalid-feedback">Please enter a valid middle name</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control name-input" name="t_suffix"
                                   pattern="[A-Za-z\-\s\.]*" maxlength="10"
                                   oninput="this.value = this.value.replace(/[^A-Za-z\s\.]/g, '')">
                            <div class="invalid-feedback">Please enter a valid suffix</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="t_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="invalid-feedback">Please select a gender</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="t_bdate" id="t_bdate" required>
                            <div class="invalid-feedback">Please select a birthdate</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="t_cnum" required
                                   pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX">
                            <div class="invalid-feedback">Enter a valid 11-digit number starting with 09</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="t_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3 d-none">
                            <label class="form-label">Password</label>
                            <input type="text" name="t_password" id="t_password" class="form-control" readonly>
                            <small class="text-muted">Password auto-generated from name + birthdate</small>
                        </div>
                    </div>
                    <div class="row">
                         <div class="col-md-12 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="t_email" required
                                   pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            <div class="invalid-feedback">Enter a valid email address</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="t_department" id="t_department" disabled>
                                <option value="">Select Department</option>
                                <?php
                                // Assuming session is started and teacher ID is stored
                                $teacher_id = $_SESSION['t_id'] ?? null;

                                if ($teacher_id) {
                                    // Fetch only the teacher's department
                                    $stmt = $conn->prepare("SELECT degree_id, degree_name FROM degrees 
                                                            WHERE degree_id = (SELECT t_department FROM teachers WHERE t_id = ?)");
                                    $stmt->bind_param("i", $teacher_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($dept = $result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($dept['degree_id']) . "' selected>" .
                                            htmlspecialchars($dept['degree_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select a department</div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="submit" form="addTeacherForm" class="btn btn-primary">Add Teacher</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" >
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTeacherModalLabel">Edit Teacher</h5>
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            <div class="modal-body">
                <form id="editTeacherForm" method="POST" novalidate>
                    <input type="hidden" name="t_id" id="edit_t_id">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control name-input" name="t_fname" id="edit_t_fname" required>
                            <div class="invalid-feedback">Please enter a valid first name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control name-input" name="t_lname" id="edit_t_lname" required>
                            <div class="invalid-feedback">Please enter a valid last name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control name-input" name="t_mname" id="edit_t_mname">
                            <div class="invalid-feedback">Please enter a valid middle name</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control name-input" name="t_suffix" id="edit_t_suffix">
                            <div class="invalid-feedback">Please enter a valid suffix</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="t_gender" id="edit_t_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="invalid-feedback">Please select a gender</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="t_bdate" id="edit_t_bdate" required>
                            <div class="invalid-feedback">Please select a birthdate</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="t_cnum" id="edit_t_cnum" 
                                   pattern="^09[0-9]{9}$" maxlength="11" required>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number starting with 09</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="t_email" id="edit_t_email" required>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                    </div>
                    <div class="row">
                      <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="t_department" id="edit_t_department" required disabled>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['degree_id']) ?>">
                                        <?= htmlspecialchars($dept['degree_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a department</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="t_status" id="edit_t_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="editTeacherForm" class="btn btn-primary">Update Teacher</button>
            </div>
        </div>
    </div>
</div>

 <script>
    
$(document).ready(function() {

    // Initialize Bootstrap modals
    var addModal = new bootstrap.Modal(document.getElementById('addTeacherModal'));
    var editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));

    // Adapt JavaScript code for teachers
    function ucwordsWithHyphen(str) {
        return str.toLowerCase().split(/[\s-]+/).map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    // Add input handlers for name fields
    $('#addTeacherForm input[name="t_fname"], #addTeacherForm input[name="t_lname"], #addTeacherForm input[name="t_mname"], #addTeacherForm input[name="t_suffix"]').on('input', function() {
        this.value = ucwordsWithHyphen(this.value);
    });
document.addEventListener("DOMContentLoaded", function () {
    const fnameInput = document.getElementById("t_fname");
    const lnameInput = document.getElementById("t_lname");
    const bdateInput = document.getElementById("t_bdate");
    const passwordInput = document.getElementById("t_password");


    function generatePassword() {
        const fname = fnameInput.value.trim().toLowerCase();
        const lname = lnameInput.value.trim().toLowerCase();
        const bdate = bdateInput.value; // Expected format: YYYY-MM-DD

        if (fname && lname && bdate) {
            const datePart = bdate.replace(/-/g, ''); // YYYYMMDD
            const password = fname.charAt(0) + lname + datePart;
            passwordInput.value = password;
        } else {
            passwordInput.value = '';
        }
    }

    fnameInput.addEventListener("input", generatePassword);
    lnameInput.addEventListener("input", generatePassword);
    bdateInput.addEventListener("input", generatePassword);
});

$('#editTeacherModal').on('hidden.bs.modal', function () {
    // Return focus to a safe element
    $('#btn').trigger('focus');
});

});
</script>