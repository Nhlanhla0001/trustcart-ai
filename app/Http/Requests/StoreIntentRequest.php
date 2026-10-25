<?php

namespace App\Http\Requests;

use App\Support\Requirements;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intent' => ['nullable', 'string', 'max:1200'],
            'category' => ['required', Rule::in(Requirements::CATEGORIES)],
            'budget' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Choose the kind of product you are shopping for.',
            'category.in' => 'That product category is not in the demo catalog.',
            'intent.max' => 'Keep the request under 1,200 characters.',
            'budget.numeric' => 'Enter the budget as a number, without a currency symbol.',
        ];
    }
}
