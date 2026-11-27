<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Api\Operations;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\MeResource;
use App\Http\Resources\MovementResource;
use App\Models\Bakery;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/** The offline queue (section 13): push the device's operations, pull what changed since its cursor. Protocol in docs/api.md. */
class SyncController extends Controller
{
    /** Most operations in one push. */
    const BATCH = 100;

    /** Most movements in one pull page. A sync version never holds more than 3 (one RecordMovements call). */
    const PAGE = 500;

    /** A first (full) pull brings this many days of movements; older statements are read online. */
    const HISTORY_DAYS = 90;

    /**
     * Runs the operations in order, each alone (its own transaction and checks), so one refusal never blocks
     * the rest. Every result says accepted (with the server's movement numbers and the balances after),
     * duplicate (already applied: the original result), or rejected (a code and an Arabic reason).
     */
    public function push(Request $request, Operations $operations): array
    {
        $request->validate(['operations' => ['required', 'array', 'max:'.self::BATCH], 'operations.*' => ['array']], [], ['operations' => 'العمليات']);

        return ['results' => array_map(fn (array $operation) => $this->pushOne($request->user(), $operation, $operations), $request->input('operations'))];
    }

    /** @param  array<string, mixed>  $operation */
    private function pushOne(User $user, array $operation, Operations $operations): array
    {
        $uuid = is_string($operation['client_uuid'] ?? null) ? $operation['client_uuid'] : null;

        try {
            $valid = Validator::make($operation, [
                'client_uuid' => ['required', 'uuid'],
                'type' => ['required', 'string', Rule::in(Operations::OFFLINE)],
                'payload' => ['present', 'array'],
                'client_created_at' => ['required', 'date'],
            ], ['type.in' => 'هذه العملية لا تُرسل من قائمة الانتظار، تتم بالإنترنت فقط.'], [
                'client_uuid' => 'معرّف العملية', 'type' => 'نوع العملية', 'payload' => 'بيانات العملية', 'client_created_at' => 'وقت العملية',
            ])->validate();

            [, $body, $replayed] = $operations->run(
                $user,
                $valid['type'],
                [...$valid['payload'], 'client_uuid' => $valid['client_uuid']],
                // A clock ahead of the server's counts as now (it must not unlock a future price).
                Carbon::parse($valid['client_created_at'])->min(now()),
            );

            return ['client_uuid' => $uuid, 'status' => $replayed ? 'duplicate' : 'accepted', 'data' => $body];
        } catch (Throwable $e) {
            $error = ApiException::from($e);
            if (! $error) {
                report($e);
                $error = new ApiException('server_error', 'تعذّر تنفيذ العملية في الخادم. ستُعاد المحاولة لاحقًا.', 500);
            }

            return [
                'client_uuid' => $uuid,
                'status' => 'rejected',
                ...$error->body(),
                // Worth sending again as it is later; anything else needs the user (shown on the sync screen).
                'retryable' => in_array($error->errorCode, ['server_error', 'too_many_requests'], true),
            ];
        }
    }

    /**
     * What changed since the cursor, for the data the app keeps offline: customers with balances, their movements
     * (only the ledgers the user may see), banks, and tombstones of deleted customers and banks. Always with the
     * current user, permissions, subscription and prices.
     *
     * The cursor is "{version}.{permissions hash}.{history floor}". The version is the bakery's sync_version,
     * handed out under the bakery row lock in the writing transaction, so nothing at or below it can still commit.
     * A missing cursor, or one taken with other permissions, starts a full pull (`full: true`): drop the cache first.
     */
    public function pull(Request $request): array
    {
        $user = $request->user();
        $bakery = $user->bakery;
        $permissions = $user->grantedPermissions();
        $hash = substr(sha1(implode(',', $permissions)), 0, 8);
        [$since, $floor, $full] = $this->cursor($request->query('since'), $hash);
        $base = MeResource::make($user)->resolve();
        $nothing = ['customers' => [], 'movements' => [], 'banks' => [], 'deleted' => ['customers' => [], 'banks' => []]];

        // An expired bakery syncs no data (as the website shows none); the device learns the state and keeps its cursor.
        if (! $bakery->isSubscriptionActive()) {
            return [...$base, 'cursor' => (string) $request->query('since', ''), 'has_more' => false, 'full' => false, ...$nothing];
        }

        $upto = (int) Bakery::whereKey($bakery->id)->value('sync_version');
        $can = fn (string $permission) => in_array($permission, $permissions, true);
        $ledgers = array_keys(array_filter([
            Movement::LEDGER_MONEY => $can('view-money-statement'),
            Movement::LEDGER_FLOUR => $can('view-flour-statement'),
        ]));
        $changed = fn ($query) => $query->where('sync_version', '>', $since)->where('sync_version', '<=', $upto);

        $movements = $can('view-customers') && $ledgers
            ? $changed($bakery->movements())->whereNotNull('customer_id')->whereIn('ledger', $ledgers)
                ->whereDate('movement_date', '>=', $floor)
                ->orderBy('sync_version')->orderBy('number')->limit(self::PAGE + 1)->get()
            : collect();
        $hasMore = $movements->count() > self::PAGE;
        if ($hasMore) {
            // Stop below the first version that did not fit, so every version up to the cursor is complete.
            $upto = $movements[self::PAGE]->sync_version - 1;
            $movements = $movements->where('sync_version', '<=', $upto)->values();
        }

        $deleted = DB::table('sync_tombstones')->where('bakery_id', $bakery->id)
            ->where('sync_version', '>', $since)->where('sync_version', '<=', $upto)
            ->get(['entity', 'entity_id'])->groupBy('entity');

        return [
            ...$base,
            'cursor' => "{$upto}.{$hash}.".str_replace('-', '', $floor),
            'has_more' => $hasMore,
            'full' => $full,
            'customers' => $can('view-customers') ? CustomerResource::collection($changed($bakery->customers())
                ->withSum(['movements as money_balance' => fn ($query) => $query->where('ledger', Movement::LEDGER_MONEY)], 'amount')
                ->orderBy('id')->get())->resolve() : [],
            'movements' => MovementResource::collection($movements)->resolve(),
            // The payment form needs the bank names even without "عرض البنوك", as on the website.
            'banks' => $can('view-banks') || $can('record-payment') ? $changed($bakery->banks())->orderBy('name')->get(['id', 'name']) : [],
            'deleted' => [
                'customers' => $deleted->get('customers', collect())->pluck('entity_id')->all(),
                'banks' => $deleted->get('banks', collect())->pluck('entity_id')->all(),
            ],
        ];
    }

    /** @return array{0: int, 1: string, 2: bool} version since, history floor (Y-m-d), whether this is a full pull */
    private function cursor(mixed $cursor, string $hash): array
    {
        $fresh = [0, today()->subDays(self::HISTORY_DAYS)->toDateString(), true];

        if ($cursor === null || $cursor === '') {
            return $fresh;
        }

        if (! is_string($cursor) || ! preg_match('/^(\d+)\.([0-9a-f]{8})\.(\d{4})(\d{2})(\d{2})$/', $cursor, $m) || ! checkdate((int) $m[4], (int) $m[5], (int) $m[3])) {
            throw new ApiException('invalid_cursor', 'مؤشر المزامنة غير صالح. أعد المزامنة من البداية.', 422);
        }

        return $m[2] === $hash ? [(int) $m[1], "{$m[3]}-{$m[4]}-{$m[5]}", false] : $fresh;
    }
}
