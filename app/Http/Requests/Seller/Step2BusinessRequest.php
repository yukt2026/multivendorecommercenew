<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\SellerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step2BusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seller = $this->user()?->seller;
        return $seller !== null && !$seller->isEditingLocked();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'seller_type' => ['required', Rule::enum(SellerType::class)],
            'category_focus' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Brand or display business name is required.',
            'legal_name.required' => 'Registered legal entity name is required for KYC contracts.',
            'seller_type.required' => 'Please select your merchant store operational model.',
            'category_focus.required' => 'Please specify your primary retail product category focus.',
        ];
    }
}
