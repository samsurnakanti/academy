<?php
require_once __DIR__ . '/includes/functions.php';
ensure_enrollment_detail_columns();
ensure_course_detail_columns();
$user = current_user();

$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM courses WHERE id = ? AND is_active = 1');
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    exit('Program not found.');
}

$isFreeProgram = course_fee_amount($course) <= 0;
$showFeeDetails = course_should_show_fee_details($course);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = normalize_whatsapp_number(trim($_POST['phone'] ?? ''));
    $studentBackground = trim($_POST['student_background'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        flash('error', 'Please enter your name, email, and phone number.');
    } else {
        $userStmt = db()->prepare('SELECT id, phone FROM users WHERE email = ?');
        $userStmt->execute([$email]);
        $student = $userStmt->fetch();

        if ($student) {
            $userId = (int) $student['id'];
            $updateUser = db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
            $updateUser->execute([$name, $phone, $userId]);
        } else {
            $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $createUser = db()->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
            $createUser->execute([$name, $email, $phone, $passwordHash]);
            $userId = (int) db()->lastInsertId();
        }

        $existing = db()->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
        $existing->execute([$userId, $courseId]);
        $enrollment = $existing->fetch();

        if ($enrollment) {
            $update = db()->prepare(
                'UPDATE enrollments
                 SET status = ?, student_background = ?, daily_reminders_enabled = ?
                 WHERE id = ?'
            );
            $enrollmentStatus = $isFreeProgram ? 'free_access' : 'payment_pending';
            $update->execute([$enrollmentStatus, $studentBackground, 0, (int) $enrollment['id']]);
            flash('success', $isFreeProgram ? 'Your free program enrollment has been updated. You can watch the videos from your dashboard.' : 'Your registration has been saved. Please complete the program payment to watch videos or join sessions.');
        } else {
            $insert = db()->prepare(
                'INSERT INTO enrollments (user_id, course_id, status, student_background, daily_reminders_enabled)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $enrollmentStatus = $isFreeProgram ? 'free_access' : 'payment_pending';
            $insert->execute([$userId, $courseId, $enrollmentStatus, $studentBackground, 0]);
            flash('success', $isFreeProgram ? 'Free program enrollment successful. You can watch the videos from your dashboard.' : 'Registration successful. Please complete the program payment to watch videos or join sessions.');
        }

        $enrolledUser = ['name' => $name, 'phone' => $phone];
        if (!send_enrollment_whatsapp($enrolledUser, $course)) {
            flash('error', $_SESSION['whatsapp_send_error'] ?? 'Enrollment saved, but WhatsApp confirmation could not be sent.');
        }

        if ($user && (int) $user['id'] === $userId) {
            redirect('dashboard.php');
        }
    }
}

$title = $isFreeProgram ? 'Free Program Enrollment | Elldy Academy' : 'Program Enrollment | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="enroll-layout">
    <aside class="enroll-intro">
        <p class="eyebrow"><?= $isFreeProgram ? 'Free program' : 'Paid program' ?></p>
        <h1><?= e($course['title']) ?></h1>
        <p>Join Elldy Academy and learn how business cases become dashboards, KPIs, insight notes, and decision-ready BI reports for the Elldy intelligence ecosystem.</p>
        <div class="stats-row">
            <span><strong>Duration</strong><?= e($course['duration']) ?></span>
            <?php if ($showFeeDetails): ?>
                <span><strong>Program fee</strong><?= price_html($course, 'fee', 'discount_fee') ?></span>
            <?php endif; ?>
            <?php if (!$showFeeDetails): ?>
                <span><strong>Access</strong>Enrollment available</span>
            <?php elseif ($isFreeProgram): ?>
                <span><strong>Access</strong>Entire program free</span>
            <?php else: ?>
                <span><strong>Access</strong>Payment required</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($course['learning_plan']) || !empty($course['completion_benefits'])): ?>
            <div class="registration-preview">
                <?php if (!empty($course['learning_plan'])): ?>
                    <div>
                        <strong>What you will learn</strong>
                        <?= detail_points($course['learning_plan']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($course['completion_benefits'])): ?>
                    <div>
                        <strong>What you get after completion</strong>
                        <?= detail_points($course['completion_benefits']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($course['expert_name']) || !empty($course['expert_bio'])): ?>
            <div class="expert-card compact">
                <?php if (!empty($course['expert_photo'])): ?>
                    <img src="<?= e($course['expert_photo']) ?>" alt="<?= e($course['expert_name'] ?: 'Elldy Expert') ?>">
                <?php endif; ?>
                <div>
                    <p class="eyebrow">Elldy Expert</p>
                    <h2><?= e($course['expert_name'] ?: 'Expert details') ?></h2>
                    <?php if (!empty($course['expert_title'])): ?>
                        <strong><?= e($course['expert_title']) ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
        <p class="eyebrow">No login required</p>
        <h1>Enroll Now</h1>
        <p><?= $isFreeProgram ? 'Share your trainee details to enroll in this free program and watch the available videos.' : 'Share your trainee details. Program videos and live sessions unlock after payment.' ?></p>

        <fieldset>
            <legend>Trainee Information</legend>
            <label>Trainee name
                <input name="name" value="<?= e($user['name'] ?? '') ?>" placeholder="Enter full name" required>
            </label>
            <label>Email address
                <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="name@example.com" required>
            </label>
            <label>Phone number
                <input name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="Mobile or WhatsApp number" required>
            </label>
        </fieldset>

        <fieldset>
            <legend>Current Profile</legend>
            <label>Trainee details / current background
                <textarea name="student_background" rows="3" placeholder="Example: college learner, working professional, business owner, beginner"></textarea>
            </label>
        </fieldset>
        <button class="button primary" type="submit">Enroll Now</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
