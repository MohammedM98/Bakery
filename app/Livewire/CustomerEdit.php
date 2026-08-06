<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CustomerEdit extends Component
{
    public Customer $customer;

    public bool $isModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:50')]
    public string $mobile_number = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = '';

    public function mount(Customer $customer): void
    {
        abort_unless($customer->bakery_id === auth()->user()->bakery_id, 403);

        $this->customer = $customer;
        $this->name = $customer->name;
        $this->mobile_number = $customer->mobile_number;
        $this->notes = $customer->notes;
    }

    public function save()
    {
        $this->validate();

        $this->customer->update([
            'name' => $this->name,
            'mobile_number' => $this->mobile_number,
            'notes' => $this->notes ?: null,
        ]);

        if ($this->isModal) {
            $this->dispatch('customer-saved', name: $this->customer->name);
            $this->dispatch('close-modal', 'customer-edit');

            return;
        }

        session()->flash('status', 'تم تحديث بيانات العميل.');

        return redirect()->route('panel.customers.show', $this->customer);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', 'confirm-delete-customer');
    }

    public function delete()
    {
        abort_unless($this->customer->bakery_id === auth()->user()->bakery_id, 403);

        $this->customer->delete();

        if ($this->isModal) {
            $this->dispatch('customer-deleted');
            $this->dispatch('close-modal', 'customer-edit');

            return;
        }

        session()->flash('status', 'تم حذف العميل وجميع سجلاته.');

        return redirect()->route('panel.customers.index');
    }

    public function render()
    {
        return view('livewire.customer-edit');
    }
}
