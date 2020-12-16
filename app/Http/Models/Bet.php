<?php
namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Bet extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bets';
    protected $guarded = [];
}
