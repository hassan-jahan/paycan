<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use App\Rules\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ValidationRules::subscriptionRules();
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Subscription status must be one of: ' . implode(', ', Subscription::getStatuses()),
        ];
    }
}