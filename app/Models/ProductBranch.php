<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBranch extends Model
{
	protected $table = "product_branch";

	public function getProduct(){
		 return $this->belongsTo('App\Shop\Products\Product', 'product_id');
	}
}


