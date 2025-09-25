<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class SmtpMailer implements MailerInterface
{
    private string $host;
    private int $port;
    private string $from;
    private ?string $username;
    private ?string $password;
    private bool $useTls;

    public function __construct(string $host, int $port, string $from, ?string $username = null, ?string $password = null, bool $useTls = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->from = $from;
        $this->username = $username;
        $this->password = $password;
        $this->useTls = $useTls;
    }

    public function send(string $to, string $subject, string $body): void
    {
        $socket = @stream_socket_client(sprintf('tcp://%s:%d', $this->host, $this->port), $errno, $errstr, 10, STREAM_CLIENT_CONNECT);

        if ($socket === false) {
            throw new RuntimeException(sprintf('Unable to connect to SMTP server: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, 10);

        $this->expect($socket, 220);
        $this->command($socket, sprintf('HELO %s', gethostname() ?: 'localhost'), 250);

        if ($this->useTls) {
            $this->command($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new RuntimeException('Failed to enable TLS for SMTP connection.');
            }

            $this->command($socket, sprintf('HELO %s', gethostname() ?: 'localhost'), 250);
        }

        if ($this->username !== null && $this->password !== null) {
            $this->command($socket, 'AUTH LOGIN', 334);
            $this->command($socket, base64_encode($this->username), 334);
            $this->command($socket, base64_encode($this->password), 235);
        }

        $this->command($socket, sprintf('MAIL FROM:<%s>', $this->from), 250);
        $this->command($socket, sprintf('RCPT TO:<%s>', $to), 250);
        $this->command($socket, 'DATA', 354);

        $headers = [
            'From' => $this->from,
            'To' => $to,
            'Subject' => $subject,
            'Date' => gmdate('D, d M Y H:i:s') . ' +0000',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];

        $data = '';
        foreach ($headers as $name => $value) {
            $data .= sprintf("%s: %s\r\n", $name, $value);
        }

        $data .= "\r\n" . $body . "\r\n";
        $data .= ".\r\n";

        fwrite($socket, $data);
        $this->expect($socket, 250);
        $this->command($socket, 'QUIT', 221);
        fclose($socket);
    }

    private function command($socket, string $command, int $expectedCode): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCode);
    }

    private function expect($socket, int $expectedCode): void
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Empty response from SMTP server.');
        }

        $code = (int) substr($response, 0, 3);

        if ($code !== $expectedCode) {
            throw new RuntimeException(sprintf('Unexpected SMTP response: %s', trim($response)));
        }
    }
}
