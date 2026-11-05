<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class Step5AddressRequest extends FormRequest
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
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Street / store address is required.',
            'city.required' => 'City name is required.',
            'state.required' => 'State name is required.',
            'pincode.required' => '6-digit Postal Pincode is required.',
            'pincode.regex' => 'The pincode must be a valid 6-digit Indian postal code.',
            'lat.required' => 'Please pin your store location on the map to set coordinates.',
            'lng.required' => 'Please pin your store location on the map to set coordinates.',
        ];
    }
}
