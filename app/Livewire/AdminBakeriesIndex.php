<?php

namespace App\Livewire;

use App\Models\Bakery;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AdminBakeriesIndex extends Component
{
    use WithPagination;

    public array $renewMonths = [];

    public ?int $editingBakeryId = null;

    public ?int $confirmingDeleteBakeryId = null;

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

        $this->dispatch('toast', message: "تم تجديد اشتراك \"{$bakery->name}\" بنجاح.");
    }

    public function toggleStatus(int $bakeryId): void
    {
        $bakery = Bakery::findOrFail($bakeryId);

        $bakery->update([
            'subscription_status' => $bakery->subscription_status === Bakery::STATUS_ACTIVE
                ? Bakery::STATUS_INACTIVE
                : Bakery::STATUS_ACTIVE,
        ]);

        $this->dispatch('toast', message: 'تم تحديث حالة الاشتراك.');
    }

    public function confirmDelete(int $bakeryId): void
    {
        $this->confirmingDeleteBakeryId = $bakeryId;
        $this->dispatch('open-modal', 'confirm-delete-bakery');
    }

    public function delete(int $bakeryId): void
    {
        $bakery = Bakery::findOrFail($bakeryId);
        $name = $bakery->name;

        $bakery->owners()->delete();
        $bakery->delete();

        $this->confirmingDeleteBakeryId = null;
        $this->dispatch('toast', message: "تم حذف مخبز \"{$name}\" وجميع بياناته.");
        $this->dispatch('close-modal', 'confirm-delete-bakery');
    }

    protected function findConfirmingDeleteBakery(): ?Bakery
    {
        if (! $this->confirmingDeleteBakeryId) {
            return null;
        }

        return Bakery::find($this->confirmingDeleteBakeryId);
    }

    public function editBakery(int $bakeryId): void
    {
        $this->editingBakeryId = Bakery::findOrFail($bakeryId)->id;
        $this->dispatch('open-modal', 'bakery-edit');
    }

    #[On('bakery-saved')]
    #[On('bakery-updated')]
    public function refreshAfterModal(): void
    {
        // No-op: handling either event triggers a fresh render(), which is
        // enough to reflect changes made inside the create/edit modals.
    }

    protected function findEditingBakery(): ?Bakery
    {
        if (! $this->editingBakeryId) {
            return null;
        }

        return Bakery::with('owners')->find($this->editingBakeryId);
    }

    public function render()
    {
        $bakeries = Bakery::with('owners')->latest()->paginate(15);

        return view('livewire.admin-bakeries-index', [
            'bakeries' => $bakeries,
            'editingBakery' => $this->findEditingBakery(),
            'confirmingDeleteBakery' => $this->findConfirmingDeleteBakery(),
        ]);
    }
}
