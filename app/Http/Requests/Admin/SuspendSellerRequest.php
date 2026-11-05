<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SuspendSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sellers.suspend');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A mandatory reason must be provided when suspending a merchant account.',
            'reason.min' => 'The suspension reason must be at least 5 characters.',
        ];
    }
}
