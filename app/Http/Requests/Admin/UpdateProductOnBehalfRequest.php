<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductOnBehalfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.edit') || $this->user()->can('products.approve');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'short_desc' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_quick_capable' => ['nullable', 'boolean'],
            'is_cod_allowed' => ['nullable', 'boolean'],
            'is_returnable' => ['nullable', 'boolean'],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
