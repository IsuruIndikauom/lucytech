<?php
namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'balance_transactions';
    protected $guarded = [];
}
