@props([
    'action' => '#',
    'title' => 'Confirm Deletion',
    'message' => 'Are you sure you want to permanently delete this item? This action cannot be undone.',
    'itemName' => 'item',
    'buttonText' => 'Delete Item',
    'triggerText' => 'Delete',
])

@php
    $modalId = 'confirm-delete-' . \Illuminate\Support\Str::random(6);
@endphp

<div x-data="{ open: false }" class="inline-block">
    <div @click="open = true">
        @if($slot->isNotEmpty())
            {{ $slot }}
        @else
            <x-button variant="danger" size="sm">
                {{ $triggerText }}
            </x-button>
        @endif
    </div>

    <!-- Modal Backdrop & Dialog -->
    <div 
        x-show="open" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    >
        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="open = false" 
            class="fixed inset-0 bg-slate-950/75 backdrop-blur-xs"
        ></div>

        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl p-6 z-10"
        >
            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800/40 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">{{ $message }}</p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end space-x-3">
                <x-button variant="secondary" size="sm" @click="open = false">
                    Cancel
                </x-button>

                <form action="{{ $action }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" size="sm" type="submit">
                        {{ $buttonText }}
                    </x-button>
                </form>
            </div>
        </div>
    </div>
</div>
