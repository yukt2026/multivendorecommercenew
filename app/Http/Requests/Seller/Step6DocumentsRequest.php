<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class Step6DocumentsRequest extends FormRequest
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
            'pan_card' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'gst_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'address_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'pan_card.max' => 'PAN Card document must not exceed 5MB.',
            'pan_card.mimes' => 'PAN Card must be a PDF, JPG, or PNG file.',
            'gst_certificate.max' => 'GST Certificate must not exceed 5MB.',
            'gst_certificate.mimes' => 'GST Certificate must be a PDF, JPG, or PNG file.',
            'bank_proof.max' => 'Cancelled Cheque / Bank Proof must not exceed 5MB.',
            'bank_proof.mimes' => 'Cancelled Cheque must be a PDF, JPG, or PNG file.',
            'address_proof.max' => 'Address Proof must not exceed 5MB.',
            'address_proof.mimes' => 'Address Proof must be a PDF, JPG, or PNG file.',
        ];
    }
}
