<?php

namespace App\Http\Requests;

use App\LoanApplication;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreEmiRequest extends FormRequest
{
    public function authorize()
    {
        Gate::denies('loan_application_emi_create');
        return true;
    }

    public function rules()
    {
        return [
            'loan_amount' => ['required','gt:0',],
        ];
    }
}
