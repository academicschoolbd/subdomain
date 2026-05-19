<?php
declare(strict_types=1);

/**
 * Telegram bot integration - sends notifications for new claims.
 */

/**
 * Send a message via the Telegram Bot API.
 * Returns true on success, false on failure. Never throws.
 */
function telegram_send_message(array $CONFIG, string $text, ?string $parseMode = 'HTML'): bool
{
    $tg = $CONFIG['telegram'] ?? [];
    $token = trim((string)($tg['bot_token'] ?? ''));
    $chatId = trim((string)($tg['chat_id'] ?? ''));
    if ($token === '' || $chatId === '') return false;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];
    if ($parseMode) $payload['parse_mode'] = $parseMode;

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode >= 200 && $httpCode < 300;
    } catch (\Throwable $e) {
        error_log('Telegram send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Notify about a new claim submission.
 * $claim should contain: slug, brand, name_en, category, contact_name, contact_phone, contact_email
 */
function telegram_notify_new_claim(array $CONFIG, array $claim): bool
{
    $subdomain = ($claim['slug'] ?? '') . '.' . ($claim['brand'] ?? '');
    $name = $claim['name_en'] ?? $claim['slug'] ?? 'Unknown';
    $category = $claim['category'] ?? 'N/A';
    $contact = $claim['contact_name'] ?? '';
    $phone = $claim['contact_phone'] ?? '';
    $email = $claim['contact_email'] ?? '';

    $lines = [
        "<b>New Claim Submitted</b>",
        "",
        "Domain: <code>{$subdomain}</code>",
        "Institution: " . htmlspecialchars($name, ENT_QUOTES),
        "Category: " . htmlspecialchars($category, ENT_QUOTES),
    ];
    if ($contact) $lines[] = "Contact: " . htmlspecialchars($contact, ENT_QUOTES);
    if ($phone) $lines[] = "Phone: " . htmlspecialchars($phone, ENT_QUOTES);
    if ($email) $lines[] = "Email: " . htmlspecialchars($email, ENT_QUOTES);
    $lines[] = "";
    $lines[] = "#new_claim";

    return telegram_send_message($CONFIG, implode("\n", $lines));
}

/**
 * Test the Telegram integration by sending a test message.
 * Returns ['ok' => bool, 'message' => string].
 */
function telegram_test(array $CONFIG): array
{
    $tg = $CONFIG['telegram'] ?? [];
    $token = trim((string)($tg['bot_token'] ?? ''));
    $chatId = trim((string)($tg['chat_id'] ?? ''));
    if ($token === '' || $chatId === '') {
        return ['ok' => false, 'message' => 'Telegram bot_token or chat_id not configured.'];
    }
    $ok = telegram_send_message($CONFIG, "Telegram integration is working!\n\nSent from institution.bd admin panel.");
    return $ok
        ? ['ok' => true, 'message' => 'Test message sent successfully!']
        : ['ok' => false, 'message' => 'Failed to send message. Check your bot token and chat ID.'];
}
