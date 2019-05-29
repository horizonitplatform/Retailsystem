<?php

namespace App\Http\Controllers\Front;

use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\OrderRepository;
use App\Shop\Orders\Transformers\OrderTransformable;
use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use App\Shop\OrderStatuses\OrderStatus;
use App\Shop\OrderStatuses\Repositories\Interfaces\OrderStatusRepositoryInterface;
use App\Shop\OrderStatuses\Repositories\OrderStatusRepository;
use App\Shop\Products\Repositories\ProductRepository;
use Auth;
use Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Customer;
use App\Models\Address;
use App\Models\Orders;
use App\Models\OrderMessenger;
use App\Models\OrderTransfer;
use App\Models\TypeShop;
use App\Models\CustomerType;
use App\Models\LocationEmployees;
use Illuminate\Support\Facades\DB;
use App\Models\SystemLink;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Branch;
use Hash;
use Bitly;
use App\Models\CouponUsed;

class AccountsController extends Controller
{
    use OrderTransformable;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * @var CourierRepositoryInterface
     */
    private $courierRepo;

    private $orderRepository;

    /**
     * AccountsController constructor.
     *
     * @param CourierRepositoryInterface $courierRepository
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        CourierRepositoryInterface $courierRepository,
        CustomerRepositoryInterface $customerRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->customerRepo = $customerRepository;
        $this->courierRepo = $courierRepository;
        $this->orderRepo = $orderRepository;
    }

    public function index()
    {
        $customer = $this->customerRepo->findCustomerById(auth()->user()->id);

        $user = Auth::user();
        if(empty($user->phone)){
            return redirect('update/social');
        }

        $customerRepo = new CustomerRepository($customer);
        $orders = $customerRepo->findOrders(['*'], 'created_at');
        $product = array();
        $orderReference =array();
        //get order of customer
        $orders->transform(function (Order $order) {
            return $this->transformOrder($order);
        });

        //get product in order
        foreach ($orders as $key => $order) {
            $orderRepo = new OrderRepository($order);
            $items = $orderRepo->listOrderedProducts();
            $product[$key] = $items ;
            array_push($orderReference, $order->reference);

            if($order->payment == "branch"){
                $shortLink = url('/order/branch/'.$order->reference);
            }else{
                $shortLink = url('/order/destination/'.$order->reference);
            }
            $order->shortLink = $shortLink;
        }
        $addresses = $customerRepo->findAddresses();

        $user = Auth::user();

        $typeUser = CustomerType::where('customer_id' , $user->id)->first();
        $type = !empty($typeUser)  ?  TypeShop::where('id' , $typeUser->type_id)->first() : null;
        $user['type'] = !empty($typeUser) ? $type->name : 'ยังไม่ระบุประเภทร้านค้า' ;

        $locationemployees = locationemployees::where('customers_id' , $user->id)->first();
        $lat = !empty($locationemployees) ? $locationemployees->lat : null ; 
        $lng = !empty($locationemployees) ? $locationemployees->lng : null ; 
        $customers_id = !empty($locationemployees) ? $locationemployees->customers_id : null ; 
        
        return view('frontend.account', [
            'customer' => $customer,
            'orders' => $this->customerRepo->paginateArrayResults($orders->toArray(), 15),
            'addresses' => $addresses,
            'user' => $user ,
            'product' => $product,
            'orderReference' => $orderReference,
            'lat' => $lat,
            'lng' => $lng,
            'customers_id' => $customers_id,
        ]);
    }
    
    public function updateAccount(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();

        $rules = [
            'name' => 'required|string|max:50',
        ];


        if (!empty($data['password'])) {
            $rules['password'] = 'required|string|confirmed|min:6';
        }
        if ($data['email'] !== $user->email ) {
            $rules['email'] = 'nullable|string|email|max:50|unique:customers,email' ;
        }
        if ($data['phone'] !== $user->phone) {
            $rules['phone'] = 'nullable|string|max:10|unique:customers,phone';
        }
        if (!empty($data['image'])) {
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
        }

        $validator = Validator::make($data, $rules);

        if (!$validator->fails()) {
            if (!empty($data['password'])) {
              $user->password = bcrypt($data['password']);
            }
            if (!empty($data['name'])) {
              $user->name = $data['name'];
            }
            if (!empty($data['email'])) {
                $user->email = $data['email'];
              }
            if (!empty($data['phone'])) {
                $user->phone = $data['phone'];
            }
            if($request->hasFile('image')) {
                $randomstr = substr(md5(microtime()), rand(0, 26), 7);
                $image       = $request->file('image');
                $filename    = $randomstr . $image->getClientOriginalName();
                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(125, 125);
                $image_resize->save(public_path('images/' .$filename));

                $user->profile_photo = $filename;
            }
            if ($user->save()) {
                return response()->json([
                    'status' => true,
                ] ,200);
            }else {
                return response()->json([
                    'status' => false,
                    'errors' => ['_error' => 'ไม่สามารถบันทึกข้อมูลได้ หรือข้อมูลไม่มีการเปลี่ยนแปลง'],
                ],404);
            }
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ],404);
        }

    }

    public function destination($reference)
    {
        $id = Orders::where('reference' , $reference )->first()->id;
        $order = $this->orderRepo->findOrderById($id);
        $customer = $this->customerRepo->findCustomerById($order->customer_id);
        $orderRepo = new OrderRepository($order);
        $items = $orderRepo->listOrderedProducts();

        return view('frontend.destination ',  ['order' => $order ,  'customer' => $customer  , 'items' => $items ]);
    }

    public function transfer($reference)
    {
        $id = Orders::where('reference' , $reference )->first()->id;
        $order = $this->orderRepo->findOrderById($id);
        $customer = $this->customerRepo->findCustomerById($order->customer_id);
        $orderRepo = new OrderRepository($order);
        $items = $orderRepo->listOrderedProducts();

        return view('frontend.orders.transfer ',  ['order' => $order ,  'customer' => $customer  , 'items' => $items ]);
    }

    public function branch($reference)
    {
        $id = Orders::where('reference' , $reference )->first()->id;
        $order = $this->orderRepo->findOrderById($id);
        $customer = $this->customerRepo->findCustomerById($order->customer_id);
        $orderRepo = new OrderRepository($order);
        $items = $orderRepo->listOrderedProducts();

        return view('frontend.orders.branch ',  ['order' => $order ,  'customer' => $customer  , 'items' => $items ]);
    }

    public function credit($reference)
    {
        $id = Orders::where('reference' , $reference )->first()->id;
        $order = $this->orderRepo->findOrderById($id);
        $customer = $this->customerRepo->findCustomerById($order->customer_id);
        $orderRepo = new OrderRepository($order);
        $items = $orderRepo->listOrderedProducts();

        return view('frontend.orders.credit ',  ['order' => $order ,  'customer' => $customer  , 'items' => $items ]);
    }

    public function confirmOrder(Request $request)
    {
        $order =  Orders::findOrFail($request->id);

        if($order->order_status_id == 8 or $order->order_status_id == 9){
            return response()->json([
                'order' => 'ออเดอร์นี้มีการยืนยันรับของไปแล้ว',
                'status' => false,
            ],404);
        }


        if(!empty($order)){
            $result = $this->checkProductsOrder($order->id);

            // if($result  == "OK"){
                if(!empty($request->note)){
                    $information = !empty($request->infomation) ? $request->infomation : $request->note ;
    
                    $messenger =  new OrderMessenger;
                    $messenger->order_id = $request->id ;
                    $messenger->information =  $information;
                    $messenger->save();
    
                    $order->order_status_id = 8 ;
                    $order->save();
    
                }else{
                    $order->total_paid = $order->total;
                    $order->order_status_id = 9 ;
                    $order->save();
                }

                // return response()->json([
                //     'status' => true,
                // ] ,200);
            // }else{
            //     return response()->json([
            //         'status' => false,
            //         'errors' => ['order' => 'สินค้ามีไม่เพียงพอ กรุณาทำการสั่งสินค้าใหม่'],
            //     ],404);
            // }
            
            return response()->json([
                'status' => true,
            ] ,200);
        }else{
            return response()->json([
                'status' => false,
            ],404);
        }

    }

    public function branchOrder(Request $request)
    {
        $order =  Orders::findOrFail($request->id);

        if($order->order_status_id == 11){
            return response()->json([
                'order' => 'ออเดอร์นี้มีการยืนยันรับของไปแล้ว',
                'status' => false,
            ],404);
        }

        if(!empty($order)){
            $result = $this->checkProductsOrder($order->id);

            // if($result  == "OK"){
                $order->total_paid = $order->total;
                $order->order_status_id = 11;
                $order->save();

                return response()->json([
                    'status' => true,
                ] ,200);
            // }else{
            //     return response()->json([
            //         'status' => false,
            //         'errors' => ['order' => 'สินค้ามีไม่เพียงพอ กรุณาทำการสั่งสินค้าใหม่'],
            //     ],404);
            // }
            
        }else{
            return response()->json([
                'status' => false,
            ],404);
        }

    }

    public function creditOrder(Request $request)
    {
        $order =  Orders::findOrFail($request->id);

        if($order->order_status_id == 6){
            return response()->json([
                'order' => 'ออเดอร์นี้มีการยืนยันรับของไปแล้ว',
                'status' => false,
            ],404);
        }


        if(!empty($order)){
        	if($order->order_status_id == 5){
                $result = $this->checkProductsOrder($order->id);
            }
            $order->total_paid = $order->total;
            $order->order_status_id = 6;
            $order->save();
            
            return response()->json([
                'status' => true,
            ] ,200);
        }else{
            return response()->json([
                'status' => false,
            ],404);
        }

    }

    public function transferOrder(Request $request)
    {
        $data = $request->all();

        $rules = array(
            'image' => 'mimes:jpeg,jpg,png,gif|required|max:10000' // max 10000kb
          );
        $validator = Validator::make($data, $rules);

        // Check to see if validation fails or passes
        if ($validator->fails()){
            return response()->json([
                    'error' => $validator->errors()->getMessages()
                ], 400);
        }else{
            $order =  Orders::findOrFail($data['id']);

            if(!empty($order)){
                if ($request->hasFile('image')) {
                    $randomstr = substr(md5(microtime()), rand(0, 26), 15);
                    $image = $request->file('image');
                    $filename = $image->getClientOriginalName();
                    $type = $image ->getClientOriginalExtension();
                    $image_resize = Image::make($image->getRealPath());
                    // $image_resize->resize(741, 321);
                    $image_resize->save(public_path('storage/transfer/'.$randomstr. "." . $type));

                    $transfer = new OrderTransfer();
                    $transfer->order_id = $order->id;
                    $transfer->customer_id = $order->customer_id;
                    $transfer->src = 'storage/transfer/'.$randomstr . "." .$type;
                    $transfer->save();

                    $order->total_paid = $order->total;
                    $order->order_status_id = 10 ;
                    $order->save();
                    

                    return response()->json([
                        'status' => true,
                    ] ,200);

                }else{
                    return response()->json([
                        'status' => false,
                        'errors' => ['image' => 'ไม่มีไฟล์รูปภาพ'] ,
                    ],404);
                }

            }else{
                return response()->json([
                    'status' => false,
                ],404);
            }
        }
    }

    public function cancelOrder(Request $request)
    {
        $data = $request->all();
        $order =  Orders::findOrFail($data['id']);

        if(!empty($order)){
            $order->order_status_id = 3;
            $order->save();

            $couponUsed = CouponUsed::where('order_id' , $order->id)->first();
            if(!empty($couponUsed)){
                $couponUsed->delete();
            }

            return response()->json([
                'status' => true,
            ] ,200);
        }else{
            return response()->json([
                'status' => false,
            ],404);
        }

    }

    public function checkProductsOrder($id)
    {
        $order = $this->orderRepo->findOrderById($id);
        $orderRepo = new OrderRepository($order);
        $productsObject = $orderRepo->listOrderedProducts();

        foreach ($productsObject as $product) {
            $uom = $product['description'];
            if (empty($uom)) {
                $productBranch = ProductBranch::where([
                    'branch_id' => $order->branch_id,
                    'product_id' => $product->id,
                ])->first();

                $productUom = ProductBranchUOM::where([
                    'product_branch_id' => $productBranch->id,
                    'um_convert' => 1,
                ])->first();

                $uom = $productUom->uom;
            }
            if ($uom == 'BOX') {
                $uom = 'Box';
            }

            $link = SystemLink::where([
                'system_id' => $product->id,
                'type' => 'product',
            ])->first();

            $products[] = [
                'uom' => $uom,
                'qty' => $product->quantity,
                'price' => $product->price,
                'item_id' => $link->link_id,
            ];
        }
        $uomMapping = [
        'PACK' => 'PCK',
        'Box' => 'BOX',
        'BOX' => 'BOX',
        'Kilogram' => 'KG',
        ];

        foreach ($products as &$product) {
            if (isset($uomMapping[$product['uom']])) {
                $product['uom'] = $uomMapping[$product['uom']];
            }
        }

        $branch_id = $order->branch_id;

        $branchObject = Branch::where([
            'branch_id' => $branch_id,
        ])->first();

        $branchPrefix = explode(' ', $branchObject->name);
        $branchPrefix = $branchPrefix[0];
        $courier = $order->courier()->get()->toArray()[0];
        $shippingCost = $courier['is_free'] ? 0 : intval($courier['cost']);

        $param = [
                'doc_no' => 'WEB-'.$branchPrefix.'-'.sprintf('%010d', $order->id).'',
                'pmf_org_id' => $branch_id,
                'billed_delivery' => $shippingCost,
                'billed_discount' => intval($order->discounts),
                'json_selling' => json_encode($products, true),
                ] ; 


        $postdata = http_build_query($param);
        $opts = array('http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
            ),
        );
        $context = stream_context_create($opts);
        
        $result = file_get_contents('http://3bbfc.ddns.cyberoam.com:96/prod/horizont/transaction/sell_issue', false, $context);
        // dd($opts , $context , $param , $result);
        $fp = fopen(storage_path('logs/sell_issue/'.date('Y-m-d').'.log'), 'a');
        fwrite($fp, json_encode([
            'TIME' => date('d-m-Y H:i:s'),
            'param' => $param,
            'result' => $result,
        ], true).",\r\n");
        fclose($fp);

        return $result;
    }
}
