<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSellerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sellers.approve');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
            'remarks' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'remarks.required_if' => 'Mandatory remarks must be provided when rejecting a KYC verification document.',
        ];
    }
}
