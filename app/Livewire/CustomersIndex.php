<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
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

        return view('livewire.customers-index', compact('customers'));
    }
}
