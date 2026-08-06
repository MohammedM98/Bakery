@props([
    'name',
    'title',
    'message',
    'confirmLabel' => 'تأكيد',
    'confirmAction',
    'tone' => 'danger',
])

@php
$toneClasses = $tone === 'success'
    ? ['icon' => 'bg-green-100 text-green-600', 'button' => 'bg-green-600 hover:bg-green-700']
    : ['icon' => 'bg-red-100 text-red-600', 'button' => 'bg-red-600 hover:bg-red-700'];
@endphp

<x-modal :name="$name" maxWidth="sm">
    <div class="p-6">
        <div class="flex items-start gap-4">
            <div class="shrink-0 h-11 w-11 rounded-full flex items-center justify-center text-xl {{ $toneClasses['icon'] }}">
                {{ $tone === 'success' ? '✓' : '⚠️' }}
            </div>
            <div>
                <h3 class="font-semibold text-gray-800 mb-1">{{ $title }}</h3>
                <p class="text-sm text-gray-600">{{ $message }}</p>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="$dispatch('close-modal', '{{ $name }}')"
                    class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                إلغاء
            </button>
            <button type="button" wire:click="{{ $confirmAction }}" wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-xl text-white text-sm font-medium disabled:opacity-60 {{ $toneClasses['button'] }}">
                {{ $confirmLabel }}
            </button>
        </div>
    </div>
</x-modal>
