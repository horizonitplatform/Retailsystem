<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Branch;


class Coupon extends Model
{
	protected $table = "coupon";

	public function branch()
    {
        return $this->belongsToMany(Branch::class);
    }
}
