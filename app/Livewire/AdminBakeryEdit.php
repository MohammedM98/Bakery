<?php

namespace App\Livewire;

use App\Models\Bakery;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AdminBakeryEdit extends Component
{
    public Bakery $bakery;

    public bool $isModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50')]
    public ?string $phone = '';

    #[Validate('nullable|string|max:255')]
    public ?string $address = '';

    #[Validate('required|integer|min:1|max:24')]
    public string $subscription_months = '1';

    public ?string $message = null;

    public function mount(Bakery $bakery): void
    {
        $bakery->load('owners');

        $this->bakery = $bakery;
        $this->name = $bakery->name;
        $this->phone = $bakery->phone;
        $this->address = $bakery->address;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        $this->bakery->update([
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
        ]);

        $this->message = 'تم تحديث بيانات المخبز.';

        $this->dispatch('bakery-updated');
    }

    public function renew(): void
    {
        $this->validate(['subscription_months' => 'required|integer|min:1|max:24']);

        $base = $this->bakery->subscription_expires_at && $this->bakery->subscription_expires_at->isFuture()
            ? $this->bakery->subscription_expires_at
            : now();

        $this->bakery->update([
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => $base->copy()->addMonths((int) $this->subscription_months)->toDateString(),
        ]);

        $this->bakery->refresh();

        $this->message = 'تم تجديد الاشتراك بنجاح.';

        $this->dispatch('bakery-updated');
    }

    public function toggleStatus(): void
    {
        $this->bakery->update([
            'subscription_status' => $this->bakery->subscription_status === Bakery::STATUS_ACTIVE
                ? Bakery::STATUS_INACTIVE
                : Bakery::STATUS_ACTIVE,
        ]);

        $this->bakery->refresh();

        $this->message = 'تم تحديث حالة الاشتراك.';

        $this->dispatch('bakery-updated');
    }

    public function delete()
    {
        $this->bakery->owners()->delete();
        $this->bakery->delete();

        if ($this->isModal) {
            $this->dispatch('bakery-updated');
            $this->dispatch('close-modal', 'bakery-edit');

            return;
        }

        session()->flash('status', 'تم حذف المخبز وجميع بياناته.');

        return redirect()->route('admin.bakeries.index');
    }

    public function render()
    {
        return view('livewire.admin-bakery-edit');
    }
}
