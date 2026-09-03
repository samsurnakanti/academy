<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();
ensure_enrollment_detail_columns();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'program') {
    $stmt = db()->prepare(
        "SELECT e.id, e.status, u.phone, c.title, c.fee, c.discount_fee, c.international_currency, c.international_fee, c.international_discount_fee
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Payment not found.');
    }
    if ($row && in_array($row['status'], ['paid', 'completed'], true)) {
        redirect('dashboard.php');
    }
    $attempt = db()->prepare('UPDATE enrollments SET program_payment_attempted_at = NOW() WHERE id = ? AND user_id = ?');
    $attempt->execute([(int) $row['id'], (int) $user['id']]);
    $currency = payment_currency_for_amount($row, 'program');
    $amount = payment_amount($row, 'program');
    $receipt = 'EA-PROGRAM-' . $id;
    $heading = 'Program Payment';
} elseif ($type === 'certificate') {
    $stmt = db()->prepare(
        "SELECT e.id, e.status, u.phone, c.title, c.fee, c.discount_fee, c.international_currency, c.international_fee, c.international_discount_fee, c.certification_fee, c.certificate_discount_fee, c.international_certification_fee, c.international_certificate_discount_fee
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Payment not found.');
    }
    $currency = payment_currency_for_amount($row, 'certificate');
    $amount = payment_amount($row, 'certificate');
    $receipt = 'EA-CERT-' . $id;
    $heading = 'Certificate Payment';
} else {
    $row = null;
    $currency = 'INR';
}

if ($amount <= 0) {
    http_response_code(404);
    exit('Payment not found.');
}

try {
    $order = create_razorpay_order($amount, $receipt, $currency);
} catch (Throwable $e) {
    http_response_code(500);
    exit(e($e->getMessage()));
}

$settings = razorpay_settings();
$title = $heading . ' | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-box">
    <div class="form-card">
        <p class="eyebrow"><?= e($heading) ?></p>
        <h1><?= e($row['title']) ?></h1>
        <p class="price-line"><?= e(money_in_currency($amount, $currency)) ?></p>
        <button id="pay-now" class="button primary" type="button">Pay Now</button>
    </div>
</section>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const options = {
    key: <?= json_encode($settings['key_id']) ?>,
    amount: <?= json_encode((int) $order['amount']) ?>,
    currency: <?= json_encode($currency) ?>,
    name: "Elldy Academy",
    description: <?= json_encode($heading) ?>,
    order_id: <?= json_encode($order['id']) ?>,
    handler: async function (response) {
        const form = new FormData();
        form.append("csrf_token", <?= json_encode(csrf_token()) ?>);
        form.append("type", <?= json_encode($type) ?>);
        form.append("id", <?= json_encode((int) $id) ?>);
        form.append("order_id", <?= json_encode($order['id']) ?>);
        form.append("razorpay_payment_id", response.razorpay_payment_id);
        form.append("razorpay_signature", response.razorpay_signature);

        const result = await fetch(<?= json_encode(public_url('razorpay_verify.php')) ?>, {
            method: "POST",
            body: form
        });
        const data = await result.json();
        window.location.href = data.redirect || <?= json_encode(public_url('dashboard.php')) ?>;
    }
};
document.getElementById("pay-now").addEventListener("click", () => new Razorpay(options).open());
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
