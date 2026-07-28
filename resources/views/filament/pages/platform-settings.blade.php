<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-check">
            {{ __('dashboard.actions.save_settings') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
