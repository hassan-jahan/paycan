<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Rules\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ValidationRules::productRules();
        $rules['slug'] = 'required|string|max:255|unique:products,slug,' . $this->route('product');
        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Product type must be one of: ' . implode(', ', Product::getTypes()),
        ];
    }
}