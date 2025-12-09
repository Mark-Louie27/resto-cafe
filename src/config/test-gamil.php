<?php
require 'vendor/autoload.php';
$config = include './email.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = 3; // Verbose debug output
    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->Port       = $config['smtp']['port'];

    // Recipients
    $mail->setFrom($config['smtp']['from'], $config['smtp']['from_name']);
    $mail->addAddress('ml.com', 'Test User');

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Gmail SMTP Test';
    $mail->Body    = 'This is a test email from Gmail SMTP';
    $mail->AltBody = 'This is a test email from Gmail SMTP';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
