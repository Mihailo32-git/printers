<?php
// Quote request handler for printers.newpage.me. Sends each request to office@newpage.me.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond($ok, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

// Spam trap: real visitors never fill this hidden field.
if (!empty($_POST['website'])) {
    respond(true);
}

$clean = function ($v, $max) {
    $v = trim((string)$v);
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
};

$name    = $clean($_POST['name'] ?? '', 120);
$email   = $clean($_POST['email'] ?? '', 160);
$volume  = $clean($_POST['volume'] ?? '', 60);
$message = trim(str_replace("\0", '', (string)($_POST['message'] ?? '')));
$message = mb_substr($message, 0, 5000);

$allowed = ['Up to 2,000', '2,000 to 5,000', '5,000 to 10,000', 'More than 10,000'];
if (!in_array($volume, $allowed, true)) {
    $volume = 'Not given';
}

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please fill in your name and a valid email address.', 422);
}
// No line breaks allowed in values that go into mail headers.
if (preg_match('/[\n\r]/', $name . $email)) {
    respond(false, 'Please check your name and email address.', 422);
}

// Simple rate limit: one request per visitor every 30 seconds.
session_start();
$now = time();
if (!empty($_SESSION['np_last_send']) && $now - $_SESSION['np_last_send'] < 30) {
    respond(false, 'Please wait a moment before sending another request.', 429);
}

$to      = 'office@newpage.me';
$subject = '=?UTF-8?B?' . base64_encode('printers.newpage.me – quote request – ' . $name) . '?=';
$body    = "New quote request from printers.newpage.me\n\n"
         . "Name: $name\n"
         . "Email: $email\n"
         . "Pages per month: $volume\n\n"
         . "Message:\n" . ($message === '' ? '(none)' : $message) . "\n";
$headers = [
    'From: New Page website <noreply@newpage.me>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

if (mail($to, $subject, $body, implode("\r\n", $headers))) {
    $_SESSION['np_last_send'] = $now;
    respond(true);
}
respond(false, 'Sorry, the request could not be sent. Please email office@newpage.me.', 500);
