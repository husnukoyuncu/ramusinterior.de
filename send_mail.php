<?php
// send_mail.php

// PHPMailer sınıflarını dahil et
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data) {
        $name = strip_tags(trim($data["name"] ?? ''));
        $email = filter_var(trim($data["email"] ?? ''), FILTER_SANITIZE_EMAIL);
        $subject_input = strip_tags(trim($data["subject"] ?? ''));
        $message = trim($data["message"] ?? '');
    } else {
        $name = strip_tags(trim($_POST["name"] ?? ''));
        $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
        $subject_input = strip_tags(trim($_POST["subject"] ?? ''));
        $message = trim($_POST["message"] ?? '');
    }

    if (empty($name) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Lütfen formu eksiksiz doldurun."]);
        exit;
    }

    // E-posta konusu
    $subject = empty($subject_input) ? "Websitesi İletişim Formu: $name" : $subject_input;

    $mailConfigPath = __DIR__ . '/mail-config.php';
    if (!file_exists($mailConfigPath)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Mail yapılandırması eksik (mail-config.php bulunamadı)."]);
        exit;
    }
    $mailConfig = require $mailConfigPath;

    // PHPMailer Kurulumu
    $mail = new PHPMailer(true);

    try {
        // Sunucu Ayarları
        $mail->isSMTP();                                            // SMTP kullan
        $mail->Host       = $mailConfig['host'];                    // SMTP sunucu adresi
        $mail->SMTPAuth   = true;                                   // SMTP doğrulamasını aç
        $mail->Username   = $mailConfig['username'];                // Oluşturduğunuz tam e-posta adresi
        $mail->Password   = $mailConfig['password'];                // E-posta adresinin şifresi (mail-config.php içinde, git'e girmez)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            // SSL şifreleme (cPanel genelde SSL port 465 kullanır)
        $mail->Port       = $mailConfig['port'];                    // SSL için port (Eğer 587 ise SMTPSecure = ENCRYPTION_STARTTLS yapın)
        $mail->CharSet    = 'UTF-8';

        // Gönderici ve Alıcı Ayarları
        $mail->setFrom('noreply@ramusinterior.de', 'Ramus Interior Form'); // Gönderici adresi ve görünen isim
        $mail->addReplyTo($email, $name);                                  // Ziyaretçinin adresi (yanıtla dediğinizde ona gitsin)
        
        // Alıcılar
        $mail->addAddress('info@ramusinterior.com');                // 1. Alıcı
        $mail->addAddress('koyuncuhusnu@gmail.com');                // 2. Alıcı

        // İçerik
        $mail->isHTML(true);                                        // HTML formatında gönder
        $mail->Subject = $subject;
        
        $htmlContent = "
            <h3>Web sitenizden yeni bir mesaj aldınız</h3>
            <p><strong>Ad/Soyad:</strong> {$name}</p>
            <p><strong>E-posta:</strong> {$email}</p>
            <p><strong>Mesaj:</strong><br/>" . nl2br(htmlspecialchars($message)) . "</p>
        ";
        $mail->Body    = $htmlContent;
        $mail->AltBody = "Ad/Soyad: {$name}\nE-posta: {$email}\n\nMesaj:\n{$message}"; // HTML desteklemeyen istemciler için düz metin

        // Gönder!
        $mail->send();
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Mesajınız gönderildi."]);

    } catch (Exception $e) {
        http_response_code(500);
        // Hata ayıklama modunda görmek isterseniz $mail->ErrorInfo değişkenini yazdırabilirsiniz
        echo json_encode(["status" => "error", "message" => "Mesaj gönderilemedi. Hata: {$mail->ErrorInfo}"]);
    }

} else {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Geçersiz istek."]);
}
