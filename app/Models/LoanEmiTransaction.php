<?php

namespace App\Models;
use App\Observers\LoanApplicationObserver;
use App\Traits\Auditable;
use App\Traits\MultiTenantModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \DateTimeInterface;

class LoanEmiTransaction extends Model
{

    public $table = 'loan_emi_transactions';

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'emi_amount',
        'created_by_id',
        'status',
        'created_at',
        'updated_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function loan()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
   
