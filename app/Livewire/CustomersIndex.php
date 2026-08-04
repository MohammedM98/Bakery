<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public ?int $editingCustomerId = null;

    public ?string $flashMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function editCustomer(int $customerId): void
    {
        $customer = Customer::where('bakery_id', auth()->user()->bakery_id)->findOrFail($customerId);

        $this->editingCustomerId = $customer->id;
        $this->dispatch('open-modal', 'customer-edit');
    }

    #[On('customer-saved')]
    public function handleCustomerSaved(?string $name = null): void
    {
        $this->editingCustomerId = null;
        $this->flashMessage = $name ? "تم حفظ بيانات \"{$name}\" بنجاح." : 'تم الحفظ بنجاح.';
    }

    #[On('customer-deleted')]
    public function handleCustomerDeleted(): void
    {
        $this->editingCustomerId = null;
        $this->flashMessage = 'تم حذف العميل وجميع سجلاته.';
    }

    protected function findEditingCustomer(): ?Customer
    {
        if (! $this->editingCustomerId) {
            return null;
        }

        return Customer::where('bakery_id', auth()->user()->bakery_id)->find($this->editingCustomerId);
    }

    public function render()
    {
        $customers = Customer::where('bakery_id', auth()->user()->bakery_id)
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('mobile_number', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.customers-index', [
            'customers' => $customers,
            'editingCustomer' => $this->findEditingCustomer(),
        ]);
    }
}
