<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** An API refusal with a machine code and an Arabic message (docs/api.md, "رموز الأخطاء"). */
class ApiException extends Exception
{
    /** @param  array<string, list<string>>  $errors */
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function subscriptionExpired(): self
    {
        return new self('subscription_expired', 'انتهى اشتراك المخبز. تواصل مع إدارة المنصة لتجديده.', 403);
    }

    public static function forbidden(): self
    {
        return new self('forbidden', 'لا تملك صلاحية لهذه العملية.', 403);
    }

    /** The API form of the framework's refusals; null for anything else (a real server error). */
    public static function from(Throwable $e): ?self
    {
        return match (true) {
            $e instanceof self => $e,
            $e instanceof ValidationException => new self('validation_failed', collect($e->errors())->flatten()->first() ?? 'البيانات غير صحيحة.', 422, $e->errors()),
            $e instanceof AuthenticationException => new self('unauthenticated', 'يجب تسجيل الدخول.', 401),
            $e instanceof AuthorizationException => self::forbidden(),
            $e instanceof ModelNotFoundException => new self('not_found', 'غير موجود.', 404),
            $e instanceof HttpExceptionInterface => match ($e->getStatusCode()) {
                403 => self::forbidden(),
                404 => new self('not_found', 'غير موجود.', 404),
                405 => new self('method_not_allowed', 'طريقة الطلب غير مسموحة.', 405),
                429 => new self('too_many_requests', 'طلبات كثيرة. حاول بعد قليل.', 429),
                default => null,
            },
            default => null,
        };
    }

    /** @return array{code: string, message: string, errors?: array<string, list<string>>} */
    public function body(): array
    {
        return ['code' => $this->errorCode, 'message' => $this->getMessage(), ...($this->errors ? ['errors' => $this->errors] : [])];
    }

    public function render(): JsonResponse
    {
        return response()->json($this->body(), $this->status);
    }
}
