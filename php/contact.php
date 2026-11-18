<?php
/**
 * Contact form handler.
 *
 * GET  contact.php?action=token  -> JSON { token }  (CSRF token + session start)
 * POST contact.php               -> JSON { success, message, errors? }
 *
 * Protections: CSRF token, honeypot field, minimum fill time, per-session and
 * per-IP rate limiting, strict server-side validation, header-injection safe mail,
 * prepared statements for the optional database insert.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../includes/config.php';

// Hardened session cookie
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean(string $value, int $max): string
{
    $value = trim($value);
    // strip control characters (keeps newlines/tabs; those are handled per field)
    $value = preg_replace('/[^\P{C}\n\t]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/** IP-based rate limit using small files in the system temp dir. */
function ip_rate_ok(string $ip, int $max, int $window, bool $record): bool
{
    $file = sys_get_temp_dir() . '/portfolio_rl_' . hash('sha256', $ip) . '.json';
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $raw  = json_decode((string) @file_get_contents($file), true);
        $hits = is_array($raw) ? array_values(array_filter($raw, fn($t) => is_int($t) && $t > $now - $window)) : [];
    }
    if (count($hits) >= $max) {
        return false;
    }
    if ($record) {
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
    }
    return true;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- Token endpoint ---------- */
if ($method === 'GET') {
    if (($_GET['action'] ?? '') !== 'token') {
        respond(['success' => false, 'message' => 'Not found.'], 404);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['form_time']  = time();
    respond(['token' => $_SESSION['csrf_token']]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

/* ---------- Spam checks ---------- */

// 1. CSRF token
$sent  = (string) ($_POST['csrf_token'] ?? '');
$saved = (string) ($_SESSION['csrf_token'] ?? '');
if ($saved === '' || !hash_equals($saved, $sent)) {
    respond(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], 403);
}

// 2. Honeypot — real visitors never see or fill this field. Pretend success to bots.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    unset($_SESSION['csrf_token']);
    respond(['success' => true, 'message' => 'Thank you! Your message has been sent.']);
}

// 3. Minimum time to fill the form
$elapsed = time() - (int) ($_SESSION['form_time'] ?? time());
if ($elapsed < (int) $config['min_fill_seconds']) {
    respond(['success' => false, 'message' => 'That was a little too fast. Please try again in a moment.'], 429);
}

// 4. Rate limiting (session + IP)
$max    = (int) $config['rate_limit_max'];
$window = (int) $config['rate_limit_window'];
$now    = time();
$_SESSION['sent_at'] = array_values(array_filter(
    $_SESSION['sent_at'] ?? [],
    fn($t) => is_int($t) && $t > $now - $window
));
if (count($_SESSION['sent_at']) >= $max || !ip_rate_ok(client_ip(), $max, $window, false)) {
    respond(['success' => false, 'message' => 'You have sent several messages recently. Please wait a few minutes, or contact me on WhatsApp.'], 429);
}

/* ---------- Validation ---------- */
$name    = clean((string) ($_POST['name'] ?? ''), 80);
$email   = clean((string) ($_POST['email'] ?? ''), 120);
$phone   = clean((string) ($_POST['phone'] ?? ''), 20);
$service = clean((string) ($_POST['service'] ?? ''), 100);
$message = clean((string) ($_POST['message'] ?? ''), 3000);

$allowedServices = [
    '', 'Website Development', 'Google Business Profile Optimization', 'WhatsApp Business Automation',
    'Product Catalogue Website', 'E-commerce Website', 'Wholesale Lead Generation',
    'Order / Customer / Inventory Management System', 'Digital Marketing & Social Media Support', 'Something else',
];

$errors = [];
if (mb_strlen($name) < 2) {
    $errors['name'] = 'Please enter your name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
    $errors['email'] = 'Enter a valid email address.';
}
if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
    $errors['phone'] = 'Enter a valid phone number.';
}
if (mb_strlen($message) < 10) {
    $errors['message'] = 'Please write at least 10 characters.';
}
if (!in_array($service, $allowedServices, true)) {
    $service = '';
}
// Crude link-spam guard: too many URLs in one message
if (preg_match_all('~https?://~i', $message) > 2) {
    $errors['message'] = 'Please remove extra links from your message.';
}

if ($errors) {
    respond(['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
}

/* ---------- Optional database storage ---------- */
$stored = false;
if (!empty($config['db']['enabled'])) {
    try {
        $db  = $config['db'];
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $stmt = $pdo->prepare(
            'INSERT INTO contact_messages (name, email, phone, service, message, ip_address, user_agent)
             VALUES (:name, :email, :phone, :service, :message, :ip, :ua)'
        );
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':phone'   => $phone !== '' ? $phone : null,
            ':service' => $service !== '' ? $service : null,
            ':message' => $message,
            ':ip'      => client_ip(),
            ':ua'      => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
        $stored = true;
    } catch (Throwable $e) {
        error_log('[portfolio] DB insert failed: ' . $e->getMessage());
    }
}

/* ---------- Send email ---------- */
$subjectName = preg_replace('/[\r\n]+/', ' ', $name);
$subject     = '=?UTF-8?B?' . base64_encode("New portfolio inquiry from {$subjectName}") . '?=';

$body  = "New inquiry from your portfolio website\n";
$body .= str_repeat('-', 44) . "\n";
$body .= "Name:    {$name}\n";
$body .= "Email:   {$email}\n";
$body .= 'Phone:   ' . ($phone !== '' ? $phone : '-') . "\n";
$body .= 'Service: ' . ($service !== '' ? $service : '-') . "\n";
$body .= "IP:      " . client_ip() . "\n";
$body .= 'Time:    ' . date('d M Y, h:i A T') . "\n\n";
$body .= "Message:\n{$message}\n";

$replyName = str_replace(["\r", "\n", '"', '<', '>', ','], '', $name);
$fromName  = str_replace(["\r", "\n", '"', '<', '>', ','], '', $config['site_name']);
$headers = [
    'From: ' . $fromName . ' <' . $config['mail_from'] . '>',
    'Reply-To: ' . $replyName . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];

$mailed = @mail($config['mail_to'], $subject, $body, implode("\r\n", $headers));

if (!$mailed && !$stored) {
    error_log('[portfolio] mail() failed and DB storage is off or failed.');
    respond([
        'success' => false,
        'message' => 'Sorry, your message could not be sent right now. Please contact me on WhatsApp or by phone.',
    ], 500);
}

/* ---------- Success ---------- */
$_SESSION['sent_at'][] = $now;
ip_rate_ok(client_ip(), $max, $window, true);
unset($_SESSION['csrf_token']);

respond(['success' => true, 'message' => 'Thank you, ' . $name . '! Your message has been sent. I will get back to you soon.']);
