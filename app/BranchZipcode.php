<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BranchZipcode extends Model
{
    //
    protected $table = 'branch_zipcode';

    public function getBranch()
    {
     return $this->hasOne('App\Branch', 'id', 'branch_id');
    }

    public function customer()
    {
     return $this->belongsTo('App\Models\Customer', 'zipcode', 'zipcode');
    }
}
