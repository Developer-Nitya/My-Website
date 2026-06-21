<?php
declare(strict_types=1);

require __DIR__ . '/includes/product-catalog.php';
require_once __DIR__ . '/includes/order-management.php';

ensure_session_started();

$paymentConfig = app_config('manual_payment', []);
$orderWhatsAppNumber = (string) ($paymentConfig['order_whatsapp_number'] ?? '8801303329946');
$paymentNumber = (string) ($paymentConfig['payment_number'] ?? '01717618480');
$allowedPaymentMethods = array_values(array_filter(
    is_array($paymentConfig['payment_methods'] ?? null) ? $paymentConfig['payment_methods'] : ['bKash', 'Nagad', 'Rocket'],
    static fn (mixed $value): bool => is_string($value) && $value !== ''
));

$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$product = find_catalog_product($productId);

if (!$product) {
    http_response_code(404);
    exit('Requested content was not found.');
}

function checkout_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function create_order_reference(): string
{
    return 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_csrf'] ?? null)) {
        checkout_redirect('checkout.php?id=' . $productId . '&status=invalid');
    }

    $customerName = clean_text($_POST['customer_name'] ?? '', 150);
    $customerPhone = clean_text($_POST['customer_phone'] ?? '', 40);
    $customerEmail = strtolower(clean_text($_POST['customer_email'] ?? '', 190));
    $paymentMethod = clean_text($_POST['payment_method'] ?? '', 30);
    $paymentReference = clean_text($_POST['payment_reference'] ?? '', 120);

    if ($paymentReference !== '' && payment_reference_exists($GLOBALS['pdo'] ?? $pdo, $paymentReference)) {
        checkout_redirect('checkout.php?id=' . $productId . '&status=duplicate_payment');
    }

    $customerMessage = clean_multiline($_POST['customer_message'] ?? '', 1500);

    $checkoutSecurity = app_config('security')['checkout_form'] ?? [];
    $deliveryMethod = $customerEmail !== '' ? 'email' : 'whatsapp';
    $deliveryContact = $customerEmail !== '' ? $customerEmail : $customerPhone;
    $requiresPaymentReference = (bool) ($checkoutSecurity['require_payment_reference_for_premium'] ?? true)
        && strtolower((string) ($product['type'] ?? 'premium')) !== 'free';

    $rateKey = client_ip() . '|' . normalize_phone($customerPhone) . '|' . $productId;
    $rateStatus = rate_limit_status('checkout_submit', $rateKey);
    if ($rateStatus['blocked']) {
        checkout_redirect('checkout.php?id=' . $productId . '&status=invalid');
    }

    if (
        $customerName === ''
        || $customerPhone === ''
        || !is_valid_phone_number($customerPhone)
        || !in_array($paymentMethod, $allowedPaymentMethods, true)
        || !is_valid_email_address($customerEmail !== '' ? $customerEmail : null)
        || ($requiresPaymentReference && $paymentReference === '')
    ) {
        checkout_redirect('checkout.php?id=' . $productId . '&status=invalid');
    }

    if (!register_rate_limit_event(
        'checkout_submit',
        $rateKey,
        (int) ($checkoutSecurity['max_attempts'] ?? 5),
        (int) ($checkoutSecurity['window_seconds'] ?? 1200),
        (int) ($checkoutSecurity['block_seconds'] ?? 2700)
    )) {
        checkout_redirect('checkout.php?id=' . $productId . '&status=invalid');
    }

    $orderReference = create_order_reference();
    $pdo = db();
    $customerId = null;
    $orderStored = false;

    if ($pdo) {
        try {
            $customerId = upsert_customer($pdo, $customerName, $customerPhone, $customerEmail !== '' ? $customerEmail : null);

            $duplicateCutoff = date('Y-m-d H:i:s', time() - (int) ($checkoutSecurity['duplicate_window_seconds'] ?? 1800));
            $duplicateStmt = $pdo->prepare('
                SELECT id, order_reference
                FROM orders
                WHERE customer_id = :customer_id
                  AND content_item_id = :content_item_id
                  AND payment_method = :payment_method
                  AND payment_reference <=> :payment_reference
                  AND created_at >= :duplicate_cutoff
                ORDER BY id DESC
                LIMIT 1
            ');
            $duplicateStmt->execute([
                'customer_id' => $customerId,
                'content_item_id' => (int) $product['id'],
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'duplicate_cutoff' => $duplicateCutoff,
            ]);
            $existingOrder = $duplicateStmt->fetch();

            if ($existingOrder) {
                $orderReference = (string) $existingOrder['order_reference'];
                $orderStored = true;
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO orders
                    (customer_id, content_item_id, order_reference, order_source, order_status, amount, currency, payment_method, payment_number, payment_status, payment_reference, delivery_method, delivery_contact, customer_message)
                    VALUES
                    (:customer_id, :content_item_id, :order_reference, :order_source, :order_status, :amount, :currency, :payment_method, :payment_number, :payment_status, :payment_reference, :delivery_method, :delivery_contact, :customer_message)
                ');
                $paymentStatus = $paymentReference !== '' ? 'pending' : 'unpaid';

                $stmt->execute([
                    'customer_id' => $customerId,
                    'content_item_id' => (int) $product['id'],
                    'order_reference' => $orderReference,
                    'order_source' => 'website_form',
                    'order_status' => 'pending',
                    'amount' => is_numeric($product['price']) ? (float) $product['price'] : null,
                    'currency' => 'BDT',
                    'payment_method' => $paymentMethod,
                    'payment_number' => $paymentNumber,
                    'payment_status' => $paymentStatus,
                    'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                    'delivery_method' => $deliveryMethod,
                    'delivery_contact' => $deliveryContact,
                    'customer_message' => $customerMessage !== '' ? $customerMessage : null,
                ]);

                $orderId = (int) $pdo->lastInsertId();
                create_initial_order_status_logs($pdo, $orderId, [
                    'order_source' => 'website_form',
                    'order_status' => 'pending',
                    'payment_status' => $paymentStatus,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                    'delivery_status' => 'pending',
                    'delivery_method' => $deliveryMethod,
                    'delivery_contact' => $deliveryContact,
                ]);

                $orderStored = true;
            }
        } catch (Throwable $exception) {
            log_message('checkout_order_error', $exception->getMessage());
        }
    }

    if (!$orderStored) {
        backup_submission('manual-orders', [
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail !== '' ? $customerEmail : null,
            'product_id' => (int) $product['id'],
            'product_title' => (string) ($product['title'] ?? ''),
            'payment_method' => $paymentMethod,
            'payment_number' => $paymentNumber,
            'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
            'delivery_method' => $deliveryMethod,
            'delivery_contact' => $deliveryContact,
            'customer_message' => $customerMessage !== '' ? $customerMessage : null,
            'order_reference' => $orderReference,
            'backup_reason' => 'database_unavailable_or_insert_failed',
        ]);
    }

    $priceText = is_numeric($product['price']) ? ((string) $product['price'] . ' BDT') : (string) $product['price'];
    $whatsAppMessage = "Assalamu Alaikum, আমি একটি PPT order করতে চাই.\n\n"
        . "Order Ref: {$orderReference}\n"
        . "PPT Title: {$product['title']}\n"
        . "Education Level: {$product['level']}\n"
        . "Class: {$product['class']}\n"
        . "Subject: {$product['subject']}\n"
        . "Chapter: {$product['chapter']}\n"
        . "Price: {$priceText}\n"
        . "Payment Method: {$paymentMethod}\n"
        . "Payment Number: " . $paymentNumber . "\n"
        . "TxID: " . ($paymentReference !== '' ? $paymentReference : 'Not provided yet') . "\n"
        . "Customer Name: {$customerName}\n"
        . "Customer Phone: {$customerPhone}\n"
        . "Customer Email: " . ($customerEmail !== '' ? $customerEmail : 'N/A') . "\n"
        . "Message: " . ($customerMessage !== '' ? $customerMessage : 'N/A');

    $waUrl = 'https://wa.me/' . $orderWhatsAppNumber . '?text=' . rawurlencode($whatsAppMessage);
    checkout_redirect($waUrl);
}

$status = clean_text($_GET['status'] ?? '', 30);
$pageTitle = 'Order Checkout - ' . $product['title'];
$priceText = is_numeric($product['price']) ? ((string) $product['price'] . ' BDT') : (string) $product['price'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="container navbar-container">
        <a href="index.html" class="logo" aria-label="EduPPT BD">
            <img src="assets/images/header-logo.png" alt="EduPPT BD Logo" class="logo-img header-logo-img">
            <span class="logo-text">EduPPT <span>BD</span></span>
        </a>
        <nav class="nav-menu">
            <a href="index.html#home" class="nav-link">Home</a>
            <a href="catalog.php" class="nav-link">Browse</a>
            <a href="index.html#payment-instructions" class="nav-link">Payment</a>
            <a href="index.html#contact" class="nav-link">Contact</a>
        </nav>
    </div>
</header>

<section class="section-padding bg-light">
    <div class="container">
        <div class="checkout-layout">
            <div class="checkout-summary-card">
                <span class="badge-tag <?= $product['type'] === 'Free' ? 'badge-free' : 'badge-premium' ?>"><?= htmlspecialchars($product['type'], ENT_QUOTES, 'UTF-8') ?></span>
                <h1 class="checkout-title">Order Checkout</h1>
                <h2 class="checkout-product-title"><?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="product-meta-tags">
                    <span class="meta-tag"><?= htmlspecialchars($product['level'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="meta-tag"><?= htmlspecialchars($product['class'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="meta-tag"><?= htmlspecialchars($product['subject'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="checkout-summary-list">
                    <div class="spec-line"><span>অধ্যায়:</span><strong><?= htmlspecialchars($product['chapter'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="spec-line"><span>মোট স্লাইড:</span><strong><?= (int) $product['slides'] ?>টি</strong></div>
                    <div class="spec-line"><span>ফরম্যাট:</span><strong><?= htmlspecialchars($product['format'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="spec-line"><span>মূল্য:</span><strong><?= htmlspecialchars($priceText, ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>

                <div class="payment-option-cards">
                    <?php foreach ($allowedPaymentMethods as $method): ?>
                        <div class="payment-option-card"><h3><?= esc($method) ?></h3><p>Send Money</p><strong><?= esc($paymentNumber) ?></strong></div>
                    <?php endforeach; ?>
                </div>

                <p class="checkout-help-text">পেমেন্ট করার পর নিচের ফর্ম পূরণ করে সাবমিট করুন। সাবমিট করলে WhatsApp-এ অর্ডার মেসেজ অটো-ওপেন হবে।</p>
            </div>

            <div class="checkout-form-card">
                <h3>অর্ডার তথ্য দিন</h3>
                <?php if ($status === 'invalid'): ?>
                    <div class="checkout-status-error">অনুগ্রহ করে সঠিক নাম, ফোন, পেমেন্ট মেথড এবং প্রিমিয়াম কনটেন্টের ক্ষেত্রে TxID দিন।</div>
                <?php endif; ?>
                <form method="post" action="checkout.php?id=<?= (int) $product['id'] ?>" class="checkout-form">
                    <input type="hidden" name="_csrf" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <label>
                        <span>আপনার নাম</span>
                        <input type="text" name="customer_name" required>
                    </label>
                    <label>
                        <span>মোবাইল নাম্বার</span>
                        <input type="text" name="customer_phone" required>
                    </label>
                    <label>
                        <span>ইমেইল (ঐচ্ছিক)</span>
                        <input type="email" name="customer_email">
                    </label>
                    <label>
                        <span>পেমেন্ট মেথড</span>
                        <select name="payment_method" required>
                            <option value="">Select payment method</option>
                            <?php foreach ($allowedPaymentMethods as $method): ?>
                                <option value="<?= esc($method) ?>"><?= esc($method) ?> - <?= esc($paymentNumber) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>TxID / Reference</span>
                        <input type="text" name="payment_reference" placeholder="উদাহরণ: 8H3K91QP2">
                    </label>
                    <label>
                        <span>অতিরিক্ত বার্তা (ঐচ্ছিক)</span>
                        <textarea name="customer_message" rows="4" placeholder="কোনো কাস্টম চাহিদা থাকলে লিখুন"></textarea>
                    </label>
                    <button type="submit" class="btn btn-gradient" style="width:100%; justify-content:center;">Submit Order &amp; Open WhatsApp</button>
                    <a href="https://wa.me/<?= esc($orderWhatsAppNumber) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp" style="width:100%; justify-content:center; margin-top:12px;">Direct WhatsApp</a>
                </form>
            </div>
        </div>
    </div>
</section>
<script src="script.js"></script>
</body>
</html>