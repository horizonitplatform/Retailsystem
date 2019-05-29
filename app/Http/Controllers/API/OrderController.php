<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DateTime;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use App\Shop\Orders\Repositories\OrderRepository;
use App\Shop\OrderStatuses\OrderStatus;
use App\Shop\OrderStatuses\Repositories\Interfaces\OrderStatusRepositoryInterface;

class OrderController extends Controller
{
    private $orderRepo;
    private $orderStatusRepo;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderStatusRepositoryInterface $orderStatusRepository
    ){
        $this->orderRepo = $orderRepository;
        $this->orderStatusRepo = $orderStatusRepository;

    }
    public function index(Request $request) {
        if ($request->isMethod('post')) {
            return response()->json([
                'status' => false,
                'data' => [
                    'error' => [
                        'code' => 404,
                        'message' => 'Error method post request.',
                    ]
                ],
            ]);
        }
        $getParam = $this->getParam($request);
        $orderder = $this->orderRepo->getCountOrder($getParam);
        $status = true;
        $data = $orderder;
        if($orderder === false) {
             return response()->json([
                'status' => false,
                'data' => [
                    'error' => [
                        'code' => 400,
                        'message' => 'กรุณาใส่ format dateให้ถูกต้อง',
                    ]
                ],
            ]);
        } else {
            return response()->json([
                'status' => $status,
                'data' => [
                    'count' => $data
                ],
            ]);
        }


    }
    private function getParam($request)
    {
        $branch_id = '';
        $status = '';
        $startAt = '';
        $endAt = '';
        if($request->branch_id){
            $branch_id = $request->branch_id;
        }
        if($request->status){
            $status = $request->status;
        }
        if($request->startAt){
            $startAt = $request->startAt.' 00:00:00';
        }
        if($request->endAt){
            $endAt = $request->endAt. ' 00:00:00';
        }
        return [
            'branch_id' => $branch_id,
            'status' => $status,
            'startAt' => $startAt,
            'endAt' => $endAt
        ];
    }
}
