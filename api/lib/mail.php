<?php
declare(strict_types=1);

/**
 * v3.2 — Outbound email helper.
 *
 * Two delivery paths, picked automatically:
 *
 *   1. **SMTP** when $CONFIG['mail']['transport'] === 'smtp' AND host is set.
 *      Pure PHP, no PHPMailer / Symfony Mailer dependency — opens a TCP
 *      socket, speaks ESMTP, supports STARTTLS or implicit TLS, and PLAIN
 *      / LOGIN authentication. Good enough for the half-dozen reset
 *      emails / day this platform sends.
 *
 *   2. **PHP `mail()`** otherwise. Useful on cPanel where the host already
 *      runs a local sendmail — zero config, but also zero deliverability
 *      guarantees once you scale.
 *
 * If both fail the function returns false and writes a single `error_log`
 * line so the caller (e.g. `route_auth_forgot_password`) can decide what
 * to surface to the user. We intentionally do NOT bubble up a hard error,
 * because forgot-password should always look uniform on the wire.
 *
 * Configurable via:
 *   $CONFIG['mail']['transport']  // 'smtp' or 'mail'
 *   $CONFIG['mail']['from_email'] // required (header `From:`)
 *   $CONFIG['mail']['from_name']  // optional display name
 *   $CONFIG['mail']['smtp_host']
 *   $CONFIG['mail']['smtp_port']  // 25 / 465 / 587
 *   $CONFIG['mail']['smtp_user']
 *   $CONFIG['mail']['smtp_pass']
 *   $CONFIG['mail']['smtp_secure']// '', 'tls' (STARTTLS), 'ssl' (implicit)
 */

/**
 * Send a transactional email. Returns true on success.
 *
 * $opts:
 *   to       (string, required)
 *   subject  (string, required)
 *   text     (string, plain-text body — required)
 *   html     (string, optional HTML alternative)
 *   reply_to (string, optional)
 */
function mail_send(array $CONFIG, array $opts): bool
{
    $to      = trim((string)($opts['to'] ?? ''));
    $subject = (string)($opts['subject'] ?? '');
    $text    = (string)($opts['text'] ?? '');
    $html    = (string)($opts['html'] ?? '');
    $replyTo = trim((string)($opts['reply_to'] ?? ''));

    if ($to === '' || $subject === '' || $text === '') {
        error_log('mail_send: missing to/subject/text');
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('mail_send: invalid recipient "' . $to . '"');
        return false;
    }

    $cfg       = $CONFIG['mail'] ?? [];
    $transport = strtolower((string)($cfg['transport'] ?? 'mail'));
    $fromEmail = trim((string)($cfg['from_email'] ?? ''));
    $fromName  = trim((string)($cfg['from_name']  ?? ($CONFIG['brand_name'] ?? 'institution.bd')));
    if ($fromEmail === '') {
        // Fall back to a no-reply on the public domain so headers are at least
        // syntactically valid. Many SMTP gateways reject bare-host envelopes.
        $host = parse_url((string)($CONFIG['site_url'] ?? 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
        $fromEmail = 'no-reply@' . $host;
    }

    // Build the MIME message once; both transports reuse it.
    [$rawHeaders, $rawBody] = mail_build_message($fromEmail, $fromName, $to, $subject, $text, $html, $replyTo);

    if ($transport === 'smtp' && trim((string)($cfg['smtp_host'] ?? '')) !== '') {
        $ok = mail_send_smtp($cfg, $fromEmail, $to, $rawHeaders, $rawBody);
        if ($ok) return true;
        error_log('mail_send: SMTP failed, falling back to PHP mail()');
    }

    // Fallback path — sendmail / postfix / cPanel default.
    // PHP's mail() wants Subject as an explicit arg and the rest as $headers.
    // Strip Subject + To from $rawHeaders to avoid duplicates.
    $hdrLines = preg_split("/\r\n/", $rawHeaders);
    $hdrLines = array_values(array_filter($hdrLines, static function ($h) {
        return $h !== '' && stripos($h, 'subject:') !== 0 && stripos($h, 'to:') !== 0;
    }));
    $headers = implode("\r\n", $hdrLines);
    return @mail($to, $subject, $rawBody, $headers, '-f' . $fromEmail);
}

/**
 * Compose the MIME envelope. Returns [headers, body].
 * If $html is non-empty we emit a multipart/alternative message with both
 * a text and an HTML part — the canonical form that every mail client
 * understands.
 */
function mail_build_message(string $fromEmail, string $fromName, string $to, string $subject, string $text, string $html, string $replyTo): array
{
    $boundary = 'b_' . bin2hex(random_bytes(8));
    $fromHdr  = $fromName !== '' ? sprintf('"%s" <%s>', addslashes($fromName), $fromEmail) : $fromEmail;

    $headers  = "From: $fromHdr\r\n";
    $headers .= "To: $to\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    if ($replyTo !== '') $headers .= "Reply-To: $replyTo\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . (parse_url('http://x', PHP_URL_HOST) ?: 'localhost') . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if ($html === '') {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $body = $text;
        return [rtrim($headers, "\r\n"), $body];
    }

    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $body  = "This is a multi-part message in MIME format.\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--$boundary--\r\n";
    return [rtrim($headers, "\r\n"), $body];
}

/**
 * Minimal ESMTP client. Returns true on a successful 250 after `.`.
 * Honors STARTTLS / implicit TLS, PLAIN and LOGIN auth. ~120 lines, no deps.
 */
function mail_send_smtp(array $cfg, string $fromEmail, string $to, string $headers, string $body): bool
{
    $host   = trim((string)$cfg['smtp_host']);
    $port   = (int)($cfg['smtp_port'] ?? 587);
    $user   = trim((string)($cfg['smtp_user'] ?? ''));
    $pass   = (string)($cfg['smtp_pass'] ?? '');
    $secure = strtolower(trim((string)($cfg['smtp_secure'] ?? '')));

    $remote = ($secure === 'ssl') ? 'ssl://' . $host : $host;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        error_log("mail_send_smtp: connect failed — $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = static function ($fp): string {
        $out = '';
        while (!feof($fp)) {
            $line = fgets($fp, 8192);
            if ($line === false) break;
            $out .= $line;
            // SMTP responses are line-terminated; a 4th char of " " (not "-")
            // marks the final line of a multiline reply.
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $out;
    };
    $write = static function ($fp, string $cmd): void { fwrite($fp, $cmd . "\r\n"); };
    $expect = static function (string $resp, string $code) use (&$lastResp): bool {
        $lastResp = $resp;
        return strpos(ltrim($resp), $code) === 0;
    };

    $banner = $read($fp);
    if (!$expect($banner, '220')) { fclose($fp); error_log('SMTP banner: ' . trim($banner)); return false; }

    // Identify ourselves with the public hostname when available.
    $heloHost = parse_url('http://x', PHP_URL_HOST) ?: 'localhost';
    if (!empty($_SERVER['SERVER_NAME'])) $heloHost = (string)$_SERVER['SERVER_NAME'];

    $write($fp, 'EHLO ' . $heloHost);
    $r = $read($fp);
    if (!$expect($r, '250')) { fclose($fp); error_log('SMTP EHLO: ' . trim($r)); return false; }

    if ($secure === 'tls') {
        $write($fp, 'STARTTLS');
        $r = $read($fp);
        if (!$expect($r, '220')) { fclose($fp); error_log('SMTP STARTTLS: ' . trim($r)); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); error_log('SMTP STARTTLS handshake failed'); return false;
        }
        // Re-EHLO over TLS.
        $write($fp, 'EHLO ' . $heloHost);
        $r = $read($fp);
        if (!$expect($r, '250')) { fclose($fp); error_log('SMTP EHLO 2: ' . trim($r)); return false; }
    }

    if ($user !== '') {
        $write($fp, 'AUTH LOGIN');
        $r = $read($fp);
        if (!$expect($r, '334')) { fclose($fp); error_log('SMTP AUTH: ' . trim($r)); return false; }
        $write($fp, base64_encode($user));
        $r = $read($fp);
        if (!$expect($r, '334')) { fclose($fp); error_log('SMTP user: ' . trim($r)); return false; }
        $write($fp, base64_encode($pass));
        $r = $read($fp);
        if (!$expect($r, '235')) { fclose($fp); error_log('SMTP pass: ' . trim($r)); return false; }
    }

    $write($fp, 'MAIL FROM:<' . $fromEmail . '>');
    $r = $read($fp);
    if (!$expect($r, '250')) { fclose($fp); error_log('SMTP MAIL FROM: ' . trim($r)); return false; }

    $write($fp, 'RCPT TO:<' . $to . '>');
    $r = $read($fp);
    if (!$expect($r, '250') && !$expect($r, '251')) { fclose($fp); error_log('SMTP RCPT TO: ' . trim($r)); return false; }

    $write($fp, 'DATA');
    $r = $read($fp);
    if (!$expect($r, '354')) { fclose($fp); error_log('SMTP DATA: ' . trim($r)); return false; }

    // Dot-stuff: any line that starts with "." must be doubled per RFC 5321.
    $msg = $headers . "\r\n\r\n" . $body;
    $msg = preg_replace("/(^|\r\n)\\.(?=\r\n|$)/", '$1..', $msg);
    fwrite($fp, $msg . "\r\n.\r\n");
    $r = $read($fp);
    if (!$expect($r, '250')) { fclose($fp); error_log('SMTP end-of-data: ' . trim($r)); return false; }

    $write($fp, 'QUIT');
    @fclose($fp);
    return true;
}
