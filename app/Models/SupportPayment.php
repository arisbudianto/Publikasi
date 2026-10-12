<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPayment extends Model
{
    protected $fillable = ['user_id', 'amount_idr', 'status', 'version'];

    protected function casts(): array
    {
        return ['amount_idr' => 'integer', 'version' => 'integer'];
    }

    public function events()
    {
        return $this->hasMany(SupportPaymentEvent::class);
    }

    public const LABELS = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi manual', 'declined' => 'Tidak terverifikasi', 'cancelled' => 'Dibatalkan'];
}
