<?php

/*
 * A device for SyncConcurrencyTest: a separate PHP process that waits for the start signal, then sends
 * its pushes through the real HTTP kernel. Prints the results as JSON.
 * Usage: php concurrent-push.php <token> <customer id> <pushes> <go file>
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

[, $token, $customerId, $pushes, $go] = $argv;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
// SQLite has no row locks: take the write lock at BEGIN and wait for it, as MySQL waits on the bakery row.
config(['database.connections.sqlite.transaction_mode' => 'IMMEDIATE', 'database.connections.sqlite.busy_timeout' => 30000]);

while (! file_exists($go)) {
    usleep(1000);
}

$today = date('Y-m-d');
$results = [];
for ($i = 0; $i < (int) $pushes; $i++) {
    $op = fn (string $type, array $payload) => ['client_uuid' => (string) Str::uuid(), 'type' => $type, 'client_created_at' => date(DATE_ATOM), 'payload' => $payload];
    $body = json_encode(['operations' => [
        $op('flour_sale', ['customer_id' => (int) $customerId, 'kg_amount' => '2', 'payment_status' => 'unpaid', 'sale_date' => $today]),
        $op('deposit', ['customer_id' => (int) $customerId, 'kg_amount' => '1', 'deposit_date' => $today]),
        $op('sale', ['kg_amount' => '1', 'payment_status' => 'paid', 'sale_date' => $today]),
    ]]);
    $request = Request::create('/api/v1/sync/push', 'POST', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], $body);
    $response = $kernel->handle($request);
    $results[] = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)];
    $kernel->terminate($request, $response);
    $app['auth']->forgetGuards();
}

echo json_encode($results);
