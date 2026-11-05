<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class Step3TaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seller = $this->user()?->seller;
        return $seller !== null && !$seller->isEditingLocked();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('pan')) {
            $this->merge(['pan' => strtoupper(trim((string) $this->pan))]);
        }

        if ($this->has('gstin')) {
            $this->merge(['gstin' => strtoupper(trim((string) $this->gstin))]);
        }

        $this->merge([
            'is_gst_exempt' => filter_var($this->input('is_gst_exempt', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pan' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'is_gst_exempt' => ['nullable', 'boolean'],
            'gstin' => [
                'nullable',
                'required_without:is_gst_exempt',
                'string',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pan.required' => 'Permanent Account Number (PAN) is mandatory for tax compliance.',
            'pan.regex' => 'The PAN format must be valid (5 letters, 4 digits, 1 letter, e.g. ABCDE1234F).',
            'gstin.required_without' => 'GSTIN is required unless you declare GST exemption.',
            'gstin.regex' => 'The GSTIN format is invalid. Standard format: 2 digits + 10-char PAN + 1 char + Z + 1 char (e.g. 29ABCDE1234F1Z5).',
        ];
    }
}
