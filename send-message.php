<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$recipientEmail = (string) app_config('contact_recipient_email', 'urdokan@gmail.com');
$siteName = (string) app_config('site_name', 'EduPPT BD');
$defaultSubject = 'EduPPT BD - নতুন কাস্টম রিকোয়েস্ট';

function send_response(bool $success, string $message, int $statusCode = 200): never
{
    http_response_code($statusCode);

    if (wants_json_response()) {
        send_json([
            'success' => $success,
            'message' => $message,
        ], $statusCode);
    }

    header('Content-Type: text/html; charset=UTF-8');
    $title = $success ? 'বার্তা পাঠানো হয়েছে' : 'বার্তা পাঠানো যায়নি';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$title.'</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body{background:#f8fafc;font-family:"Hind Siliguri","Noto Sans Bengali",Arial,sans-serif;}
        .mail-status-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
        .mail-status-card{max-width:620px;background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:34px;box-shadow:0 20px 55px rgba(15,23,42,.10);text-align:center;}
        .mail-status-card h1{margin-bottom:14px;color:#0f172a;}
        .mail-status-card p{color:#475569;line-height:1.75;}
        .mail-status-card a{display:inline-block;margin-top:18px;text-decoration:none;}
    </style>
</head>
<body>
    <div class="mail-status-wrap">
        <div class="mail-status-card">
            <h1>'.$title.'</h1>
            <p>'.$safeMessage.'</p>
            <a href="index.html#contact" class="btn btn-gradient">ওয়েবসাইটে ফিরে যান</a>
        </div>
    </div>
</body>
</html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, 'Invalid request method.', 405);
}

if (!is_same_origin_request()) {
    send_response(false, 'Invalid request origin.', 403);
}

if (!empty($_POST['website'])) {
    send_response(true, 'ধন্যবাদ। আপনার বার্তা গ্রহণ করা হয়েছে।');
}

$name = clean_text($_POST['name'] ?? '', 120);
$phone = clean_text($_POST['phone'] ?? '', 40);
$email = strtolower(clean_text($_POST['email'] ?? '', 190));
$userMessage = clean_multiline($_POST['message'] ?? '', 5000);
$formSubject = clean_text($_POST['form_subject'] ?? $defaultSubject, 255);

if ($name === '' || $phone === '' || $userMessage === '') {
    send_response(false, 'অনুগ্রহ করে নাম, ফোন নাম্বার এবং বার্তা পূরণ করুন।', 422);
}

if (!is_valid_phone_number($phone)) {
    send_response(false, 'সঠিক ফোন নাম্বার দিন।', 422);
}

if (!is_valid_email_address($email !== '' ? $email : null)) {
    send_response(false, 'সঠিক ইমেইল এড্রেস দিন।', 422);
}

$contactSecurity = app_config('security')['contact_form'] ?? [];
$rateKey = client_ip() . '|' . normalize_phone($phone);
$rateStatus = rate_limit_status('contact_submit', $rateKey);
if ($rateStatus['blocked']) {
    send_response(false, 'অল্প সময়ের মধ্যে অনেকবার বার্তা পাঠানো হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।', 429);
}

if (!register_rate_limit_event(
    'contact_submit',
    $rateKey,
    (int) ($contactSecurity['max_attempts'] ?? 5),
    (int) ($contactSecurity['window_seconds'] ?? 600),
    (int) ($contactSecurity['block_seconds'] ?? 1800)
)) {
    send_response(false, 'অল্প সময়ের মধ্যে অনেকবার বার্তা পাঠানো হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।', 429);
}

$submittedAt = date('Y-m-d H:i:s');
$ipAddress = client_ip();
$userAgent = clean_text((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 500);
$mailSent = false;
$messageSaved = false;

$pdo = db();
if ($pdo) {
    try {
        $duplicateCutoff = date('Y-m-d H:i:s', time() - (int) ($contactSecurity['duplicate_window_seconds'] ?? 900));
        $duplicateStmt = $pdo->prepare('
            SELECT id
            FROM contact_messages
            WHERE phone = :phone
              AND message = :message
              AND created_at >= :duplicate_cutoff
            LIMIT 1
        ');
        $duplicateStmt->execute([
            'phone' => $phone,
            'message' => $userMessage,
            'duplicate_cutoff' => $duplicateCutoff,
        ]);

        if ($duplicateStmt->fetchColumn()) {
            send_response(true, 'আপনার একই বার্তা ইতোমধ্যে গ্রহণ করা হয়েছে।');
        }

        $customerId = upsert_customer($pdo, $name, $phone, $email !== '' ? $email : null);

        $insert = $pdo->prepare('
            INSERT INTO contact_messages
            (customer_id, full_name, phone, email, subject, message, source_page, ip_address, user_agent, mail_delivery_status)
            VALUES
            (:customer_id, :full_name, :phone, :email, :subject, :message, :source_page, :ip_address, :user_agent, :mail_delivery_status)
        ');
        $insert->execute([
            'customer_id' => $customerId,
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'subject' => $formSubject,
            'message' => $userMessage,
            'source_page' => 'index.html#contact',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'mail_delivery_status' => 'pending',
        ]);
        $messageId = (int) $pdo->lastInsertId();
        $messageSaved = true;
    } catch (Throwable $exception) {
        log_message('contact_message_store_error', $exception->getMessage());
        $messageId = 0;
    }
} else {
    $messageId = 0;
}

if (!$messageSaved) {
    backup_submission('contact-messages', [
        'full_name' => $name,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'subject' => $formSubject,
        'message' => $userMessage,
        'source_page' => 'index.html#contact',
        'mail_delivery_status' => 'pending_backup',
    ]);
}

$serverName = isset($_SERVER['SERVER_NAME']) ? preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string) $_SERVER['SERVER_NAME']) : 'localhost';
$fromDomain = $serverName !== '' ? $serverName : 'localhost';
$fromEmail = 'no-reply@' . $fromDomain;
$subject = '=?UTF-8?B?' . base64_encode($formSubject) . '?=';

$emailBody = "নতুন বার্তা এসেছে {$siteName} ওয়েবসাইট থেকে\n\n"
    . "সময়: {$submittedAt}\n"
    . "নাম: {$name}\n"
    . "ফোন: {$phone}\n"
    . "ইমেইল: " . ($email !== '' ? $email : 'N/A') . "\n"
    . "বিষয়: {$formSubject}\n"
    . "IP: {$ipAddress}\n\n"
    . "বার্তা:\n{$userMessage}\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: '.$siteName.' <'.$fromEmail.'>',
    'Reply-To: '.($email !== '' ? $email : $fromEmail),
];

$mailSent = @mail($recipientEmail, $subject, $emailBody, implode("\r\n", $headers));

if ($messageSaved && $pdo && $messageId > 0) {
    try {
        $pdo->prepare('UPDATE contact_messages SET mail_delivery_status = :status WHERE id = :id')->execute([
            'status' => $mailSent ? 'sent' : 'failed',
            'id' => $messageId,
        ]);
    } catch (Throwable $exception) {
        log_message('contact_message_update_error', $exception->getMessage());
    }
}

if ($messageSaved) {
    send_response(true, $mailSent ? 'ধন্যবাদ। আপনার বার্তা সফলভাবে পাঠানো হয়েছে।' : 'ধন্যবাদ। আপনার বার্তা সংরক্ষণ করা হয়েছে। ইমেইল পাঠানো না গেলেও আমরা ড্যাশবোর্ডে বার্তাটি পেয়েছি।');
}

send_response($mailSent, $mailSent ? 'ধন্যবাদ। আপনার বার্তা সফলভাবে পাঠানো হয়েছে।' : 'দুঃখিত, এই মুহূর্তে বার্তা সংরক্ষণ করা যায়নি। পরে আবার চেষ্টা করুন।', $mailSent ? 200 : 500);
