<?php

namespace App;

use App\Shop\Products\Product;
use App\Shop\Employees\Employee;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    //
    protected $table = 'branchs';

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function branch()
    {
        return $this->hasMany(Employee::class);
    }

    public function branchZipcode()
    {
     return $this->belongsTo('App\BranchZipcode', 'branch_id', 'id');
    }

    public function coupon()
    {
     return $this->hasMany(Coupon::class);
    }
}
