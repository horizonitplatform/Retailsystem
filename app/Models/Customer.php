<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
	protected $table = "customers";

	public function getBranchZipcode()
    {
     return $this->hasOne('App\BranchZipcode', 'zipcode', 'zipcode');
	}
	
}
