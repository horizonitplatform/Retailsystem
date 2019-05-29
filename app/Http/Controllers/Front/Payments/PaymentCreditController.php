<?php

namespace App\Http\Controllers\Front\Payments;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUsed;
use App\Branch;
use App\Shop\Carts\Repositories\CartRepository;
use App\Shop\Carts\Repositories\Interfaces\CartRepositoryInterface;
use App\Shop\Checkout\CheckoutRepository;
use App\Shop\Couriers\Courier;
use App\Shop\Customers\Customer;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Orders\Repositories\OrderRepository;
use App\Shop\OrderStatuses\OrderStatus;
use App\Shop\OrderStatuses\Repositories\OrderStatusRepository;
use App\Shop\Shipping\ShippingInterface;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\ProductBranch;
use App\Models\ProductBranchUOM;
use App\Models\SystemLink;
use Ramsey\Uuid\Uuid;
use Shippo_Shipment;
use Shippo_Transaction;
use DB;
use Exception;
use App\Events\OrderEvent;

class PaymentCreditController extends Controller
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepo;

    /**
     * @var int
     */
    private $shippingFee;

    private $rateObjectId;

    private $shipmentObjId;

    private $billingAddress;

    private $carrier;

    /**
     * BankTransferController constructor.
     *
     * @param Request                 $request
     * @param CartRepositoryInterface $cartRepository
     * @param ShippingInterface       $shippingRepo
     */
    public function __construct(
        Request $request,
        CartRepositoryInterface $cartRepository,
        ShippingInterface $shippingRepo
    ) {
        $this->cartRepo = $cartRepository;
        $fee = 0;
        $rateObjId = null;
        $shipmentObjId = null;
        $billingAddress = $request->input('billing_address');

        $this->shippingFee = $fee;
        $this->rateObjectId = $rateObjId;
        $this->shipmentObjId = $shipmentObjId;
        $this->billingAddress = $billingAddress;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $this->store($request);

//        return view('front.payments.payment-credit-redirect', [
//            'subtotal' => $this->cartRepo->getSubTotal(),
//            'shipping' => $this->shippingFee,
//            'tax' => $this->cartRepo->getTax(),
//            'total' => $this->cartRepo->getTotal(2, $this->shippingFee),
//            'rateObjectId' => $this->rateObjectId,
//            'shipmentObjId' => $this->shipmentObjId,
//            'billingAddress' => $this->billingAddress
//        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $checkoutRepo = new CheckoutRepository();
        $orderStatusRepo = new OrderStatusRepository(new OrderStatus());
        $os = $orderStatusRepo->findById(5);
        $shippingFee = 0;
        $courierId = $request->input('courier');
        $coupon = $request->input('coupon');
        $destination = $request->input('destination');

        $courier = Courier::where(['id' => $courierId, 'status' => 1])->first();
        $customerRepo = new CustomerRepository(new Customer());
        $customer = $customerRepo->findCustomerById(auth()->id());

        $addresses = $customer->addresses()->get();
        $branchList = [];
        $activeAddressList = [];
        foreach ($addresses as $item) {
            $branchList[$item->id] = $this->cartRepo->getBranch($item);
            if ($branchList[$item->id] > 0) {
                $activeAddressList[$item->id] = $branchList[$item->id];
            }
        }
        $branchId = $activeAddressList[$request->input('address_id')];

        if ($courier && (int) $courier->cost > 0) {
            $shippingFee += $courier->cost;
        }

        $total = $this->cartRepo->getTotal(2, $shippingFee);
        $payment = !empty($destination) ? strtolower(config('payment-destination.name')) : strtolower(config('payment-credit.name'));

        $discounts = 0;
        if (!empty($coupon)) {
            $couponObject = $this->checkCoupon($coupon);
            if ($couponObject) {
                if ($couponObject['type'] === 'price') {
                    $discounts = (int) $couponObject['value'];
                }
                if ($couponObject['type'] === 'percent') {
                    $discounts = floor($total * (int) $couponObject['value'] / 100);
                }
                $total -= $discounts;
            }
        }

        $address = Address::find($request->input('address_id'));

        $order = $checkoutRepo->buildCheckoutItems([
            'reference' => Uuid::uuid4()->toString(),
            'courier_id' => $request->input('courier'), // @deprecated
            'customer_id' => $request->user()->id,
            'address_id' => $request->input('address_id'),
            'order_status_id' => $os->id,
            'payment' => $payment,
            'discounts' => $discounts,
            'total_products' => $this->cartRepo->getSubTotal(),
            'total' => $total,
            'total_paid' => 0,
            'tax' => $this->cartRepo->getTax(),
            'branch_id' => $branchId,
            'address_order' => $address->address_1.' '.$address->phone,
        ]);

        if ($discounts > 0 && $couponObject !== null) {
            $couponUsed = new CouponUsed();
            $couponUsed->order_id = $order->id;
            $couponUsed->customer_id = $request->user()->id;
            $couponUsed->coupon_id = $couponObject->id;
            $couponUsed->save();
        }

//        $shipment = Shippo_Shipment::retrieve($this->shipmentObjId);
//        $details = [
//            'shipment' => [
//                'address_to' => json_decode($shipment->address_to, true),
//                'address_from' => json_decode($shipment->address_from, true),
//                'parcels' => [json_decode($shipment->parcels[0], true)]
//            ],
//            'carrier_account' => $this->carrier->carrier_account,
//            'servicelevel_token' => $this->carrier->servicelevel->token
//        ];
//        $transaction = Shippo_Transaction::create($details);
//        if ($transaction['status'] != 'SUCCESS'){
//            Log::error($transaction['messages']);
//            return redirect()->route('checkout.index')->with('error', 'There is an error in the shipment details. Check logs.');
//        }
        $orderRepo = new OrderRepository($order);
//        $orderRepo->updateOrder([
//            'courier' => $this->carrier->provider,
//            'label_url' => $transaction['label_url'],
//            'tracking_number' => $transaction['tracking_number']
//        ]);

        $order = $orderRepo->findOrderById($order->id);
        $productsObject = $orderRepo->listOrderedProducts();
        $total_product = 0 ;

        foreach ($productsObject as $product) {
            $total_product =  $total_product + ($product['price'] * $product['quantity'] );
        }
        $tax = $this->cartRepo->getTax();
        $courierCost = $order->courier()->get()->toArray()[0]['cost'];
        $total_product = !empty($courierCost) ? $total_product + $courierCost : $total_product ;
        
        $order->total_products = $total_product;
        $order->total = $total_product - $discounts;
        $order->save();

        Cart::destroy();
        $this->cartRepo->clearCart();

        $orderId = $orderRepo->transform()->getAttribute('id');
        $total = $order->getAttribute('total');

        Artisan::call('check:qtyAPI', [
            'id' => $orderId,
        ]);

//        echo '<form id="form" name="payFormCcard" method="post" action="https://psipay.bangkokbank.com/b2c/eng/payment/payForm.jsp">
//            <input type="hidden" name="merchantId" value="4095">
//            <input type="hidden" name="amount" value="'.sprintf('%.2f',$total).'" >
//            <input type="hidden" name="orderRef" value="TEST'.sprintf('%010d',$orderId).'">
//            <input type="hidden" name="currCode" value="764" >
//            <input type="hidden" name="successUrl" value="https://www.horizontfood.com/accounts">
//            <input type="hidden" name="failUrl" value="https://www.horizontfood.com/accounts">
//            <input type="hidden" name="cancelUrl" value="https://www.horizontfood.com/accounts">
//            <input type="hidden" name="payType" value="N">
//            <input type="hidden" name="lang" value="E">
//            <input type="hidden" name="remark" value="TEST">
//            </form>
//            <script >document.getElementById("form").submit()</script>
//        ';
//        exit;

        echo '<form id="form" name="payFormCcard" method="post" action="https://ipay.bangkokbank.com/b2c/eng/payment/payForm.jsp">
                <input type="hidden" name="merchantId" value="5796">
                <input type="hidden" name="amount" value="'.sprintf('%.2f', $total).'" >
                <input type="hidden" name="orderRef" value="'.sprintf('%010d', $orderId).'">
                <input type="hidden" name="currCode" value="764" >
                <input type="hidden" name="successUrl" value="https://www.horizontfood.com/accounts">
                <input type="hidden" name="failUrl" value="https://www.horizontfood.com/accounts">
                <input type="hidden" name="cancelUrl" value="https://www.horizontfood.com/accounts">
                <input type="hidden" name="payType" value="N">
                <input type="hidden" name="lang" value="E">
                <input type="hidden" name="remark" value="">
                </form>
                <script >document.getElementById("form").submit()</script>
            ';
        exit;

//        return redirect()->away('https://www.plamworapot.com')->withInputs($input);
    }

    private function checkCoupon($coupon_code)
    {
        $couponExist = Coupon::where('coupon_code', $coupon_code)->first();
        if (!empty($couponExist)) {
            $usedAll = CouponUsed::where([
                'coupon_id' => $couponExist->id,
            ])->count();

            $customer = (new CustomerRepository(new Customer()))->findCustomerById(auth()->id());
            $usedOwn = CouponUsed::where([
                'coupon_id' => $couponExist->id,
                'customer_id' => $customer->id,
            ])->count();

            if ($usedAll >= $couponExist->limit) {
                return null;
            }
            if ($usedOwn > 0) {
                return null;
            }

            $total = (float) str_replace([','], '', Cart::total());
            if ($total <= $couponExist->minimum) {
                return null;
            }

            return $couponExist;
        } else {
            return null;
        }
    }

    public function destination(Request $request)
    {
        $checkoutRepo = new CheckoutRepository();
        $orderStatusRepo = new OrderStatusRepository(new OrderStatus());
        $os = $orderStatusRepo->findById(5);
        $shippingFee = 0;
        $courierId = $request->input('courier');
        $coupon = $request->input('coupon');
        $destination = $request->input('destination');

        $courier = Courier::where(['id' => $courierId, 'status' => 1])->first();
        $customerRepo = new CustomerRepository(new Customer());
        $customer = $customerRepo->findCustomerById(auth()->id());

        $addresses = $customer->addresses()->get();
        $branchList = [];
        $activeAddressList = [];
        foreach ($addresses as $item) {
            $branchList[$item->id] = $this->cartRepo->getBranch($item);
            if ($branchList[$item->id] > 0) {
                $activeAddressList[$item->id] = $branchList[$item->id];
            }
        }
        $branchId = $activeAddressList[$request->input('address_id')];

        if ($courier && (int) $courier->cost > 0) {
            $shippingFee += $courier->cost;
        }

        $total = $this->cartRepo->getTotal(2, $shippingFee);
        $payment = !empty($destination) ? strtolower(config('payment-destination.name')) : strtolower(config('payment-credit.name'));

        $discounts = 0;
        if (!empty($coupon)) {
            $couponObject = $this->checkCoupon($coupon);
            if ($couponObject) {
                if ($couponObject['type'] === 'price') {
                    $discounts = (int) $couponObject['value'];
                }
                if ($couponObject['type'] === 'percent') {
                    $discounts = floor($total * (int) $couponObject['value'] / 100);
                }
                $total -= $discounts;
            }
        }

        $address = Address::find($request->input('address_id'));

        DB::beginTransaction();
        try {
            if($this->cartRepo->countItems() <  1 ){
                throw new Exception();
            }

            $order = $checkoutRepo->buildCheckoutItems([
                'reference' => Uuid::uuid4()->toString(),
                'courier_id' => $request->input('courier'), // @deprecated
                'customer_id' => $request->user()->id,
                'address_id' => $request->input('address_id'),
                'order_status_id' => $os->id,
                'payment' => $payment,
                'discounts' => $discounts,
                'total_products' => $this->cartRepo->getSubTotal(),
                'total' => $total,
                'total_paid' => 0,
                'tax' => $this->cartRepo->getTax(),
                'branch_id' => $branchId,
                'address_order' => $address->address_1.' '.$address->phone,
            ]);

            // event(new OrderEvent($order));

            if ($discounts > 0 && $couponObject !== null) {
                $couponUsed = new CouponUsed();
                $couponUsed->order_id = $order->id;
                $couponUsed->customer_id = $request->user()->id;
                $couponUsed->coupon_id = $couponObject->id;
                $couponUsed->save();
            }

            $orderRepo = new OrderRepository($order);
            // Cart::destroy();

            $order = $orderRepo->findOrderById($order->id);

            Artisan::call('check:qtyAPI', [
                'id' => $order->id,
            ]);

            $products = [];
            $total_product = 0 ;

            $branch_id = $order->branch_id;
            $productsObject = $orderRepo->listOrderedProducts();

            foreach ($productsObject as $product) {
                $total_product =  $total_product + ($product['price'] * $product['quantity'] );
                $uom = $product['description'];
                if (empty($uom)) {
                    $productBranch = ProductBranch::where([
                        'branch_id' => $branch_id,
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

            $tax = $this->cartRepo->getTax();
            $courierCost = $order->courier()->get()->toArray()[0]['cost'];
            $total_product = !empty($courierCost) ? $total_product + $courierCost : $total_product ;
           
            $order->total_products = $total_product;
            $order->total = $total_product - $discounts;
            $order->save();

            foreach ($products as &$product) {
                if (isset($uomMapping[$product['uom']])) {
                    $product['uom'] = $uomMapping[$product['uom']];
                }
            }
            $branchObject = Branch::where([
                'branch_id' => $branch_id,
            ])->first();

            $branchPrefix = explode(' ', $branchObject->name);
            $branchPrefix = $branchPrefix[0];

            $courier = $order->courier()->get()->toArray()[0];

            $shippingCost = $courier['is_free'] ? 0 : intval($courier['cost']);

            // $this->updateQtyApi([
            // 'doc_no' => 'WEB-'.$branchPrefix.'-'.sprintf('%010d', $order->id).'',
            // 'pmf_org_id' => $branch_id,
            // 'billed_delivery' => $shippingCost,
            // 'billed_discount' => intval($order->discounts),
            // 'json_selling' => json_encode($products, true),
            // ]);

            DB::commit();
            event(new OrderEvent($order));
        } catch (\Exception $ex) {
            DB::rollback();
            return view('frontend.after-purchase')->withErrors(['error' => 'เกิดข้อผิดพลาดในการสั่งซื้อ']);
        }
        $this->cartRepo->clearCart();
        return view('frontend.after-purchase');
    }

    public function branch(Request $request)
    {
        $checkoutRepo = new CheckoutRepository();
        $orderStatusRepo = new OrderStatusRepository(new OrderStatus());
        $os = $orderStatusRepo->findById(5);
        $shippingFee = 0;
        $courierId = $request->input('courier');
        $coupon = $request->input('coupon');
        $destination = $request->input('destination');

        $courier = Courier::where(['id' => $courierId, 'status' => 1])->first();
        $customerRepo = new CustomerRepository(new Customer());
        $customer = $customerRepo->findCustomerById(auth()->id());

        $addresses = $customer->addresses()->get();
        $branchList = [];
        $activeAddressList = [];
        foreach ($addresses as $item) {
            $branchList[$item->id] = $this->cartRepo->getBranch($item);
            if ($branchList[$item->id] > 0) {
                $activeAddressList[$item->id] = $branchList[$item->id];
            }
        }
        $branchId = $activeAddressList[$request->input('address_id')];

        if ($courier && (int) $courier->cost > 0) {
            $shippingFee += $courier->cost;
        }

        $total = $this->cartRepo->getTotal(2, $shippingFee);
        // $payment = !empty($destination) ? strtolower(config('payment-destination.name')) : strtolower(config('payment-credit.name'));

        $discounts = 0;
        if (!empty($coupon)) {
            $couponObject = $this->checkCoupon($coupon);
            if ($couponObject) {
                if ($couponObject['type'] === 'price') {
                    $discounts = (int) $couponObject['value'];
                }
                if ($couponObject['type'] === 'percent') {
                    $discounts = floor($total * (int) $couponObject['value'] / 100);
                }
                $total -= $discounts;
            }
        }

        $address = Address::find($request->input('address_id'));

        DB::beginTransaction();
        try {
            if($this->cartRepo->countItems() <  1 ){
                throw new Exception();
            }

            $order = $checkoutRepo->buildCheckoutItems([
                'reference' => Uuid::uuid4()->toString(),
                'courier_id' => $request->input('courier'), // @deprecated
                'customer_id' => $request->user()->id,
                'address_id' => $request->input('address_id'),
                'order_status_id' => $os->id,
                'payment' => 'branch',
                'discounts' => $discounts,
                'total_products' => $this->cartRepo->getSubTotal(),
                'total' => $total,
                'total_paid' => 0,
                'tax' => $this->cartRepo->getTax(),
                'branch_id' => $branchId,
                'address_order' => $address->address_1.' '.$address->phone,
            ]);

            event(new OrderEvent($order));
            
            if ($discounts > 0 && $couponObject !== null) {
                $couponUsed = new CouponUsed();
                $couponUsed->order_id = $order->id;
                $couponUsed->customer_id = $request->user()->id;
                $couponUsed->coupon_id = $couponObject->id;
                $couponUsed->save();
            }

            $orderRepo = new OrderRepository($order);
            // Cart::destroy();

            $order = $orderRepo->findOrderById($order->id);

            Artisan::call('check:qtyAPI', [
                'id' => $order->id,
            ]);

            $products = [];

            $total_product = 0 ; 
            $branch_id = $order->branch_id;
            $productsObject = $orderRepo->listOrderedProducts();

            foreach ($productsObject as $product) {
                $total_product =  $total_product + ($product['price'] * $product['quantity'] );
                $uom = $product['description'];

                if (empty($uom)) {
                    $productBranch = ProductBranch::where([
                        'branch_id' => $branch_id,
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

            $tax = $this->cartRepo->getTax();
            $courierCost = $order->courier()->get()->toArray()[0]['cost'];
            $total_product = !empty($courierCost) ? $total_product + $courierCost : $total_product ;
            
            $order->total_products = $total_product;
            $order->total = $total_product - $discounts;
            $order->save();

            foreach ($products as &$product) {
                if (isset($uomMapping[$product['uom']])) {
                    $product['uom'] = $uomMapping[$product['uom']];
                }
            }
            $branchObject = Branch::where([
                'branch_id' => $branch_id,
            ])->first();

            $branchPrefix = explode(' ', $branchObject->name);
            $branchPrefix = $branchPrefix[0];

            $courier = $order->courier()->get()->toArray()[0];

            $shippingCost = $courier['is_free'] ? 0 : intval($courier['cost']);
            
            // $this->updateQtyApi([
            // 'doc_no' => 'WEB-'.$branchPrefix.'-'.sprintf('%010d', $order->id).'',
            // 'pmf_org_id' => $branch_id,
            // 'billed_delivery' => $shippingCost,
            // 'billed_discount' => intval($order->discounts),
            // 'json_selling' => json_encode($products, true),
            // ]);
            
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();
            // dd($ex);
            // return response()->json(['error' => $ex->getMessage()], 500);
            return view('frontend.after-purchase')->withErrors(['error' => 'เกิดข้อผิดพลาดในการสั่งซื้อ']);
        }

        $this->cartRepo->clearCart();
        return view('frontend.after-purchase');
    }

    public function updateQtyApi($param)
    {
        $postdata = http_build_query($param);
        $opts = array('http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
            ),
        );
        $context = stream_context_create($opts);
        // dd($opts , $context , $param);
        $result = file_get_contents(ENV('PRODUCT_API').'/transaction/sell_issue', false, $context);
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
