<?php

namespace App\Http\Controllers\Admin\Transport\Orders;

use App\Shop\Addresses\Repositories\Interfaces\AddressRepositoryInterface;
use App\Shop\Addresses\Transformations\AddressTransformable;
use App\Shop\Couriers\Courier;
use App\Shop\Couriers\Repositories\CourierRepository;
use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\Customers\Customer;
use App\Shop\Employees\Repositories\EmployeeRepository;
use App\Shop\Employees\Employee;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use App\Shop\Orders\Repositories\OrderRepository;
use App\Shop\OrderStatuses\OrderStatus;
use App\Shop\OrderStatuses\Repositories\Interfaces\OrderStatusRepositoryInterface;
use App\Shop\OrderStatuses\Repositories\OrderStatusRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Orders;
use App\Models\OrderTransport;
use Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Branch;
use Bitly;
use Response;
use Illuminate\Support\Facades\View;
use App\Events\OrderEvent;
use DateHelper;

class OrderController extends Controller
{
    use AddressTransformable;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepo;

    /**
     * @var CourierRepositoryInterface
     */
    private $courierRepo;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepo;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * @var OrderStatusRepositoryInterface
     */
    private $orderStatusRepo;

    private $employeeRepo;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CourierRepositoryInterface $courierRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository,
        OrderStatusRepositoryInterface $orderStatusRepository,
        EmployeeRepositoryInterface $employeeRepository
    ) {
        $this->orderRepo = $orderRepository;
        $this->courierRepo = $courierRepository;
        $this->addressRepo = $addressRepository;
        $this->customerRepo = $customerRepository;
        $this->orderStatusRepo = $orderStatusRepository;
        $this->employeeRepo = $employeeRepository;

        $this->middleware(['permission:update-order, guard:employee'], ['only' => ['edit', 'update']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $branch_id = Auth::guard('employee')->user()->branch_id;
        if (!empty($branch_id)) {
            $list = $this->orderRepo->listOrders('created_at', 'desc')->where('branch_id', $branch_id);
        } else {
            $list = $this->orderRepo->listOrders('created_at', 'desc');
        }

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($list), 10);

        return view('admin.orders.list', ['orders' => $orders]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $orderId
     *
     * @return \Illuminate\Http\Response
     */
    public function show($orderId)
    {
        $order = $this->orderRepo->findOrderById($orderId);
        $order->courier = $this->courierRepo->findCourierById($order->courier_id);
        $order->address = $this->addressRepo->findAddressById($order->address_id);

        $orderRepo = new OrderRepository($order);

        $items = $orderRepo->listOrderedProducts();

        return view('admin.orders.show', [
            'order' => $order,
            'items' => $items,
            'customer' => $this->customerRepo->findCustomerById($order->customer_id),
            'currentStatus' => $this->orderStatusRepo->findOrderStatusById($order->order_status_id),
            'payment' => $order->payment,
            'user' => auth()->guard('employee')->user(),
        ]);
    }

    /**
     * @param $orderId
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($orderId)
    {
        $order = $this->orderRepo->findOrderById($orderId);
        $order->courier = $this->courierRepo->findCourierById($order->courier_id);
        $order->address = $this->addressRepo->findAddressById($order->address_id);

        $orderRepo = new OrderRepository($order);

        $items = $orderRepo->listOrderedProducts();

        return view('admin.orders.edit', [
            'statuses' => $this->orderStatusRepo->listOrderStatuses(),
            'order' => $order,
            'items' => $items,
            'customer' => $this->customerRepo->findCustomerById($order->customer_id),
            'currentStatus' => $this->orderStatusRepo->findOrderStatusById($order->order_status_id),
            'payment' => $order->payment,
            'user' => auth()->guard('employee')->user(),
        ]);
    }

    /**
     * @param Request $request
     * @param $orderId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $orderId)
    {
        $order = $this->orderRepo->findOrderById($orderId);
        $orderRepo = new OrderRepository($order);

        if ($request->has('total_paid') && $request->input('total_paid') != null) {
            $orderData = $request->except('_method', '_token');
        } else {
            $orderData = $request->except('_method', '_token', 'total_paid');
        }

        $orderRepo->updateOrder($orderData);

        return redirect()->route('admin.orders.edit', $orderId);
    }

    /**
     * Generate order invoice.
     *
     * @param int $id
     *
     * @return mixed
     */
    public function generateInvoice(int $id)
    {
        $order = $this->orderRepo->findOrderById($id);
        $orderRepo = new OrderRepository($order);

        $items = $orderRepo->listOrderedProducts();
        $total = 0;

        foreach($items as $item){
            $total = $total + ($item->quantity * $item->price);
        }
        $total = $total;
        $order['sumTotal'] = $total; 

        if($order->payment == "destination"){
            $url = url('/order/destination/'.$order->reference);
            $shortLink = Bitly::getUrl($url);
            $order['shortLink'] = $shortLink;
        }elseif($order->payment == "branch"){
            $url = url('/order/branch/'.$order->reference);
            $shortLink = Bitly::getUrl($url);
            $order['shortLink'] = $shortLink;
        }elseif($order->payment == "credit"){
            $url = url('/order/credit/'.$order->reference);
            $shortLink = Bitly::getUrl($url);
            $order['shortLink'] = $shortLink;
        }
        
        $urlTransfer = url('/order/transfer/'.$order->reference);
        $shortLinkTransfer = Bitly::getUrl($urlTransfer);

        $order['transfer'] = $shortLinkTransfer;
        // $order['transfer'] = "test";
        // $order['shortLink'] = "test";

        $courierRepo = new CourierRepository(new Courier());
        $courier = $courierRepo->findCourierById($order->courier_id);
        $order['courier'] = $courier->cost;
        $order['phone'] = $order->address->phone;

        $data = [
            'order' => $order,
            'products' => $order->products,
            'customer' => $order->customer,
            'courier' => $order->courier,
            'address' => $this->transformAddress($order->address),
            'status' => $order->orderStatus,
            'payment' => $order->paymentMethod,
            'items' => $items,
        ];
        // dd($items);
        $pdf = \PDF::setOptions([
            'logOutputFile' => storage_path('logs/log.htm'),
            'tempDir' => storage_path('logs/'),
        ])->loadView('invoices.orders', $data, compact('orders'));

        // return view('invoices.orders', $data, compact('orders'));

        // $pdf = app()->make('dompdf.wrapper');
        // $pdf->loadView('invoices.orders', $data, compact('orders'))->stream();

        return $pdf->stream();
    }

    /**
     * @param Collection $list
     *
     * @return array
     */
    private function transFormOrder(Collection $list)
    {
        $courierRepo = new CourierRepository(new Courier());
        $customerRepo = new CustomerRepository(new Customer());
        $orderStatusRepo = new OrderStatusRepository(new OrderStatus());

        return $list->transform(function (Order $order) use ($courierRepo, $customerRepo, $orderStatusRepo) {
            $order->courier = $courierRepo->findCourierById($order->courier_id);
            $order->customer = $customerRepo->findCustomerById($order->customer_id);
            $order->status = $orderStatusRepo->findOrderStatusById($order->order_status_id);

            return $order;
        })->all();
    }

    public function changStatus(int $id)
    {
        $order = Orders::find($id);
        $orderStatus = $order->order_status_id;
        $status = $orderStatus;

        if ($orderStatus == 1) {
            $status = 2;
        }
        if ($orderStatus == 2) {
            $status = 4;
        }
        if ($orderStatus == 2) {
            $status = 4;
        }

        if ($orderStatus == 4) {
            $status = 6;
        }

        if ($orderStatus == $status) {
            return back()->with('message', 'ยังไม่ได้จ่ายเงิน');
        }

        $order->order_status_id = $status;
        $order->save();

        return back()->with('message', 'Operation Successful !');
    }

    public function getOrders()
    {
        $transport = Auth::guard('employee')->user();
        $branchObj = !empty(Auth::guard('employee')->user()->branch_id)  ? Branch::where( 'id', Auth::guard('employee')->user()->branch_id)->first() :  null ;
        
        $orders = $this->orderRepo->listOrdersGet($transport->id);
        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($orders), 10);

        return view('admin.transport.orders.list' ,  ['orders' => $orders  , 
                                                    'branchObj' => $branchObj  
                                                    ] );
    }

    public function dashBoard(Request $request)
    {
        //get order
        $staffCheck = (Auth::guard('employee')->user()->branch_id ==  null) ? :  Branch::find(Auth::guard('employee')->user()->branch_id);

        $branch_id = !empty($request->branch_id) ? $request->branch_id: ((!empty($staffCheck->branch_id) ? $staffCheck->branch_id: null) );
        $branchObj = Branch::where('branch_id', $branch_id)->first();

        $transport = Auth::guard('employee')->user();
        
        $myorders = $this->orderRepo->listOrdersGet($transport->id);
        $myorders = $this->orderRepo->paginateArrayResults($this->transFormOrder($myorders), 30);
        
        $orderFree =  Order::whereHas('getOrder')->with('getOrder.getTransport')
                            ->whereIn('order_status_id', [1 ,2])
                            ->where('branch_id', $branchObj->branch_id)
                            ->where('courier_id' , 2)
                            ->orWhere(function ($query) use ($branchObj){
                                $query->doesnthave('getOrder')->with('getOrder.getTransport')
                                    ->whereIn('payment', ['destination'])
                                    ->where('branch_id', $branchObj->branch_id)
                                    ->where('courier_id' , 2)
                                    ->where('order_status_id' , 5);
                            })
                            ->orWhereHas('getOrder.getTransport'  , function ($query) use ($branchObj){
                                $query->where('transport_id' , Auth::guard('employee')->user()->id)
                                ->where('courier_id' , 2);
                            })
                            ->orderBy('created_at' , 'desc');

        $orderCost = Order::doesnthave('getOrder')
                            ->whereIn('order_status_id', [2])
                            ->where('branch_id', $branchObj->branch_id)
                            ->whereIn('courier_id' , [2 , 3] )
                            ->orWhere(function ($query) use ($branchObj){
                                $query->doesnthave('getOrder')->with('getOrder.getTransport')
                                    ->whereIn('payment', ['destination'])
                                    ->whereIn('courier_id' , [2 , 3] )
                                    ->where('branch_id', $branchObj->branch_id)
                                    ->where('order_status_id' , 5);
                            })
                            ->orWhereHas('getOrder.getTransport'  , function ($query) use ($branchObj){
                                $query->where('transport_id' , Auth::guard('employee')->user()->id)
                                ->whereIn('courier_id' , [2 , 3] );
                            })
                            ->orderBy('created_at' , 'desc');
        // dd($orderCost->get(), $branchObj->branch_id);               

        $data = $request->all();
        $startDate =null;
        $endDate =null;
        $branch = array();
        $branch[0] = 'ไม่มีสาขา';
        $branch[1] = 'นอกสาขาที่กำหนด';
        $branchObj = !empty(Auth::guard('employee')->user()->branch_id)  ? Branch::where('branch_id', $branch_id)->first() :  null ;
        $branchs = Branch::all();

        if(!empty($data['startDate']) and !empty($data['endDate']) ){
            $startDate = Carbon::parse($data['startDate'])->startOfDay(); 
            $endDate = Carbon::parse($data['endDate'])->endOfday(); 
        }else{
            $startDate = Carbon::now()->startOfDay(); 
            $endDate = Carbon::now()->endOfday();
        }
        // order
        $orderFree = $orderFree->where('created_at' , '>=', $startDate)
                                ->where('created_at' , '<=', $endDate);
        $orderCost = $orderCost->where('created_at' , '>=', $startDate)
                                ->where('created_at' , '<=', $endDate);

        $startDate  = $startDate->format('d-m-Y');
        $endDate    = $endDate->format('d-m-Y'); 

        $staffCheck = (Auth::guard('employee')->user()->branch_id ==  null) ? :  Branch::find(Auth::guard('employee')->user()->branch_id);

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $total['count']= $orderCost->count() + $orderFree->count(); 
        $total['total']= $orderCost->sum('total') + $orderFree->sum('total'); 

        $free['count']= $orderFree->count(); 
        $free['total']= $orderFree->sum('total'); 

        $cost['count']= $orderCost->count(); 
        $cost['total']= $orderCost->sum('total'); 

        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderFree->get()), 10);
        $ordersCosts = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderCost->get()), 10);

        if($request->ajax()){
            if($request->order == "cost"){
                return Response::json(View::make('admin.transport.orders.order-cost', array('ordersCosts' => $ordersCosts))->render());
            }else if($request->order == "free"){
                return Response::json(View::make('admin.transport.orders.order-free', array('orders' => $orders))->render());
            }else{
                return Response::json(View::make('admin.transport.orders.my-order', array('myorders' => $myorders))->render());
            }
        }
        // dd($startDate);
        return view('admin.transport.dashboard',    ['orders' => $orders,
                                            'ordersCosts' => $ordersCosts, 
                                            'branch' => $branch,
                                            'branchs' => $branchs,
                                            'branch_id' => $branch_id,
                                            'total' => $total,
                                            'free' => $free,
                                            'cost' => $cost,    
                                            'branchObj' =>$branchObj,
                                            'startDate' => $startDate,
                                            'endDate' => $endDate,
                                            'myorders' => $myorders,
                    ]);
    }

    public function cancelOrder(Request $request , $id)
    {
        $transport_id = Auth::guard('employee')->user()->id;
        $order = OrderTransport::where([ 'order_id' => $id , 'transport_id' => $transport_id])->first();

        $orderCheck = $this->orderRepo->findOrderById($id);
        $orderCheck->order_status_id = 5;
        $orderCheck->save();

        if(!empty($order)){
            $order = $order->delete();
            return back()->with('success' , 'ยกเลิกออเดอร์สำเร็จ');
        }else{
            return back()->with('error' , 'เกิดข้อผิดพลาด');
        }
        
    }

    public function takeOrder($id)
    {
        $transport = Auth::guard('employee')->user();
       
        $orderTransport = new OrderTransport;
        $orderTransport->transport_id = $transport->id;
        $orderTransport->order_id = $id;

        if($orderTransport->save()){
            $order = $this->orderRepo->findOrderById($id);
            $order->order_status_id = 12;
            $order->save();

            event(new OrderEvent());

            return response()->json([
                'status' => true,
            ] ,200);
        }else{
            return response()->json([
                'status' => false,
                'errors' => ['order' => 'เกิดข้อผิดพลาด'],
            ],404);
        }

        
    }

}
