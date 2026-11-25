<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CustomerProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $customer = $this->user('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique('customers', 'email')->where('store_id', $customer->store_id)->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_province' => ['nullable', 'string', 'max:120'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'current_password' => ['nullable', Rule::requiredIf(fn (): bool => filled($this->input('password'))), 'current_password:customer'],
            'password' => ['nullable', 'confirmed', (Password::defaults() ?? Password::min(8))],
        ];
    }
}
