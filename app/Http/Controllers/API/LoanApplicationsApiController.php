<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\LoanApplication;
use App\Models\LoanEmiTransaction;
use Validator;
use Gate;
use App\Http\Resources\Admin\LoanApplicationResource;
use App\Http\Requests\StoreLoanApplicationRequest;
use App\Http\Requests\StoreEmiRequest;
use App\Http\Requests\UpdateLoanApplicationRequest;
use Symfony\Component\HttpFoundation\Response;


class LoanApplicationsApiController extends BaseController
{
    public function index()
    {
        abort(Gate::denies('loan_application_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new LoanApplicationResource();
    }

    public function store(StoreLoanApplicationRequest $request)
    {
       
        $loanApplication = LoanApplication::create($request->all());

        return (new LoanApplicationResource($loanApplication))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(LoanApplication $loanApplication)
    {


        abort(Gate::denies('loan_application_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new LoanApplicationResource($loanApplication->load(['status', 'analyst', 'cfo', 'created_by']));
    }

    public function update(UpdateLoanApplicationRequest $request, LoanApplication $loanApplication)
    {

        $loanApplication->update($request->all());

        return (new LoanApplicationResource($loanApplication))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(LoanApplication $loanApplication)
    {
        abort(Gate::denies('loan_application_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $loanApplication->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function showSend(LoanApplication $loanApplication)
    {
        abort(!auth()->user()->is_admin, Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($loanApplication->status_id == 1) {
            $role = 'Analyst';
            $users = Role::find(3)->users->pluck('name', 'id');
        } else if (in_array($loanApplication->status_id, [3,4])) {
            $role = 'CFO';
            $users = Role::find(4)->users->pluck('name', 'id');
        } else {
            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

       return (new LoanApplicationResource($users))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function send(Request $request, LoanApplication $loanApplication)
    {
        abort(!auth()->user()->is_admin, Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($loanApplication->status_id == 1) {
            $column = 'analyst_id';
            $users  = Role::find(3)->users->pluck('id');
            $status = 2;
        } else if (in_array($loanApplication->status_id, [3,4])) {
            $column = 'cfo_id';
            $users  = Role::find(4)->users->pluck('id');
            $status = 5;
        } else {
            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $request->validate([
            'user_id' => 'required|in:' . $users->implode(',')
        ]);

        $loanApplication->update([
            $column => $request->user_id,
            'status_id' => $status
        ]);

        return redirect()->route('admin.loan-applications.index')->with('message', 'Loan application has been sent for analysis');
    }

    public function showAnalyze(LoanApplication $loanApplication)
    {
        $user = auth()->user();

        abort(
            (!$user->is_analyst || $loanApplication->status_id != 2) && (!$user->is_cfo || $loanApplication->status_id != 5),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden'
        );

        return view('admin.loanApplications.analyze', compact('loanApplication'));
    }

    public function analyze(Request $request, LoanApplication $loanApplication)
    {
        $user = auth()->user();

        if ($user->is_analyst && $loanApplication->status_id == 2) {
            $status = $request->has('approve') ? 3 : 4;
        } else if ($user->is_cfo && $loanApplication->status_id == 5) {
            $status = $request->has('approve') ? 6 : 7;
        } else {
            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $request->validate([
            'comment_text' => 'required'
        ]);

        $loanApplication->comments()->create([
            'comment_text' => $request->comment_text,
            'user_id'      => $user->id
        ]);

        $loanApplication->update([
            'status_id' => $status
        ]);

        return redirect()->route('admin.loan-applications.index')->with('message', 'Analysis has been submitted');
    }

    public function emiTransaction(StoreEmiRequest $request)
    {
        $principal = request()->loan_amount;; // principal amount
        $rate = 9.25; // 9.25 as Rate of interest per annum
        $time = request()->time_yearly; // 1 years as Repayment period
        $monthly=12*$time;
        for($i=1;$i<=$monthly;$i++){
            $emi=$this->emiCalculator($principal, $rate, $time);
            $emiTrans = new LoanEmiTransaction();
            $emiTrans->emi_amount = $emi;
            $emiTrans->created_by_id = auth()->user()->id;
            $emiTrans->status_id = 1;
            $emiTrans->loan_application_id = request()->id;
            $emiTrans->save();
        }

        $monthlyemi=LoanEmiTransaction::where('loan_application_id',request()->id)->get();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function getEmiTransaction(Request $request)
    {

        $monthlyemi=LoanEmiTransaction::where('loan_application_id',request()->loan_application_id)->get();


       return new LoanApplicationResource($monthlyemi);
    }

    public function payEmi(Request $request)
    {

        $monthlyemi=LoanEmiTransaction::where('loan_application_id',request()->loan_application_id)->update(['status',2]);

       return new LoanApplicationResource($monthlyemi);
    }


    public function emiCalculator($p, $r, $t) 
    { 
        $emi; 
        // one month interest 
        $r = $r / (12 * 100); 
          
        // one month period 
        $t = $t * 12;  
          
        $emi = ($p * $r * pow(1 + $r, $t)) /  
                      (pow(1 + $r, $t) - 1); 
      
        return ($emi); 
    } 

}
