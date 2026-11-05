@props([
    'name' => 'file',
    'id' => null,
    'label' => 'Upload File',
    'hint' => 'PNG, JPG, WebP up to 5MB',
    'accept' => 'image/*',
    'currentUrl' => null,
])

@php
    $id = $id ?? 'file-' . \Illuminate\Support\Str::random(6);
@endphp

<div 
    x-data="{
        previewUrl: @js($currentUrl),
        fileName: '',
        fileSize: '',
        isDragging: false,
        handleDrop(e) {
            this.isDragging = false;
            if (e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                this.updatePreview(file);
                $refs.input.files = e.dataTransfer.files;
            }
        },
        handleSelect(e) {
            if (e.target.files.length > 0) {
                this.updatePreview(e.target.files[0]);
            }
        },
        updatePreview(file) {
            this.fileName = file.name;
            this.fileSize = (file.size / 1024).toFixed(1) + ' KB';
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => { this.previewUrl = e.target.result; };
                reader.readAsDataURL(file);
            }
        },
        removeFile() {
            this.previewUrl = null;
            this.fileName = '';
            this.fileSize = '';
            $refs.input.value = '';
        }
    }"
    class="space-y-2 w-full"
>
    @if($label)
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
            {{ $label }}
        </label>
    @endif

    <div
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop($event)"
        :class="isDragging ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20' : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 bg-slate-50/50 dark:bg-slate-900/50'"
        class="relative border-2 border-dashed rounded-2xl p-6 text-center transition-all duration-200 cursor-pointer flex flex-col items-center justify-center min-h-[140px]"
        @click="$refs.input.click()"
    >
        <input 
            type="file" 
            name="{{ $name }}" 
            id="{{ $id }}"
            x-ref="input"
            accept="{{ $accept }}"
            class="hidden"
            @change="handleSelect($event)"
        />

        <template x-if="previewUrl">
            <div class="relative group flex flex-col items-center" @click.stop>
                <img :src="previewUrl" class="h-24 w-24 object-cover rounded-xl border border-slate-200 dark:border-slate-700 shadow-md mb-2" alt="Preview" />
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="fileName || 'Current File'"></span>
                    <span class="text-[10px] text-slate-400" x-text="fileSize"></span>
                    <button type="button" @click.stop="removeFile()" class="p-1 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-md transition" title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </template>

        <template x-if="!previewUrl">
            <div class="space-y-1">
                <div class="mx-auto w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-300 font-medium">
                    <span class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">Click to upload</span> or drag and drop
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ $hint }}</p>
            </div>
        </template>
    </div>
</div>
