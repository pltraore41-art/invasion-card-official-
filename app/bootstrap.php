<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';
const BRAND_NAME = 'Invasion de Cardigan';
const BRAND_TAGLINE = 'Lancement officiel de la marque';
const LAUNCH_EVENT = 'Événement de lancement Invasion de Cardigan';
const EVENT_DATE = '14 novembre 2026';
const EVENT_START = '2026-11-14T00:00:00';

function load_environment(): void
{
    $envFile = ROOT_PATH . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2 && $value[0] === $value[-1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

function env_value(string $name, ?string $default = null): ?string
{
    $value = $_ENV[$name] ?? getenv($name);
    return $value === false || $value === '' ? $default : (string) $value;
}

load_environment();
date_default_timezone_set(env_value('APP_TIMEZONE', 'Europe/Paris'));

if (PHP_SAPI !== 'cli') {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('invasion_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
