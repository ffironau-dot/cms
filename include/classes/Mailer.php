<?php
/**
 * Отправка писем с заявками.
 *
 * Если в конфигурации задан SMTP-сервер, письмо отправляется по SMTP
 * (поддерживаются SSL на порту 465, STARTTLS и соединение без шифрования,
 * авторизация AUTH LOGIN). Если SMTP не задан — через функцию mail().
 * Сторонние библиотеки не используются.
 */

declare(strict_types=1);

namespace Core;

final class Mailer
{
    /** Таймаут сетевых операций SMTP, секунд. */
    private const TIMEOUT = 20;

    /** @var resource|null */
    private $socket = null;

    /**
     * Отправить текстовое письмо получателю из конфигурации.
     *
     * @throws \RuntimeException при ошибке отправки
     */
    public static function send(string $subject, string $body): void
    {
        $to = trim((string) Config::get('mail_to', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Не задан корректный email получателя заявок в include/connect.php.');
        }
        $from = trim((string) Config::get('mail_from', ''));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $host = (string) parse_url(Config::siteUrl(), PHP_URL_HOST);
            $from = 'noreply@' . ($host !== '' ? $host : 'localhost');
        }
        $smtpHost = trim((string) Config::get('smtp_host', ''));
        if ($smtpHost === '') {
            self::sendViaMail($to, $from, $subject, $body);
            return;
        }
        (new self())->sendViaSmtp($smtpHost, (int) Config::get('smtp_port', 587), (string) Config::get('smtp_secure', ''),
            (string) Config::get('smtp_user', ''), (string) Config::get('smtp_pass', ''), $from, $to, $subject, $body);
    }

    /** Заголовок темы в кодировке UTF-8 (RFC 2047). */
    private static function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    private static function sendViaMail(string $to, string $from, string $subject, string $body): void
    {
        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $ok = @mail($to, self::encodeHeader($subject), chunk_split(base64_encode($body)), implode("\r\n", $headers));
        if (!$ok) {
            throw new \RuntimeException('Функция mail() не смогла отправить письмо.');
        }
    }

    private function sendViaSmtp(string $host, int $port, string $secure, string $user, string $pass,
                                 string $from, string $to, string $subject, string $body): void
    {
        $secure = strtolower(trim($secure));
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . ($port ?: ($secure === 'ssl' ? 465 : 587));
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            throw new \RuntimeException('SMTP: не удалось подключиться к ' . $host . ': ' . $errstr);
        }
        $this->socket = $socket;
        stream_set_timeout($socket, self::TIMEOUT);
        try {
            $this->expect(220);
            $ehloHost = (string) (parse_url(Config::siteUrl(), PHP_URL_HOST) ?: 'localhost');
            $this->command('EHLO ' . $ehloHost, 250);
            if ($secure === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP: не удалось включить шифрование STARTTLS.');
                }
                $this->command('EHLO ' . $ehloHost, 250);
            }
            if ($user !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($user), 334);
                $this->command(base64_encode($pass), 235);
            }
            $this->command('MAIL FROM:<' . $from . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', 354);

            $headers = [
                'Date: ' . date('r'),
                'From: ' . $from,
                'To: ' . $to,
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
            $this->command($data . "\r\n.", 250);
            $this->command('QUIT', 221);
        } finally {
            fclose($socket);
            $this->socket = null;
        }
    }

    /** Отправить команду и проверить код ответа. */
    private function command(string $line, $expected): void
    {
        fwrite($this->socket, $line . "\r\n");
        $this->expect($expected);
    }

    /** Прочитать (многострочный) ответ сервера и проверить код. */
    private function expect($expected): void
    {
        $expected = (array) $expected;
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException('SMTP: неожиданный ответ сервера: ' . trim($response));
        }
    }
}
