<?php
function mail_cfg($key) {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/mail.php';
    return $cfg[$key] ?? null;
}

// Creates a random token. Only its SHA-256 hash is stored; the raw token only exists in the email link.
// Expires after 24 hours. Uses DB time for both set and check so clocks can't disagree.
function issue_reset_token(PDO $pdo, int $userId): string {
    $raw = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE users SET reset_token_hash = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?')
        ->execute([hash('sha256', $raw), $userId]);
    return $raw;
}

function send_mail(string $to, string $subject, string $html): bool {
    if (mail_cfg('mode') === 'smtp') {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) return false;       // run: composer require phpmailer/phpmailer
        require_once $autoload;
        try {
            $m = new PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host = mail_cfg('host');
            $m->SMTPAuth = true;
            $m->Username = mail_cfg('username');
            $m->Password = mail_cfg('password');
            $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $m->Port = (int)mail_cfg('port');
            $m->setFrom(mail_cfg('from'), mail_cfg('from_name'));
            $m->addAddress($to);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body = $html;
            $m->send();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
    // Dev mode: append to a log file (storage/ is blocked from the web by .htaccess)
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $line = "=== " . date('Y-m-d H:i:s') . " | To: $to | $subject ===\n" . strip_tags(str_replace('<br>', "\n", $html)) . "\n\n";
    return file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
}

// Builds and sends the "set your password" email. Returns [sent?, link]
function send_setup_email(PDO $pdo, int $userId, string $email, string $name, bool $isReset = false): array {
    $token = issue_reset_token($pdo, $userId);
    $link  = rtrim(mail_cfg('app_url'), '/') . '/set_password.php?token=' . $token;
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    if ($isReset) {
        $subject = 'ExamSlot: reset your password';
        $body = "Hello $n,<br><br>Use the link below to set a new password. It can be used once and expires in 24 hours.<br><br><a href=\"$link\">$link</a><br><br>If you did not ask for this, ignore this email.";
    } else {
        $subject = 'Your ExamSlot portal account has been created';
        $body = "Hello $n,<br><br>A student portal account has been created for you on ExamSlot. Use the link below to set your password. It can be used once and expires in 24 hours.<br><br><a href=\"$link\">$link</a><br><br>After that, log in with this email address and your new password.";
    }
    return [send_mail($email, $subject, $body), $link];
}
