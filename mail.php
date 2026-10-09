<?php
// Mail settings. Copy mail.local.example.php to mail.local.php and edit it (never commit that file).
$defaults = [
    'mode'     => 'log',                        // 'log' = write emails to storage/mail.log (dev), 'smtp' = really send
    'app_url'  => 'http://localhost/examslot',  // used to build links in emails
    'host'     => 'sandbox.smtp.mailtrap.io',
    'port'     => 587,
    'username' => '',
    'password' => '',
    'from'     => 'no-reply@examslot.test',
    'from_name'=> 'ExamSlot',
];
$local = __DIR__ . '/mail.local.php';
return array_merge($defaults, file_exists($local) ? (require $local) : []);
