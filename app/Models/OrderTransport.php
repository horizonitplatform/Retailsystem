<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Shop\Orders\Order;

class OrderTransport extends Model
{
	protected $table = "order_transport";
	
	public function getOrder()
    {
     return $this->hasOne('App\Shop\Orders\Order', 'id', 'order_id');
	}
	
	public function getTransport()
    {
     return $this->hasOne('App\Shop\Employees\Employee', 'id', 'transport_id');
    }
}
