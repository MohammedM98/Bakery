<?php

namespace App\Livewire;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AdminBakeryCreate extends Component
{
    public bool $isModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50')]
    public ?string $phone = '';

    #[Validate('nullable|string|max:255')]
    public ?string $address = '';

    #[Validate('required|integer|min:1|max:24')]
    public string $subscription_months = '1';

    #[Validate('required|string|max:255')]
    public string $owner_name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $owner_email = '';

    #[Validate('required|string|min:8')]
    public string $owner_password = '';

    public function save()
    {
        $this->validate();

        DB::transaction(function () {
            $bakery = Bakery::create([
                'name' => $this->name,
                'phone' => $this->phone ?: null,
                'address' => $this->address ?: null,
                'subscription_status' => Bakery::STATUS_ACTIVE,
                'subscription_expires_at' => now()->addMonths((int) $this->subscription_months)->toDateString(),
                'regular_price_per_kg' => 0,
                'flour_exchange_fee_per_kg' => 0,
                'created_by' => auth()->id(),
            ]);

            User::create([
                'bakery_id' => $bakery->id,
                'name' => $this->owner_name,
                'email' => $this->owner_email,
                'password' => Hash::make($this->owner_password),
                'role' => User::ROLE_BAKERY_OWNER,
            ]);
        });

        if ($this->isModal) {
            $this->reset('name', 'phone', 'address', 'subscription_months', 'owner_name', 'owner_email', 'owner_password');
            $this->dispatch('bakery-saved');
            $this->dispatch('close-modal', 'bakery-create');

            return;
        }

        session()->flash('status', 'تم إنشاء المخبز وحساب المالك بنجاح.');

        return redirect()->route('admin.bakeries.index');
    }

    public function render()
    {
        return view('livewire.admin-bakery-create');
    }
}
