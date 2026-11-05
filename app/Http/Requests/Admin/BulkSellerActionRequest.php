<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkSellerActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = (string) $this->input('action');
        if (in_array($action, ['approve', 'reject'], true)) {
            return $this->user()->can('sellers.approve');
        }

        if (in_array($action, ['suspend', 'reactivate'], true)) {
            return $this->user()->can('sellers.suspend');
        }

        return $this->user()->can('sellers.manage');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject,suspend,reactivate'],
            'seller_ids' => ['required', 'array', 'min:1'],
            'seller_ids.*' => ['integer', 'exists:sellers,id'],
            'reason' => ['required_if:action,reject', 'required_if:action,suspend', 'nullable', 'string', 'max:1000'],
        ];
    }
}
