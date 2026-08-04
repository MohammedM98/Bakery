<?php

namespace App\Livewire;

use Livewire\Attributes\Validate;
use Livewire\Component;

class BakerySettingsForm extends Component
{
    #[Validate('required|numeric|min:0')]
    public string $regular_price_per_kg = '0';

    #[Validate('required|numeric|min:0')]
    public string $flour_exchange_fee_per_kg = '0';

    public ?string $message = null;

    public function mount(): void
    {
        $bakery = auth()->user()->bakery;

        $this->regular_price_per_kg = (string) $bakery->regular_price_per_kg;
        $this->flour_exchange_fee_per_kg = (string) $bakery->flour_exchange_fee_per_kg;
    }

    public function save(): void
    {
        $this->validate();

        auth()->user()->bakery->update([
            'regular_price_per_kg' => $this->regular_price_per_kg,
            'flour_exchange_fee_per_kg' => $this->flour_exchange_fee_per_kg,
        ]);

        $this->message = 'تم تحديث أسعار الخبز.';
    }

    public function render()
    {
        return view('livewire.bakery-settings-form');
    }
}
