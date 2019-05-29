<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Shop\Addresses\Repositories\Interfaces\AddressRepositoryInterface;
use App\Shop\Addresses\Transformations\AddressTransformable;
use App\Shop\Couriers\Courier;
use App\Shop\Couriers\Repositories\CourierRepository;
use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\Customers\Customer;
use App\Shop\Customers\Repositories\CustomerRepository;
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
use App\Models\OrderMessenger;
use App\Models\OrderTransfer;
use Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Branch;
use Bitly;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Attributes\Repositories\AttributeRepositoryInterface;
use App\Shop\Attributes\Repositories\AttributeRepository;
use App\Shop\AttributeValues\Repositories\AttributeValueRepositoryInterface;
use App\Shop\AttributeValues\Repositories\AttributeValueRepository;
use Response;
use Illuminate\Support\Facades\View;
use App\Models\OrderTransport;
use App\Shop\Employees\Repositories\EmployeeRepository;
use App\Shop\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Models\TransportDrive;
use App\Models\CouponUsed;

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

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CourierRepositoryInterface $courierRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository,
        OrderStatusRepositoryInterface $orderStatusRepository,
        ProductRepositoryInterface $productRepository,
        AttributeRepositoryInterface $attributeRepository,
        EmployeeRepositoryInterface $employeeRepository,
        AttributeValueRepositoryInterface $attributeValueRepository
    ) {
        $this->orderRepo = $orderRepository;
        $this->courierRepo = $courierRepository;
        $this->addressRepo = $addressRepository;
        $this->customerRepo = $customerRepository;
        $this->orderStatusRepo = $orderStatusRepository;
        $this->productRepo = $productRepository;
        $this->employeeRepo = $employeeRepository;
        $this->attributeValueRepo = $attributeValueRepository;

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

        if(!empty($request->order_status_id)  and $request->order_status_id == 3 ){
            $couponUsed = CouponUsed::where('order_id' , $orderId)->first();
            if(!empty($couponUsed)){
                $couponUsed->delete();
            }
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
            // $url = url('/order/destination/'.$order->reference);
            // $shortLink = Bitly::getUrl($url);
            // $order['shortLink'] = $shortLink;
            
            $order['shortLink'] = url('/order/destination/'.$order->reference);
        }elseif($order->payment == "branch"){
            // $url = url('/order/branch/'.$order->reference);
            // $shortLink = Bitly::getUrl($url);
            // $order['shortLink'] = $shortLink;
            $order['shortLink'] = url('/order/branch/'.$order->reference);
        }elseif($order->payment == "credit"){
            // $url = url('/order/credit/'.$order->reference);
            // $shortLink = Bitly::getUrl($url);
            // $order['shortLink'] = $shortLink;
            $order['shortLink'] = url('/order/credit/'.$order->reference);
        }
        
        // $urlTransfer = url('/order/transfer/'.$order->reference);
        // $shortLinkTransfer = Bitly::getUrl($urlTransfer);

        // $order['transfer'] = $shortLinkTransfer;
        $order['transfer'] = url('/order/transfer/'.$order->reference);
        // $order['shortLink'] = url('/order/branch/'.$order->reference);

        $branch = Branch::where('branch_id' , $order->branch_id )->first();
        $order['branch'] = !empty($branch) ? $branch->name : null ;


        $transport = OrderTransport::where('order_id' , $id)->first();

        if(!empty($transport)){
            $transportId = $transport->transport_id ; 
            $order['transport'] = $this->employeeRepo->findEmployeeById($transportId);
            $drive =  TransportDrive::where('transport_id' , $transportId)->first() ;
            $order['drive'] = !empty($drive) ? $drive->drive_id :  null  ; 
        }


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
        
        $pdf = \PDF::setOptions([
            'logOutputFile' => storage_path('logs/log.htm'),
            'tempDir' => storage_path('logs/'),
        ])->loadView('invoices.orders', $data, compact('order'));

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

    public function chart($start , $end)
    {
        // dd($start , $end);
        $get = Order::where('courier_id' , 1);
        $free = Order::where('courier_id' , 2);  
        $cost = Order::where('courier_id' , 3);  
        
        $courier['get'] = $get->count() ; 
        $courier['free'] = $free->count() ; 
        $courier['cost'] = $cost->count() ; 

        return $courier;
    }

    public function dashBoard(Request $request)
    {
        if(Auth::guard('employee')->user()->hasRole('admin (logistic)')){
            return redirect('admin/transport-dashboard');
        }
        //get order
        $orderder = Order::with('getOrder')
                            ->where('order_status_id' , '!=' , 3)
                            ->orderBy('created_at' , 'desc');  
        $orderCancel = Order::with('getOrder')
                        ->where('order_status_id', 3)
                        ->orderBy('created_at' , 'desc');  

        $orderStatuses = $this->orderStatusRepo->listOrderStatuses();
        $orderStatusId = null;
        //report
        $total = DB::table('orders')
                ->whereNotIn('order_status_id', [ 3, 7 , 8])
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $paid = DB::table('orders')
                ->whereIn('order_status_id', [6 , 9 , 10 ,11])
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $wait = DB::table('orders')
                ->where('order_status_id', 5)
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $waitTransport = DB::table('orders')
                ->whereIn('order_status_id', [1, 2, 4])
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $refund = DB::table('orders')
                ->where('order_status_id', 7)
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $cancel = DB::table('orders')
                ->where('order_status_id', 3)
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $cash = DB::table('orders')
                ->whereIn('order_status_id', [9,11])
                ->select(DB::raw('count(id) as count'), DB::raw('SUM(total) as total'));

        $data = $request->all();
        $startDate = null;
        $endDate = null;
        
        if (!empty($data['startDate']) and !empty($data['endDate'])) {
            $startDate = Carbon::parse($data['startDate'])->startOfDay();
            $endDate = Carbon::parse($data['endDate'])->endOfday();

        }else{
            $startDate = Carbon::now()->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }

        //report
        $total = $total->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
        $paid = $paid->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
        $wait = $wait->whereDate('created_at', ' >=', $startDate)->whereDate('created_at', '<=', $endDate);
        $waitTransport = $waitTransport->whereDate('created_at', ' >=', $startDate)->whereDate('created_at', '<=', $endDate);
        $refund = $refund->whereDate('created_at', ' >=', $startDate)->whereDate('created_at', '<=', $endDate);
        $cancel = $cancel->whereDate('created_at', ' >=', $startDate)->whereDate('created_at', '<=', $endDate);
        $cash = $cash->whereDate('created_at', ' >=', $startDate)->whereDate('created_at', '<=', $endDate);

        //order
        $orderder = $orderder->where('created_at', '>=', $startDate)
                            ->where('created_at', '<=', $endDate);

        $orderCancel = $orderCancel->where('created_at', '>=', $startDate)
                            ->where('created_at', '<=', $endDate);

        // $startDate = $startDate->format('d-m-Y');
        // $endDate = $endDate->format('d-m-Y');

        $staffCheck = (Auth::guard('employee')->user()->branch_id == null) ?: Branch::find(Auth::guard('employee')->user()->branch_id);

        $branch_id = !empty($request->branch_id) ? $request->branch_id : ((!empty($staffCheck->branch_id) ? $staffCheck->branch_id : null));
        $branchs = Branch::all();
        $branch = array();
        $branch[0] = 'ไม่มีสาขา';
        $branch[1] = 'นอกสาขาที่กำหนด';
        $branchObj = null;

        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }

        if (!empty($data['status_id']) and $data['status_id'] !== 'all') {
            $orderder = $orderder->where('order_status_id'  , $data['status_id']);
            $orderStatusId = $data['status_id'];
        }

        if (!empty($branch_id) and $branch_id !== 'all') {
            $branchObj = Branch::where('branch_id', $branch_id)->first();

            if (Auth::guard('employee')->user()->hasRole('staff') == true) {
                // $orderder = Order::where('branch_id', $branchObj->branch_id)
                //                 ->whereNotIn('order_status_id', [ 3 , 7])
                //                 ->where('created_at', '>=', $startDate)
                //                 ->where('created_at', '<=', $endDate)
                //                 ->orWhere(function ($query) use ($data , $branchObj , $startDate , $endDate ){
                //                     $query->whereIn('payment', ['destination' , 'branch'])
                //                         ->where('branch_id', $branchObj->branch_id)
                //                         ->where('created_at', '>=', $startDate)
                //                         ->where('created_at', '<=', $endDate)
                //                         ->where('order_status_id' , 5);
                //                 })
                //                 ->orderBy('created_at' , 'desc');
                $orderder = Order::where('branch_id', $branchObj->branch_id)
                                    ->where('order_status_id', '!=' , 3)
                                    ->where('created_at', '>=', $startDate)
                                    ->where('created_at', '<=', $endDate)
                                    ->orderBy('created_at' , 'desc');

                $orderCancel = Order::where('branch_id', $branchObj->branch_id)
                                    ->where('order_status_id', 3)
                                    ->where('created_at', '>=', $startDate)
                                    ->where('created_at', '<=', $endDate)
                                    ->orderBy('created_at' , 'desc');
            } else {
                $orderder = $orderder->where('branch_id', $branchObj->branch_id);
                $orderCancel = $orderCancel->where('branch_id', $branchObj->branch_id);
            }

            $total = $total->where('branch_id', $branchObj->branch_id)->first();
            $paid = $paid->where('branch_id', $branchObj->branch_id)->first();
            $wait = $wait->where('branch_id', $branchObj->branch_id)->first();
            $waitTransport = $waitTransport->where('branch_id', $branchObj->branch_id)->first();
            $refund = $refund->where('branch_id', $branchObj->branch_id)->first();
            $cancel = $cancel->where('branch_id', $branchObj->branch_id)->first();
            $cash = $cash->where('branch_id', $branchObj->branch_id)->first();
        } else {
            $total = $total->first();
            $paid = $paid->first();
            $wait = $wait->first();
            $waitTransport = $waitTransport->first();
            $refund = $refund->first();
            $cancel = $cancel->first();
            $cash = $cash->first();
        }

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $startDate = $startDate->format('d-m-Y');
        $endDate = $endDate->format('d-m-Y');
        
        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder->get()), 30);
        $orderCancel = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderCancel->get()), 30);

        if($request->ajax()){
            $type = !empty($data['type']) ? $data['type'] : null ; 

            if(!empty($type) and $type == "normal") {
                return Response::json(View::make('admin.orders.paginate.list', array('orders' => $orders,
                'branch' => $branch,
                'branchs' => $branchs,
                'branch_id' => $branch_id,
                'total' => $total,
                'paid' => $paid,
                'wait' => $wait,
                'waitTransport' => $waitTransport,
                'cancel' => $cancel,
                'refund' => $refund,
                'cash' => $cash,
                'branchObj' => $branchObj,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'orderStatuses' => $orderStatuses,
                'orderStatusId' => $orderStatusId,))->render());
            }elseif(!empty($type) and $type == "cancel" ){
                return Response::json(View::make('admin.orders.paginate.list', array('orderCancel' => $orderCancel,
                'branch' => $branch,
                'branchs' => $branchs,
                'branch_id' => $branch_id,
                'total' => $total,
                'paid' => $paid,
                'wait' => $wait,
                'waitTransport' => $waitTransport,
                'cancel' => $cancel,
                'refund' => $refund,
                'cash' => $cash,
                'branchObj' => $branchObj,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'orderStatuses' => $orderStatuses,
                'orderStatusId' => $orderStatusId,))->render());
            }
            
        }

        // $chart = $this->chart($startDate , $endDate);
        // dd($chart);

        return view('admin.orders.list', ['orders' => $orders,
                                            'orderCancel' => $orderCancel,  
                                            'branch' => $branch,
                                            'branchs' => $branchs,
                                            'branch_id' => $branch_id,
                                            'total' => $total,
                                            'paid' => $paid,
                                            'wait' => $wait,
                                            'waitTransport' => $waitTransport,
                                            'cancel' => $cancel,
                                            'refund' => $refund,
                                            'cash' => $cash,
                                            'branchObj' => $branchObj,
                                            'startDate' => $startDate,
                                            'endDate' => $endDate,
                                            'orderStatuses' => $orderStatuses,
                                            'orderStatusId' => $orderStatusId,
                                            // 'chart' => $chart,
                    ]);
    }

    public function listOrder($type, Request $request)
    {
        $orderder = $this->orderRepo->listOrders('created_at', 'desc');

        switch ($type) {
            case 'waitTransport':
                $orderder = $orderder->whereIn('order_status_id', [1, 2, 4]);
                break;
            case 'paid':
                $orderder = $orderder->whereIn('order_status_id', [6 , 9 , 10 ,11]);
                break;
            case 'wait':
                $orderder = $orderder->where('order_status_id', 5);
                break;
            case 'refund':
                $orderder = $orderder->where('order_status_id', 7);
                break;
            case 'cash':
                $orderder = $orderder->whereIn('order_status_id',  [9 , 11 ]);
                break;
            case 'all':
                $orderder = $orderder->whereNotIn('order_status_id', [3,7,8]);
                break;
            default:
                abort(404);
                break;
        }

        $data = $request->all();
        $startDate = null;
        $endDate = null;

        if (!empty($data['startDate']) and !empty($data['endDate'])) {
            $startDate = Carbon::parse($data['startDate'])->startOfDay();
            $endDate = Carbon::parse($data['endDate'])->endOfday();

            //order
            $orderder = $orderder->where('created_at', '>=', $startDate)
                                 ->where('created_at', '<=', $endDate);
        }

        $staffCheck = (Auth::guard('employee')->user()->branch_id == null) ?: Branch::find(Auth::guard('employee')->user()->branch_id);

        $branch_id = !empty($request->branch_id) ? $request->branch_id : ((!empty($staffCheck->branch_id) ? $staffCheck->branch_id : null));

        $branchs = Branch::all();
        $branch = array();
        $branch[0] = 'ไม่มีสาขา';
        $branch[1] = 'นอกสาขาที่กำหนด';
        $branchObj = null;
        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }

        if (!empty($branch_id) and $branch_id !== 'all') {
            $branchObj = Branch::where('branch_id', $branch_id)->first();

            if (Auth::guard('employee')->user()->hasRole('staff') == true) {
                $orderder = $orderder->where('branch_id', $branchObj->branch_id)
                ->whereNotIn('order_status_id', [3,7,8]);
                            //    ->whereNotIn('order_status_id', [3,7,8]);
            } else {
                $orderder = $orderder->where('branch_id', $branchObj->branch_id);
            }
        }

        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder), 10);

        return view('admin.orders.ordersListType', ['orders' => $orders,
                                            'branch' => $branch,
                                            'branchs' => $branchs,
                                            'branch_id' => $branch_id,
                                            'branchObj' => $branchObj,
                                            'startDate' => $startDate,
                                            'endDate' => $endDate,
                    ]);
    }

    public function pickUp()
    {
        $branch_id = Auth::guard('employee')->user()->branch_id;
        $branchs = Branch::all();
        $branch = array();
        $branch[0] = 'ไม่มีสาขา';
        $branch[1] = 'นอกสาขาที่กำหนด';
        $branchObj = null;
        $startDate = null;
        $endDate = null;

        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }

        if (!empty($branch_id)) {
            $branchObj = Branch::where('id', $branch_id)->first();
            // dd($branchObj , $branch_id);
            $orderder = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('branch_id', $branchObj->branch_id)
                            ->whereIn('order_status_id', [1, 2, 4, 6])
                            ->where('courier_id', 1);
        } else {
            $orderder = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('courier_id', 1);
        }

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder), 10);

        return view('admin.orders.list', ['orders' => $orders,
                                            'branch' => $branch,
                                            'branchs' => $branchs,
                                            'branchObj' => $branchObj,
                                            'startDate' => $startDate,
                                            'endDate' => $endDate,
                                        ]);
    }

    public function delivery()
    {
        $yesterday = Carbon::today()->subHours(8)->subMinutes(30);
        $morning = Carbon::today()->addHours(10)->addMinutes(30);
        $eveing = Carbon::today()->addHours(15)->addMinutes(30);

        $branch_id = Auth::guard('employee')->user()->branch_id;
        $branchs = Branch::all();
        $branch = array();
        $branch[0] = 'ไม่มีสาขา';
        $branch[1] = 'นอกสาขาที่กำหนด';
        $checkBranch = null;
        $branchObj = null;
        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }

        if (!empty($branch_id)) {
            $branchObj = Branch::where([
                'id' => $branch_id,
            ])->first();

            $orderder_morning = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('branch_id', $branchObj->branch_id)
                            ->whereNotIn('order_status_id', [5 , 3 , 7])
                            ->whereIn('courier_id', [2, 3])
                            ->where('created_at', '>', $yesterday)
                            ->where('created_at', '<', $morning);

            $orderder_evening = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('branch_id', $branchObj->branch_id)
                            ->whereNotIn('order_status_id', [5 , 3 , 7])
                            ->whereIn('courier_id', [2, 3])
                            ->where('created_at', '>', $morning)
                            ->where('created_at', '<', $eveing);
            $orderder_express = $this->orderRepo->listOrders('created_at', 'desc')
                            ->whereIn('courier_id', [2, 3])
                            ->whereNotIn('order_status_id', [5 , 3 , 7])
                            ->where('branch_id', $branchObj->branch_id);
        } else {
            $orderder_morning = $this->orderRepo->listOrders('created_at', 'desc')
                            ->whereIn('courier_id', [2, 3])
                            ->where('created_at', '>', $yesterday)
                            ->where('created_at', '<', $morning);

            $orderder_evening = $this->orderRepo->listOrders('created_at', 'desc')
                            ->whereIn('courier_id', [2, 3])
                            ->where('created_at', '>', $morning)
                            ->where('created_at', '<', $eveing);

            $orderder_express = $this->orderRepo->listOrders('created_at', 'desc')
                                ->whereIn('courier_id', [2, 3])
                                ->where('created_at', '>', $morning)
                                ->where('created_at', '<', $eveing);
        }

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $list_morning = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder_morning), 30);
        $list_evening = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder_evening), 30);
        $expresses = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder_express), 30);

        return view('admin.dashboard', ['list_morning' => $list_morning,
                                        'list_evening' => $list_evening,
                                        'branch' => $branch,
                                        'expresses' => $expresses,
                                        'branchs' => $branchs,
                                        'checkBranch' => $checkBranch,
                                        'branchObj' => $branchObj,
                                        ]);
    }

    public function pickUpBranch($id)
    {
        $branchs = Branch::all();
        $branch = array();
        $branchObj = null;
        $startDate = null;
        $endDate = null;
        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }

        $branchObj = Branch::where([
                'id' => $id,
        ])->first();
        $orderder = $this->orderRepo->listOrders('created_at', 'desc')
                            ->whereNotIn('order_status_id', [5 , 3 , 7])
                            ->where('branch_id', $branchObj->branch_id)
                            ->where('courier_id', 1);

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $orders = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder), 10);

        return view('admin.orders.list', ['orders' => $orders,
                                            'branch' => $branch,
                                            'branchs' => $branchs,
                                            'branchObj' => $branchObj,
                                            'startDate' => $startDate,
                                            'endDate' => $endDate,
                                        ]);
    }

    public function deliveryBranch($id)
    {
        $yesterday = Carbon::today()->subHours(8)->subMinutes(30);
        $morning = Carbon::today()->addHours(10)->addMinutes(30);
        $eveing = Carbon::today()->addHours(15)->addMinutes(30);

        $branchs = Branch::all();
        $branch = array();
        $checkBranch = null;
        $branchObj = null;

        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }
        $branchObj = Branch::where([
                'id' => $id,
            ])->first();

        $orderder_morning = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('branch_id', $branchObj->branch_id)
                            ->whereIn('courier_id', [2, 3])
                            ->where('created_at', '>', $yesterday)
                            ->where('created_at', '<', $morning);

        $orderder_evening = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('branch_id', $branchObj->branch_id)
                            ->whereIn('courier_id', [2, 3])
                            ->where('created_at', '>', $morning)
                            ->where('created_at', '<', $eveing);
        $orderder_express = $this->orderRepo->listOrders('created_at', 'desc')
                            ->where('created_at', '>', $morning)
                            ->where('created_at', '<', $eveing)
                            ->whereIn('courier_id', [2, 3])
                            ->where('branch_id', $branchObj->branch_id);

        $checkBranch = $branchObj->branch_id;

        if (request()->has('q')) {
            $list = $this->orderRepo->searchOrder(request()->input('q') ?? '');
        }

        $list_morning = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder_morning), 10);
        $list_evening = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder_evening), 10);
        $expresses = $this->orderRepo->paginateArrayResults($this->transFormOrder($orderder_express), 10);

        return view('admin.dashboard', ['list_morning' => $list_morning,
                                        'list_evening' => $list_evening,
                                        'branch' => $branch,
                                        'expresses' => $expresses,
                                        'branchs' => $branchs,
                                        'checkBranch' => $checkBranch,
                                        'branchObj' => $branchObj,
                                        ]);
    }

    public function refurnOrder($id)
    {
        $order = Orders::find($id);
        $orderStatus = $order->order_status_id;
        $status = $orderStatus;

        if ($orderStatus == 1 or $orderStatus == 6) {
            $status = 7;

            $order->order_status_id = $status;
            $order->save();

            return back()->with('message', 'เปลี่ยนสถานะ "คืนเงิน" เสร็จสิ้น');
        } else {
            return back()->with('message', 'ยังไม่ได้จ่ายเงิน');
        }
    }

    public function cancleOrder($id)
    {
        $order = Orders::find($id);
        $orderStatus = $order->order_status_id;
        $status = $orderStatus;

        if ($orderStatus !== 3) {
            $status = 3;
            $order->order_status_id = $status;
            $order->save();

            $couponUsed = CouponUsed::where('order_id' , $id)->first();
            if(!empty($couponUsed)){
                $couponUsed->delete();
            }

            return back()->with('message', 'ยกเลิก ออเดอร์เสร็จสิ้น');
        } else {
            return back()->with('message', 'ยังไม่ได้จ่ายเงิน');
        }
    }

    public function messenger(Request $request , $id)
    {
        $data = $request->all();

        if($data['status'] == 8 ){
            $messenger = OrderMessenger::where('order_id' , $data['id'])->first();
            if(!empty($messenger)){
                return response()->json([
                    'status' => true,
                    'information' => $messenger->information,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                ]);
            }
        }else if($data['status'] == 9){
            $order = Orders::find($data['id']);
            $date = $order->updated_at->format('d-m-Y  H:i');

            if(!empty($order)){
                return response()->json([
                    'status' => true,
                    'information' => "ลูกค้ารับของเมื่อ : " . $date,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                ]);
            }
        }else if($data['status'] == 10){
            $transfer = OrderTransfer::where('order_id' , $data['id'])->first();
            $src = $transfer->src;

            if(!empty($transfer)){
                return response()->json([
                    'status' => true,
                    'src' => $src ,
                    'date' => $transfer->updated_at->format('d-m-Y  H:i') ,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                ]);
            }

        }else if($data['status'] == 11){
            $order = Orders::find($data['id']);
            $date = $order->updated_at->format('d-m-Y  H:i');

            if(!empty($order)){
                return response()->json([
                    'status' => true,
                    'information' => "ลูกค้าชำระเงินเมื่อ: " . $date,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                ]);
            }
        }
    }
    public function drive(Request $request)
    {
        $data = $request->all();
        $transport = $this->employeeRepo->findEmployeeById($data['id']);
       
        if(!empty($transport)){
            $drive = TransportDrive::where('transport_id' , $transport->id)->first();

            return response()->json([
                'status' => true , 
                'infomation' => $transport ,
                'drive' =>  $drive ,
            ]);
        }else{
            
            return response()->json([
                'status' => false,
            ]);
        }

    }
}
