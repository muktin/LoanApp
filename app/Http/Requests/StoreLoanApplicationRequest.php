<?php

namespace App\Http\Requests;

use App\LoanApplication;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize()
    {
       // dd(Gate::denies('loan_application_create'));
        Gate::denies('loan_application_create');

        return true;
    }

    public function rules()
    {
        return [
            'loan_amount' => ['required','gt:0',],
        ];
    }
}
