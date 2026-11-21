<?php

declare(strict_types=1);

/*
 * Writes .env.local.php for production from the environment of the deploy
 * session. Symfony loads this file instead of parsing .env files, and
 * var_export() keeps passwords containing quotes, "$" or "#" intact, which
 * a hand-written .env.local would not.
 *
 * Usage (from the repository root on the server): php scripts/write-prod-env.php
 */

function env(string $name): string
{
    $value = getenv($name);

    // Values pasted from a Windows editor keep a trailing carriage return.
    return false === $value ? '' : trim($value);
}

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

const DATABASE_PARTS = ['DATABASE_HOST', 'DATABASE_NAME', 'DATABASE_USER', 'DATABASE_PASSWORD'];
const POSTGRESQL_SCHEMES = ['postgresql', 'postgres', 'pgsql', 'pdo-pgsql'];

/**
 * Prefers the separate DATABASE_* settings (LARPilot's convention) when all
 * of them are set, so a leftover DATABASE_URL cannot silently win.
 *
 * @return array{string, string} the URL and a description of its source
 */
function databaseUrl(): array
{
    $missing = array_values(array_filter(DATABASE_PARTS, static fn (string $name): bool => '' === env($name)));

    if ([] === $missing) {
        $url = sprintf(
            'postgresql://%s:%s@%s:%s/%s?serverVersion=%s&charset=utf8',
            rawurlencode(env('DATABASE_USER')),
            rawurlencode(env('DATABASE_PASSWORD')),
            env('DATABASE_HOST'),
            env('DATABASE_PORT') ?: '5432',
            rawurlencode(env('DATABASE_NAME')),
            rawurlencode(env('DATABASE_SERVER_VERSION') ?: '17'),
        );

        return [$url, 'the DATABASE_HOST/NAME/USER/PASSWORD settings'];
    }

    if ('' !== env('DATABASE_URL')) {
        return [env('DATABASE_URL'), 'the DATABASE_URL secret'];
    }

    fail('Set DATABASE_URL, or all of '.implode(', ', $missing).', as secrets on the GitHub "prod" environment.');
}

/**
 * Connects once before anything is built, so a wrong driver, host or
 * password fails with a clear message instead of halfway through the deploy.
 */
function checkDatabase(string $url, string $source): void
{
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));

    if (!in_array($scheme, POSTGRESQL_SCHEMES, true)) {
        fail(sprintf(
            'The database URL from %s uses the "%s" driver. PipBoy requires PostgreSQL: use a postgresql:// URL, or delete DATABASE_URL and set %s on the GitHub "prod" environment.',
            $source,
            '' === $scheme ? 'unknown' : $scheme,
            implode(', ', DATABASE_PARTS),
        ));
    }

    if (!extension_loaded('pdo_pgsql')) {
        fail('The pdo_pgsql PHP extension is not enabled for '.PHP_BINARY.'. Enable it on the server.');
    }

    parse_str($parts['query'] ?? '', $query);
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=10',
        $parts['host'] ?? 'localhost',
        $parts['port'] ?? 5432,
        rawurldecode(ltrim($parts['path'] ?? '', '/')),
    );

    try {
        $connection = new PDO($dsn, rawurldecode($parts['user'] ?? ''), rawurldecode($parts['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $serverVersion = (string) $connection->query('SHOW server_version')->fetchColumn();
    } catch (PDOException $exception) {
        fail(sprintf('Could not connect to PostgreSQL using %s: %s', $source, $exception->getMessage()));
    }

    $actualMajor = (int) $serverVersion;
    echo sprintf('Connected to PostgreSQL %s using %s.', $serverVersion, $source).PHP_EOL;

    if ($actualMajor < 17) {
        echo sprintf('WARNING: PipBoy targets PostgreSQL 17; this server runs %d.', $actualMajor).PHP_EOL;
    }

    $configuredMajor = (int) ($query['serverVersion'] ?? 0);
    if (0 !== $configuredMajor && $configuredMajor !== $actualMajor) {
        echo sprintf(
            'WARNING: serverVersion=%d in the database URL does not match the server (PostgreSQL %d). Set DATABASE_SERVER_VERSION (or serverVersion in DATABASE_URL) to %d.',
            $configuredMajor,
            $actualMajor,
            $actualMajor,
        ).PHP_EOL;
    }
}

$appSecret = env('APP_SECRET');
if ('' === $appSecret) {
    fail('APP_SECRET is empty. Set it as a secret on the GitHub "prod" environment. Generate one with: php -r \'echo bin2hex(random_bytes(32)), PHP_EOL;\'');
}

[$databaseUrl, $databaseSource] = databaseUrl();
checkDatabase($databaseUrl, $databaseSource);

$values = [
    'APP_ENV' => 'prod',
    'APP_DEBUG' => '0',
    'APP_SECRET' => $appSecret,
    'DATABASE_URL' => $databaseUrl,
    'DISCORD_CLIENT_ID' => env('DISCORD_CLIENT_ID') ?: env('OAUTH_DISCORD_CLIENT_ID'),
    'DISCORD_CLIENT_SECRET' => env('DISCORD_CLIENT_SECRET') ?: env('OAUTH_DISCORD_CLIENT_SECRET'),
];

if (('' === $values['DISCORD_CLIENT_ID']) !== ('' === $values['DISCORD_CLIENT_SECRET'])) {
    fail('Set both DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET on the GitHub "prod" environment, or neither to disable Discord login.');
}

$target = dirname(__DIR__).'/.env.local.php';
$temporary = $target.'.tmp';
$previousMask = umask(0o027);
$written = file_put_contents($temporary, "<?php\n\n// Generated by scripts/write-prod-env.php during deployment. Do not edit or commit.\n\nreturn ".var_export($values, true).";\n");
umask($previousMask);

if (false === $written || !rename($temporary, $target)) {
    fail('Could not write '.$target.'.');
}

echo 'Wrote .env.local.php ('.('' === $values['DISCORD_CLIENT_ID'] ? 'Discord login disabled' : 'Discord login enabled').').'.PHP_EOL;
