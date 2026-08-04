<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/functions.php';

// Two entry points into this one form: a brand-new visitor (no account yet)
// fills in both account + employer/KYC fields; an already-logged-in Wellness
// member (same login, adding Basics on top) only fills in employer/KYC.
$already_logged_in = is_logged_in();
if ($already_logged_in) {
    $existing_member = basics_get_member($conn, current_user_id());
    if ($existing_member) {
        redirect('/basics/pending.php');
    }
}

$errors = [];
$full_name = '';
$address = '';
$birthdate = '';
$contact_number = '';
$email = '';
$username = '';
$employer_name = '';
$employer_contact = '';
$position = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employer_name = trim($_POST['employer_name'] ?? '');
    $employer_contact = trim($_POST['employer_contact'] ?? '');
    $position = trim($_POST['position'] ?? '');
    if ($employer_name === '') $errors[] = 'Employer name is required.';

    if (!$already_logged_in) {
        $full_name = trim($_POST['full_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($full_name === '') $errors[] = 'Full name is required.';
        if ($address === '') $errors[] = 'Address is required.';
        if ($birthdate === '' || !DateTime::createFromFormat('Y-m-d', $birthdate)) $errors[] = 'A valid birthdate is required.';
        if ($contact_number === '') $errors[] = 'Contact number is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if ($username === '') $errors[] = 'Username is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'That username is already taken.';
            $stmt->close();

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'That email address is already registered.';
            $stmt->close();
        }
    }

    $doc_fields = [
        'valid_id_1' => 'First valid ID',
        'valid_id_2' => 'Second valid ID',
        'barangay_clearance' => 'Barangay Clearance',
        'membership_application_form' => 'Membership Application Form',
        'certificate_of_employment' => 'Certificate of Employment / Work Clearance',
    ];
    foreach ($doc_fields as $field => $label) {
        if (empty($_FILES[$field]['name'])) {
            $errors[] = $label . ' is required.';
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            if ($already_logged_in) {
                $user_id = current_user_id();
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $new_code = generate_referral_code($conn);
                $stmt = $conn->prepare("INSERT INTO users
                    (referral_code, referred_by, full_name, address, birthdate, contact_number, email, username, password_hash, must_change_password, status, wellness_enrolled)
                    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 1, 'active', 0)");
                $stmt->bind_param('ssssssss', $new_code, $full_name, $address, $birthdate, $contact_number, $email, $username, $hash);
                $stmt->execute();
                $user_id = $stmt->insert_id;
                $stmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO basics_members (user_id, employer_name, employer_contact, position) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $user_id, $employer_name, $employer_contact, $position);
            $stmt->execute();
            $member_id = $stmt->insert_id;
            $stmt->close();

            foreach ($doc_fields as $field => $label) {
                [$filename, $upload_error] = handle_kyc_document_upload($field);
                if ($upload_error) {
                    throw new Exception($label . ': ' . $upload_error);
                }
                $stmt = $conn->prepare("INSERT INTO basics_kyc_documents (member_id, doc_type, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param('iss', $member_id, $field, $filename);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            if (!$already_logged_in) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['must_change_password'] = true;
            }
            redirect('/basics/pending.php?submitted=1');
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$page_title = 'Apply for Membership';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Join Us</span>
    <h1 class="stitle">Apply for <span>Basics Membership</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-8">
      <div class="panel-card">
        <?php if ($errors): ?>
          <div class="errmsg">
            <ul class="mb-0">
              <?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate>
          <?php if (!$already_logged_in): ?>
            <h2 class="h6 mb-3">Your Account</h2>
            <div class="mb-3">
              <label class="flbl">Full Name</label>
              <input type="text" name="full_name" class="fctrl" value="<?= sanitize($full_name) ?>" required>
            </div>
            <div class="mb-3">
              <label class="flbl">Address</label>
              <textarea name="address" class="fctrl" rows="2" required><?= sanitize($address) ?></textarea>
            </div>
            <div class="row">
              <div class="col-sm-6 mb-3">
                <label class="flbl">Birthdate</label>
                <input type="date" name="birthdate" class="fctrl" value="<?= sanitize($birthdate) ?>" required>
              </div>
              <div class="col-sm-6 mb-3">
                <label class="flbl">Contact Number</label>
                <input type="text" name="contact_number" class="fctrl" value="<?= sanitize($contact_number) ?>" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="flbl">Email Address</label>
              <input type="email" name="email" class="fctrl" value="<?= sanitize($email) ?>" required>
            </div>
            <div class="mb-3">
              <label class="flbl">Username</label>
              <input type="text" name="username" class="fctrl" value="<?= sanitize($username) ?>" required>
            </div>
            <div class="row">
              <div class="col-sm-6 mb-3">
                <label class="flbl">Temporary Password</label>
                <div class="pwd-field">
                  <input type="password" id="applyPassword" name="password" class="fctrl" required>
                  <button type="button" class="pwd-toggle" data-pwd-target="applyPassword" tabindex="-1" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
              </div>
              <div class="col-sm-6 mb-3">
                <label class="flbl">Confirm Password</label>
                <div class="pwd-field">
                  <input type="password" id="applyConfirmPassword" name="confirm_password" class="fctrl" required>
                  <button type="button" class="pwd-toggle" data-pwd-target="applyConfirmPassword" tabindex="-1" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <h2 class="h6 mb-3 mt-2">Employer Information</h2>
          <div class="mb-3">
            <label class="flbl">Employer / Company Name</label>
            <input type="text" name="employer_name" class="fctrl" value="<?= sanitize($employer_name) ?>" required>
          </div>
          <div class="row">
            <div class="col-sm-6 mb-3">
              <label class="flbl">Employer Contact (optional)</label>
              <input type="text" name="employer_contact" class="fctrl" value="<?= sanitize($employer_contact) ?>">
            </div>
            <div class="col-sm-6 mb-3">
              <label class="flbl">Position (optional)</label>
              <input type="text" name="position" class="fctrl" value="<?= sanitize($position) ?>">
            </div>
          </div>

          <h2 class="h6 mb-3 mt-2">Required Documents</h2>
          <div class="form-text mb-3">JPG, PNG, WEBP, or PDF — max 5MB each.</div>
          <div class="mb-3">
            <label class="flbl">Valid ID #1</label>
            <input type="file" name="valid_id_1" class="fctrl" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          </div>
          <div class="mb-3">
            <label class="flbl">Valid ID #2</label>
            <input type="file" name="valid_id_2" class="fctrl" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          </div>
          <div class="mb-3">
            <label class="flbl">Barangay Clearance</label>
            <input type="file" name="barangay_clearance" class="fctrl" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          </div>
          <div class="mb-3">
            <label class="flbl">Membership Application Form (signed)</label>
            <input type="file" name="membership_application_form" class="fctrl" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          </div>
          <div class="mb-3">
            <label class="flbl">Certificate of Employment / Company Work Clearance</label>
            <input type="file" name="certificate_of_employment" class="fctrl" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          </div>

          <button type="submit" class="btn-red w-100 justify-content-center"><i class="fas fa-paper-plane"></i>Submit Application</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
