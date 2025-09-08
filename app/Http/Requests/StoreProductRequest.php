<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Rules\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ValidationRules::productRules();
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Product type must be one of: ' . implode(', ', Product::getTypes()),
        ];
    }
}