<?php
declare(strict_types=1);

/**
 * Lightweight mail helper.
 *
 *  - Encodes UTF-8 subjects (RFC 2047)
 *  - Sets safe From / Reply-To / Content-Type headers
 *  - Strips header-injection attempts from user-supplied values
 *  - Accepts a single string or array of recipients
 *  - Logs (does not expose) any send failure
 *
 * Returns true if at least one recipient accepted the message.
 */
function send_admin_notification(
    array $config,
    string $subject,
    string $body,
    ?string $replyToEmail = null,
    ?string $replyToName = null
): bool {
    $to = $config['notify_email'] ?? '';
    if (is_string($to)) {
        $to = trim($to) === '' ? [] : [$to];
    }
    if (!is_array($to) || count($to) === 0) {
        return false; // notifications disabled
    }

    $from     = $config['mail_from']      ?? ('no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $fromName = $config['mail_from_name'] ?? 'Website';

    // Sanitize anything that goes into headers (defence in depth)
    $from     = mailer_sanitize_header($from);
    $fromName = mailer_sanitize_header($fromName);
    $subject  = mailer_sanitize_header($subject);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';

    $headers   = [];
    $headers[] = 'From: ' . $encodedFrom;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if ($replyToEmail && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        $rtName = $replyToName ? mailer_sanitize_header($replyToName) : '';
        $headers[] = $rtName
            ? 'Reply-To: =?UTF-8?B?' . base64_encode($rtName) . '?= <' . $replyToEmail . '>'
            : 'Reply-To: ' . $replyToEmail;
    }

    $headerStr = implode("\r\n", $headers);
    $ok = false;

    foreach ($to as $recipient) {
        $recipient = trim((string)$recipient);
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $sent = @mail($recipient, $encodedSubject, $body, $headerStr, '-f' . $from);
        if ($sent) {
            $ok = true;
        } else {
            error_log('[mailer] failed to send to ' . $recipient);
        }
    }
    return $ok;
}

/**
 * Strip CR/LF and other characters that could be used to inject extra headers.
 */
function mailer_sanitize_header(string $value): string
{
    // Remove all control chars (CR, LF, NUL, etc.)
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    // Collapse whitespace and trim
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}
