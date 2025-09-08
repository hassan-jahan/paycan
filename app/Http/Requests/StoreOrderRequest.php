<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Rules\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ValidationRules::orderRules();
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Order status must be one of: ' . implode(', ', Order::getStatuses()),
            'gateway.in' => 'Gateway must be one of: ' . implode(', ', Order::getGateways()),
        ];
    }
}