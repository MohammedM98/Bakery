<?php

namespace App\Livewire;

use App\Models\Bakery;
use Livewire\Component;
use Livewire\WithPagination;

class AdminBakeriesIndex extends Component
{
    use WithPagination;

    public array $renewMonths = [];

    public ?string $message = null;

    public function renew(int $bakeryId): void
    {
        $months = max(1, min(24, (int) ($this->renewMonths[$bakeryId] ?? 1)));

        $bakery = Bakery::findOrFail($bakeryId);

        $base = $bakery->subscription_expires_at && $bakery->subscription_expires_at->isFuture()
            ? $bakery->subscription_expires_at
            : now();

        $bakery->update([
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => $base->copy()->addMonths($months)->toDateString(),
        ]);

        $this->message = "تم تجديد اشتراك \"{$bakery->name}\" بنجاح.";
    }

    public function toggleStatus(int $bakeryId): void
    {
        $bakery = Bakery::findOrFail($bakeryId);

        $bakery->update([
            'subscription_status' => $bakery->subscription_status === Bakery::STATUS_ACTIVE
                ? Bakery::STATUS_INACTIVE
                : Bakery::STATUS_ACTIVE,
        ]);

        $this->message = 'تم تحديث حالة الاشتراك.';
    }

    public function delete(int $bakeryId): void
    {
        $bakery = Bakery::findOrFail($bakeryId);
        $name = $bakery->name;

        $bakery->owners()->delete();
        $bakery->delete();

        $this->message = "تم حذف مخبز \"{$name}\" وجميع بياناته.";
    }

    public function render()
    {
        $bakeries = Bakery::with('owners')->latest()->paginate(15);

        return view('livewire.admin-bakeries-index', compact('bakeries'));
    }
}
