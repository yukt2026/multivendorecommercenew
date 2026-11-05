<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class Step4BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seller = $this->user()?->seller;
        return $seller !== null && !$seller->isEditingLocked();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ifsc')) {
            $this->merge(['ifsc' => strtoupper(trim((string) $this->ifsc))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_holder' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'min:8', 'max:30', 'confirmed'],
            'account_no_confirmation' => ['required', 'string'],
            'ifsc' => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'bank_name' => ['required', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_holder.required' => 'Bank account holder name is required.',
            'account_no.required' => 'Bank account number is required.',
            'account_no.confirmed' => 'The bank account number confirmation does not match.',
            'ifsc.required' => 'Bank IFSC code is required.',
            'ifsc.regex' => 'The IFSC code format must be 11 characters (e.g. HDFC0001234).',
            'bank_name.required' => 'Bank name is required.',
        ];
    }
}
