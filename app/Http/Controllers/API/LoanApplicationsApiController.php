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
use Dompdf\Dompdf;



class LoanApplicationsApiController extends BaseController
{
    public function index()
    {
        if(Gate::denies('loan_application_access')){
            return response(Response::HTTP_FORBIDDEN);
        }

        return new LoanApplicationResource();
    }

    public function store(StoreLoanApplicationRequest $request)
    {
       
        $loanApplication = LoanApplication::create($request->all());

        return (new LoanApplicationResource($loanApplication))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(LoanApplication $loanApplication)
    {
        if(Gate::denies('loan_application_show')){
            return response(Response::HTTP_FORBIDDEN);
        }

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
        if(Gate::denies('loan_application_delete')){
            return response(null, Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $loanApplication->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function showSend(LoanApplication $loanApplication)
    {
        if(!auth()->user()->is_admin){
             return response(Response::HTTP_FORBIDDEN);
         }

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
        if(!auth()->user()->is_admin){
            return response(Response::HTTP_FORBIDDEN);
        }

        if ($loanApplication->status_id == 1) {
            $column = 'analyst_id';
            $users  = Role::find(3)->users->pluck('id');
            $status = 2;
        } else if (in_array($loanApplication->status_id, [3,4])) {
            $column = 'cfo_id';
            $users  = Role::find(4)->users->pluck('id');
            $status = 5;
        } else {
             return response(Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'user_id' => 'required|in:' . $users->implode(',')
        ]);

        $loanApplication->update([
            $column => $request->user_id,
            'status_id' => $status
        ]);


         return $this->sendResponse('success', 'Loan application has been sent for analysis.');
    }

    public function showAnalyze(LoanApplication $loanApplication)
    {
        $user = auth()->user();

        if((!$user->is_analyst || $loanApplication->status_id != 2) && (!$user->is_cfo || $loanApplication->status_id != 5)){
             return response(Response::HTTP_FORBIDDEN);
         }
   
       return new LoanApplicationResource($loanApplication->load(['status', 'analyst', 'cfo', 'created_by']));
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
        $date = date("Y-m-05");

        for($i=1;$i<=$monthly;$i++){
            $loan_payment_duedate = date("Y-m-d", strtotime("+$i month", strtotime($date)));
            $emi=$this->emiCalculator($principal, $rate, $time);
            $emiTrans = new LoanEmiTransaction();
            $emiTrans->emi_amount = $emi;
            $emiTrans->loan_payment_duedate = $loan_payment_duedate;
            $emiTrans->created_by_id = auth()->user()->id;
            $emiTrans->status_id = 1;
            $emiTrans->loan_application_id = request()->id;
            $emiTrans->save();
        }


        return response(Response::HTTP_NO_CONTENT);
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
          
        $emi = ($p * $r * pow(1 + $r, $t)) / (pow(1 + $r, $t) - 1); 
      
        return ($emi); 
    } 


    public function generatePDF($loandata)
    {
        //dd($loandata);
        $dompdf = new Dompdf(array('enable_remote' => true));
        $destinationPath = 'applicaionpdf/';
        $valfilename = "LAP".$loandata[0]['id'];
        $destinationPath2 = $destinationPath.$valfilename.'.pdf';
    
       /// $logoPath = url('/')."/public/images/llogo.jpg";
        
        //dd($imagePath2);
        
        $data = ['title' => 'Welcome to Loan Application'];

        $loandataEMI = loanpaymentemi::with('loanapplication')->where('loan_applications_id',$loandata[0]['id'])
       ->get()->toArray();

        //dd($loandataEMI);
        
        //$pdf_text = 'CRM Report</br></br><img src="'.$imagePath2.'" id="logo" >'; 
        $pdf_text = '<!DOCTYPE html><html><head><title>Loan Application</title></head>
        <body>
        <img src="'.$logoPath.'"><br>
        <h3 align="center" style="color:#000040;">Loan Application Approval Letter</h3>
        <p>Dear '.$loandata[0]['users']['name'].',<br>Your loan applicaion has been approved by administation, you have applied for loan amount <b> Rs. '.$loandata[0]['loan_amount'].'/</b> for <b>'.$loandata[0]['loan_duration'].'yrs</b>.<br>Please find the delails regarding you EMI and all the details.<br></p>

        <table width="100%" style="border: 1px solid black;">
        <tr>
        <th width="10%" align="left">No.</th>
        <th width="20%" align="left">Control Number</th>
        <th  width="20%" align="left">EMI Amount</th>
        <th  width="20%" align="left">EMI Duedate</th>
        <th  width="30%" align="left">Payment Status</th>
        </tr>';

        for($i=0; $i<count($loandataEMI); $i++)
        {
            $loan_payment_status='';
            if($loandataEMI[$i]['loan_payment_status']==0)
            {
                $loan_payment_status='Pending';
            } 
            else 
            {
                $loan_payment_status='Paid';
            } 
            $loan_control_number = $loandataEMI[$i]['loan_control_number'];
            $loan_payment_amount=$loandataEMI[$i]['loan_payment_amount'];
            $loan_payment_duedate = substr($loandataEMI[$i]['loan_payment_duedate'],0,10);
            $No=$i+1;
            $pdf_text.= '<tr>
            <td>'.$No.'</td>
            <td>'.$loan_control_number.'</td>
            <td>'.$loan_payment_amount.'</td>
            <td>'.$loan_payment_duedate.'</td>
            <td>'.$loan_payment_status.'</td>
            </tr>';
        } 

        $pdf_text.= '

        </table>
        <br><br><br><br>
            Thank & Regards<br>
            Team Loan Connect
        </body>
        </html>';

        //dd($pdf_text);    
        
        $dompdf->loadHTML($pdf_text);
        //dd($pdf_text);
        
        //$dompdf->loadHtml('myPDF');
        
        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'landscape');

        // Render the HTML as PDF 
        $dompdf->render();
        
        // save the pdf on server
        $output = $dompdf->output();
        file_put_contents($destinationPath2, $output);
        
        return $destinationPath2;
    }

}
