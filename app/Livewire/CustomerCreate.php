<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CustomerCreate extends Component
{
    public bool $isModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:50')]
    public string $mobile_number = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $flour_balance_kg = '0';

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = '';

    public function save()
    {
        $this->validate();

        $customer = Customer::create([
            'bakery_id' => auth()->user()->bakery_id,
            'name' => $this->name,
            'mobile_number' => $this->mobile_number,
            'flour_balance_kg' => $this->flour_balance_kg ?: 0,
            'notes' => $this->notes ?: null,
        ]);

        if ($this->isModal) {
            $this->reset('name', 'mobile_number', 'flour_balance_kg', 'notes');
            $this->dispatch('customer-saved', name: $customer->name);
            $this->dispatch('close-modal', 'customer-create');

            return;
        }

        session()->flash('status', 'تم إضافة العميل بنجاح.');

        return redirect()->route('panel.customers.show', $customer);
    }

    public function render()
    {
        return view('livewire.customer-create');
    }
}
