<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'bakery_id', 'permissions', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Matches the column default, so a freshly created model is active before it is reloaded. */
    protected $attributes = ['is_active' => true];

    const ROLE_SUPER_ADMIN = 'super_admin';

    const ROLE_BAKERY_OWNER = 'bakery_owner';

    const ROLE_BAKERY_STAFF = 'bakery_staff';

    /**
     * The permissions a bakery owner can tick for each staff member (9-ب item 7), by group.
     * The single source of truth: each key is a Gate (AppServiceProvider) and the labels go to React.
     */
    const PERMISSIONS = [
        'اللوحة' => [
            'view-dashboard' => 'عرض اللوحة الرئيسية',
        ],
        'الزبائن' => [
            'view-customers' => 'عرض الزبائن',
            'create-customers' => 'إضافة زبون',
            'edit-customers' => 'تعديل زبون',
        ],
        'المبيعات' => [
            'view-sales' => 'عرض قائمة المبيعات',
            'record-sale' => 'تسجيل بيع عادي',
            'record-flour-sale' => 'تسجيل بيع مقابل طحين',
        ],
        'المال' => [
            'record-payment' => 'تسجيل دفعة',
        ],
        'الطحين' => [
            'record-deposit' => 'تسجيل إيداع طحين',
        ],
        'الكشوف' => [
            'view-money-statement' => 'عرض كشف الحساب المالي',
            'view-flour-statement' => 'عرض كشف الطحين',
            'export-statements' => 'تحميل الكشوف (PDF / Excel)',
        ],
        'السجل' => [
            'view-sales-log' => 'عرض سجل مبيعات المخبز',
        ],
        'التسوية' => [
            'record-adjustment' => 'تسجيل قيد تسوية',
        ],
        'البنوك' => [
            'view-banks' => 'عرض البنوك',
            'manage-banks' => 'إضافة وتعديل بنك',
        ],
    ];

    /** Never granted to staff: the bakery owner only. */
    const OWNER_ONLY = ['delete-customers', 'delete-banks', 'manage-users', 'manage-settings'];

    /**
     * A new password signs out every phone: the user's API tokens are revoked. (A disabled account's token is
     * revoked on its next request by EnsureUserIsActive, which tells the app why: `account_disabled`.)
     */
    protected static function booted(): void
    {
        static::updated(fn (User $user) => $user->wasChanged('password') && $user->tokens()->delete());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function bakery(): BelongsTo
    {
        return $this->belongsTo(Bakery::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isBakeryOwner(): bool
    {
        return $this->role === self::ROLE_BAKERY_OWNER;
    }

    public function isBakeryStaff(): bool
    {
        return $this->role === self::ROLE_BAKERY_STAFF;
    }

    /** @return list<string> every grantable permission key */
    public static function permissionKeys(): array
    {
        return array_keys(array_merge(...array_values(self::PERMISSIONS)));
    }

    /** The owner holds every permission, owner-only ones included; staff only the ticked ones; nobody else any. */
    public function hasPermission(string $permission): bool
    {
        if ($this->isBakeryOwner()) {
            return true;
        }

        return $this->isBakeryStaff()
            && $this->is_active
            && ! in_array($permission, self::OWNER_ONLY, true)
            && in_array($permission, $this->permissions ?? [], true);
    }

    /** @return list<string> the permission keys this user holds, for React (sidebar and buttons) */
    public function grantedPermissions(): array
    {
        return array_values(array_filter(
            [...self::permissionKeys(), ...self::OWNER_ONLY],
            fn (string $permission) => $this->hasPermission($permission),
        ));
    }
}
