<?php
function telegram_settings(): array
{
    static $local = null;
    if ($local === null) {
        $path = __DIR__ . '/telegram.local.php';
        $local = is_file($path) ? require $path : [];
    }
    return [
        'token' => getenv('TELEGRAM_BOT_TOKEN') ?: ($local['token'] ?? ''),
        'username' => ltrim(getenv('TELEGRAM_BOT_USERNAME') ?: ($local['username'] ?? ''), '@'),
    ];
}

function telegram_configured(): bool
{
    $settings = telegram_settings();
    return $settings['token'] !== '' && $settings['username'] !== '';
}

function telegram_request(string $method, array $fields = []): array
{
    $settings = telegram_settings();
    if ($settings['token'] === '') {
        throw new RuntimeException('Telegram bot is not configured.');
    }
    $handle = curl_init('https://api.telegram.org/bot' . $settings['token'] . '/' . $method);
    curl_setopt_array($handle, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if ($body === false || $status !== 200) {
        throw new RuntimeException('Telegram request failed (HTTP ' . $status . '). Check the bot configuration and connection.');
    }
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (empty($data['ok'])) {
        throw new RuntimeException('Telegram did not accept the request.');
    }
    return $data;
}