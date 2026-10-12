<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPaymentEvent extends Model
{
    protected $fillable = ['support_payment_id', 'actor_id', 'status', 'note'];
}
