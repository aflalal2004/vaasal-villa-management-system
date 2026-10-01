<?php
/**
 * Vaasal Villa HMS — Lock Bridge
 * ------------------------------
 * Runs on a PC inside the property network next to the card encoder / lock server.
 * Loop: heartbeat → claim encoder jobs → call vendor adapter → report result → upload door events.
 *
 * Usage:  php bridge.php            (runs forever; install as a Windows service with NSSM)
 *         php bridge.php --once     (single cycle, for testing)
 */

declare(strict_types=1);

spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'LockBridge\\')) {
        $file = __DIR__.'/src/'.str_replace('\\', '/', substr($class, strlen('LockBridge\\'))).'.php';
        if (is_file($file)) require $file;
    }
});

use LockBridge\Adapters\SimulatorAdapter;
use LockBridge\Adapters\VendorSdkAdapter;
use LockBridge\ApiClient;

// ---- configuration (.env next to this file) ----
$env = [];
$envFile = __DIR__.'/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), '"');
    }
}
$apiUrl = $env['HMS_API_URL'] ?? 'http://localhost/vaasal_villa_hospitality_management_system28/public/api/v1/lock-bridge';
$token = $env['LOCK_BRIDGE_TOKEN'] ?? '';
$vendor = $env['LOCK_VENDOR'] ?? 'simulator';
$interval = max(2, (int) ($env['POLL_SECONDS'] ?? 3));
if ($token === '') {
    fwrite(STDERR, "LOCK_BRIDGE_TOKEN missing in lock-bridge/.env\n");
    exit(1);
}

$adapter = $vendor === 'simulator' ? new SimulatorAdapter() : new VendorSdkAdapter($env);
$api = new ApiClient($apiUrl, $token, filter_var($env['VERIFY_TLS'] ?? 'true', FILTER_VALIDATE_BOOL));
$once = in_array('--once', $argv, true);

function logline(string $msg): void
{
    echo '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
}

logline("Lock Bridge started · vendor={$adapter->name()} · api={$apiUrl}");

do {
    try {
        $api->post('heartbeat', ['version' => '1.0.0', 'vendor' => $adapter->name()]);

        $jobs = $api->get('jobs?limit=5')['jobs'] ?? [];
        foreach ($jobs as $job) {
            $method = match ($job['action']) {
                'encode_new' => 'encodeNew', 'encode_duplicate' => 'encodeDuplicate', 'revoke' => 'revoke', 'extend' => 'extend', default => 'readCard',
            };
            logline("Job {$job['uuid']} {$job['action']} card ".($job['payload']['card_uid'] ?? '?'));
            try {
                $result = $adapter->{$method}($job['payload']);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'message' => 'Adapter error: '.$e->getMessage()];
            }
            $api->post("jobs/{$job['uuid']}/result", $result);
            logline('  → '.($result['success'] ? 'OK' : 'FAILED').': '.($result['message'] ?? ''));
        }

        $events = $adapter->fetchEvents();
        if ($events) {
            $api->post('events', ['events' => $events]);
            logline('Uploaded '.count($events).' door event(s)');
        }
    } catch (\Throwable $e) {
        logline('ERROR '.$e->getMessage().' — retrying');
    }
    if (! $once) sleep($interval);
} while (! $once);
