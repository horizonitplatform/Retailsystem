<?php

namespace App\Http\Controllers\Admin\Transport;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Shop\Admins\Requests\CreateEmployeeRequest;
use App\Shop\Admins\Requests\UpdateEmployeeRequest;
use App\Shop\Employees\Repositories\EmployeeRepository;
use App\Shop\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Shop\Roles\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Branch;
use App\Models\TransportDrive;
use App\Models\RoleUser;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
// use GuzzleHttp\Message\Response;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use App\Shop\Orders\Repositories\OrderRepository;
use App\Shop\OrderStatuses\OrderStatus;
use App\Shop\OrderStatuses\Repositories\Interfaces\OrderStatusRepositoryInterface;
use App\Shop\OrderStatuses\Repositories\OrderStatusRepository;
use App\Shop\Couriers\Courier;
use App\Shop\Couriers\Repositories\CourierRepository;
use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\Customers\Customer;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use Auth;
use Response;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;

class TransportController extends Controller
{   
     /**
     * @var EmployeeRepositoryInterface
     */
    private $employeeRepo;
    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepo;

    /**
     * EmployeeController constructor.
     *
     * @param EmployeeRepositoryInterface $employeeRepository
     * @param RoleRepositoryInterface $roleRepository
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CourierRepositoryInterface $courierRepository,
        EmployeeRepositoryInterface $employeeRepository,
        CustomerRepositoryInterface $customerRepository,
        RoleRepositoryInterface $roleRepository
    ) {
        $this->orderRepo = $orderRepository;
        $this->courierRepo = $courierRepository;
        $this->employeeRepo = $employeeRepository;
        $this->roleRepo = $roleRepository;
        $this->customerRepo = $customerRepository;
    }

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

    public function index()
    {
        $transport_id = $this->roleRepo->findRoleByName('transport')->id;
        $transport = RoleUser::where('role_id' , $transport_id )->get();
        $transport_lists = array();

        foreach($transport as $list){
            array_push($transport_lists  , $list->user_id);
        }

        $transports = $this->employeeRepo->listEmployees('created_at', 'desc')->whereIn('id' , $transport_lists);
        
        return view('admin.transport.list' ,[
            'transport_lists' => $this->employeeRepo->paginateArrayResults($transports->all(),10),
        ]);
    }

    public function create()
    {
        $branchs = Branch::all();
        $role = $this->roleRepo->findRoleByName('transport');
        return view('admin.transport.create' ,[
            'branchs' => $branchs , 
            'role' => $role ,
        ]);
    }

    public function store(CreateEmployeeRequest $request)
    {
        $employee = $this->employeeRepo->createEmployee($request->all());
        
        if ($request->has('role')) {
            $employeeRepo = new EmployeeRepository($employee);
            $employeeRepo->syncRoles([$request->input('role')]);
        }

        return redirect()->route('admin.transport.index');
    }

    public function edit(int $id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $role = $this->roleRepo->findRoleByName('transport');
        $isCurrentUser = $this->employeeRepo->isAuthUser($employee);
        $branchs = Branch::all();
                
        return view(
            'admin.transport.edit',
            [
                'employee' => $employee,
                'role' => $role,
                'isCurrentUser' => $isCurrentUser,
                'selectedIds' => $employee->roles()->pluck('role_id')->all(),
                'branchs' => $branchs,
            ]
        );
    }

    public function update(UpdateEmployeeRequest $request, $id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $isCurrentUser = $this->employeeRepo->isAuthUser($employee);

        $empRepo = new EmployeeRepository($employee);
        $empRepo->updateEmployee($request->except('_token', '_method', 'password'));

        if ($request->has('password') && !empty($request->input('password'))) {
            $employee->password = Hash::make($request->input('password'));
            $employee->save();
        }

        if ($request->has('roles') and !$isCurrentUser) {
            $employee->roles()->sync($request->input('roles'));
        } elseif (!$isCurrentUser) {
            $employee->roles()->detach();
        }

        return redirect()->route('admin.transport.edit', $id)
            ->with('message', 'Update successful');
    }

    public function destroy(int $id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);
        $employeeRepo = new EmployeeRepository($employee);
        $employeeRepo->deleteEmployee();
        
        return redirect()->route('admin.transport.index')->with('message', 'Delete successful');
    }
    public function registerTransport()
    {
        $branchs = Branch::all();
        return view('auth.transport.register' , ['branchs' => $branchs ]);
    }

    public function storeTransport(Request $request)
    {

        $messages = [
            'email.unique'    => 'มีการใช้  email นี้แล้ว',
            'email.email'    => 'รูปแบบ email ไม่ถูกต้อง',
            'password.confirmed' => 'รหัสผ่าน กับ รหัสผ่านยืนยัน ไม่เหมือนกัน',
            'password.min' => 'รหัสผ่านที่ใช้ต้องมีอย่างน้อย 8 หลัก',
            'phone.between' => 'หมายเลขโทรศัพท์ต้องมีจำนวน 9-10 หลัก',
            'phone.unique' => 'มีการใช้หมายเลขโทรศัพท์นี้แล้ว'
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|',
            'email' => 'required|string|email|max:50|unique:employees,email|unique:employees,email',
            'password' => 'required|string|confirmed|min:8',
            'phone' => 'required|string|between:9,10|unique:employees,phone',
        ], $messages);

        if ($validator->fails()) {
            // dd($request->all() , $validator->errors());
            // return redirect('transport/register')->withErrors($validator->errors())->withInput();
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ] ,400);
        }else{
            $employee = $this->employeeRepo->createEmployee($request->all());
        
            if ($request->has('role')) {
                $employeeRepo = new EmployeeRepository($employee);
                $employeeRepo->syncRoles([$request->input('role')]);

                $id = $employee->id;
                // dd($id , $employee);
                $transportDrive = TransportDrive::where('transport_id' , $id)->first();
                if(empty($transportDrive)){
                    $regisDrive = new TransportDrive;
                    $regisDrive->transport_id = $id;
                    $regisDrive->drive_type = $request->input('type');
                    $regisDrive->drive_id = $request->input('drive_id');
                    $regisDrive->save();
                }else{
                    $transportDrive->drive_type = $request->input('type');
                    $transportDrive->drive_id = $request->input('drive_id');
                    $transportDrive->save();
                }
            }
        }

        return response()->json([
            'status' => true,
        ] ,200);
        // return redirect('transport/register')->with('success' , 'Register succes');
    }

    public function checkTransportFromMember(Request $request)
    {   
        $messages = [
            'password.min' => 'รหัสผ่านที่ใช้ต้องมีอย่างน้อย 8 หลัก',
            'phone.between' => 'หมายเลขโทรศัพท์ต้องมีจำนวน 9-10 หลัก',
            'phone.unique' => 'มีการใช้หมายเลขโทรศัพท์นี้แล้ว',
            'phone.numeric' => 'หมายเลขโทรศัพท์ต้องเป็นตัวเลขเท่านั้น'
        ];

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|between:9,10|unique:employees,phone',
        ], $messages);

        $data = $request->all();

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ] ,400);
        }else{
            $checkMember = Customer::where('phone' , $data['phone'])->first();
            if(!empty($checkMember)){
                return response()->json([
                    'status' => true,
                    'name' => $checkMember->name,
                    'email' => $checkMember->email,
                ] ,200);
            }else{
                return response()->json([
                    'status' => true,
                ] ,200);
            }
        }
    }

    public function transportRequest()
    {
        $topic ='รายชื่อคำร้องขอเป็นสมาชิก';
        $transport_id = $this->roleRepo->findRoleByName('transport')->id;
        $transport = RoleUser::where('role_id' , $transport_id )->get();
        $transport_lists = array();

         foreach($transport as $list){
             array_push($transport_lists  , $list->user_id);
         }
 
         $transports = $this->employeeRepo->listEmployees('created_at', 'desc')->whereIn('id' , $transport_lists)->where('status' , 0);
         
        return view('admin.transport.list' ,[
            'transport_lists' => $this->employeeRepo->paginateArrayResults($transports->all(),10),
            'topic' => $topic,
        ]);
    }

    public function approve($id)
    {
        $employee = $this->employeeRepo->findEmployeeById($id);

        if(!empty($employee)){

            $phoneAPI = !empty($employee->phone) ? "66" . substr($employee->phone, 1) : null ;
            // $verifyAPI = random_int(100000 , 999999);
            if(!empty($phoneAPI)){
                $client = new Client();
            
                $request = $client->post('https://api2.ants.co.th/sms/1/text/single', [
                    'headers' => [
                        'contentType' => 'application/json',
                        'Authorization' => 'Basic SG9yaXpvbnRAOkhAejU2OTg4=',
                    ],
                    'json'    => [ "form" => "Horizont" ,"to" => $phoneAPI , "text"=> 'คุณได้รับสิทธิสมาชิกขนส่งกับทาง Horizont แล้วโดยผู้ดูแลของ Horizont'],
                ]);
                $response = $request->getBody()->getContents();
                $status =  $request->getStatusCode();

                if($status == '200'){ 
                    $employee->status = 1 ; 
                    $employee->save() ; 
                }else{
                    return back()->with('error' , 'ไม่สามารถยืนยันสมาชิกได้');
                }

            }else{
                return back()->with('error' , 'ไม่สามารถยืนยันสมาชิกได้');
            }
            return back()->with('success' , 'อนมุัติผู้ใช้เรียบร้อยแล้ว');
        }else{
            return back()->with('error' , 'ไม่สามารถยืนยันสมาชิกได้');
        }
    }

    public function dashboard(Request $request)
    {
        //get order
        // $staffCheck = (Auth::guard('employee')->user()->branch_id ==  null) ? :  Branch::find(Auth::guard('employee')->user()->branch_id);

        // $branch_id = !empty($request->branch_id) ? $request->branch_id: ((!empty($staffCheck->branch_id) ? $staffCheck->branch_id: null) );
        // $branchObj = Branch::where('branch_id', $branch_id)->first();
        $branch_id = null ; 
        $ordersAll =  Order::with('getOrder')->whereIn('courier_id' , [2,3])->orderBy('created_at' , 'desc');
        $orderFree =  Order::with('getOrder')->where('courier_id' , 2)->orderBy('created_at' , 'desc');

        $orderCost = Order::with('getOrder')->where('courier_id' , 3)->orderBy('created_at' , 'desc');

        // dd($orderCost->get());                    

        $data = $request->all();
        $startDate =null;
        $endDate =null;
        $branch_id = !empty($request->branch_id) ? $request->branch_id : 'all' ; 
        $branch = array();
        $branch[0] = 'ไม่มีสาขา';
        $branch[1] = 'นอกสาขาที่กำหนด';
        $branchObj = null;
        $branchs = Branch::all();

        if(!empty($data['startDate']) and !empty($data['endDate']) ){
            $startDate = Carbon::parse($data['startDate'])->startOfDay(); 
            $endDate = Carbon::parse($data['endDate'])->endOfday(); 

            // order
            $ordersAll = $ordersAll->where('created_at' , '>=', $startDate)
                            ->where('created_at' , '<=', $endDate);
            $orderFree = $orderFree->where('created_at' , '>=', $startDate)
                                ->where('created_at' , '<=', $endDate);
            $orderCost = $orderCost->where('created_at' , '>=', $startDate)
                                ->where('created_at' , '<=', $endDate);
            // dd($orderCost->get());
            $startDate  = $startDate->format('d-m-Y');
            $endDate    = $endDate->format('d-m-Y');        
        }
        if (!empty($branch_id) and $branch_id !== 'all') {
            $branchObj = Branch::where('branch_id', $branch_id)->first();
            $ordersAll = $ordersAll->where('branch_id', $branchObj->branch_id);
            $orderCost = $orderCost->where('branch_id', $branchObj->branch_id);
            $orderFree = $orderFree->where('branch_id', $branchObj->branch_id);

            // dd($ordersAll->get());
        }
        // $staffCheck = (Auth::guard('employee')->user()->branch_id ==  null) ? :  Branch::find(Auth::guard('employee')->user()->branch_id);

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
        $ordersAll = $this->orderRepo->paginateArrayResults($this->transFormOrder($ordersAll->get()), 10);
       
        foreach ($branchs as $key => $item) {
            $branch[$item->branch_id] = $item->name;
        }

        if($request->ajax()){
            if($request->order == "cost"){
                return Response::json(View::make('admin.transport.orders.order-cost', array('ordersCosts' => $ordersCosts ,'branch' => $branch  , 'branch_id' => $branch_id ))->render());
            }elseif($request->order == "free"){
                return Response::json(View::make('admin.transport.orders.order-free', array('orders' => $orders ,'branch' => $branch , 'branch_id' => $branch_id ))->render());
            }else{
                return Response::json(View::make('admin.transport.orders.order-all', array('ordersAll' => $ordersAll ,'branch' => $branch ,'branch_id' => $branch_id ))->render());
            }
        }

        return view('admin.transport.admin.dashboard',    ['orders' => $orders,
                                            'ordersAll' => $ordersAll, 
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
                    ]);
    }
}
