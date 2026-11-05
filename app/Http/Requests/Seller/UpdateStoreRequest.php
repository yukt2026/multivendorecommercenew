<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public const RESERVED_SLUGS = [
        'admin', 'seller', 'api', 'login', 'register', 'logout',
        'dashboard', 'store', 'stores', 'cart', 'checkout', 'orders',
        'support', 'terms', 'privacy', 'help', 'account', 'onestall',
        'root', 'superadmin', 'webhook', 'cargo', 'tracking', 'search',
        'categories', 'products', 'brands', 'uikit', 'horizon', 'telescope',
    ];

    public function authorize(): bool
    {
        $seller = $this->user()?->seller;
        return $seller !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge([
                'slug' => strtolower(trim((string) $this->slug)),
            ]);
        }

        $this->merge([
            'accepts_quick_commerce' => filter_var($this->input('accepts_quick_commerce', false), FILTER_VALIDATE_BOOLEAN),
            'accepts_pickup' => filter_var($this->input('accepts_pickup', false), FILTER_VALIDATE_BOOLEAN),
            'is_active' => filter_var($this->input('is_active', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $seller = $this->user()->seller;
        $activeStoreId = session('seller_active_store_id') ?: $seller->stores()->first()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique('stores', 'slug')->ignore($activeStoreId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'delivery_radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'accepts_quick_commerce' => ['nullable', 'boolean'],
            'accepts_pickup' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            // Location
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            // Timings & Delivery Zones
            'timings' => ['nullable', 'array'],
            'timings.*.day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'timings.*.opens_at' => ['nullable', 'string'],
            'timings.*.closes_at' => ['nullable', 'string'],
            'timings.*.is_closed' => ['nullable', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Store display name is required.',
            'slug.required' => 'Unique store web slug is required.',
            'slug.regex' => 'The slug must consist only of lowercase alphanumeric characters and hyphens (e.g. metro-supermart).',
            'slug.not_in' => 'This slug is reserved by system routing and cannot be used.',
            'slug.unique' => 'This storefront URL slug is already taken by another merchant.',
            'pincode.regex' => 'Please provide a valid 6-digit Indian postal code.',
            'lat.required' => 'Store map pin coordinates are required for quick delivery dispatch.',
        ];
    }
}
