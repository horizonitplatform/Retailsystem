<?php

namespace App\Http\Controllers\Front;

use App\Models\CouponUsed;
use App\Shop\Addresses\Repositories\Interfaces\AddressRepositoryInterface;
use App\Shop\Cart\Requests\CartCheckoutRequest;
use App\Shop\Carts\Repositories\Interfaces\CartRepositoryInterface;
use App\Shop\Carts\Requests\PayPalCheckoutExecutionRequest;
use App\Shop\Carts\Requests\StripeExecutionRequest;
use App\Shop\Couriers\Courier;
use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\Customers\Customer;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use App\Shop\PaymentMethods\Paypal\Exceptions\PaypalRequestError;
use App\Shop\PaymentMethods\Paypal\Repositories\PayPalExpressCheckoutRepository;
use App\Shop\PaymentMethods\Stripe\Exceptions\StripeChargingErrorException;
use App\Shop\PaymentMethods\Stripe\StripeRepository;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\Products\Transformations\ProductTransformable;
use App\Shop\Shipping\ShippingInterface;
use Exception;
use App\Http\Controllers\Controller;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PayPal\Exception\PayPalConnectionException;
use Auth;
use App\Shop\Products\Repositories\ProductRepository;
use App\Models\Coupon;
use App\BranchZipcode;
use App\Branch;
use App\Models\BranchCoupon;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    use ProductTransformable;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepo;

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
     * @var ProductRepositoryInterface
     */
    private $productRepo;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepo;

    /**
     * @var PayPalExpressCheckoutRepository
     */
    private $payPal;

    /**
     * @var ShippingInterface
     */
    private $shippingRepo;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        CourierRepositoryInterface $courierRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        ShippingInterface $shipping
    ) {
        $this->cartRepo = $cartRepository;
        $this->courierRepo = $courierRepository;
        $this->addressRepo = $addressRepository;
        $this->customerRepo = $customerRepository;
        $this->productRepo = $productRepository;
        $this->orderRepo = $orderRepository;

        $payPalRepo = new PayPalExpressCheckoutRepository();
        $this->payPal = $payPalRepo;
        $this->shippingRepo = $shipping;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // $products = $this->cartRepo->getCartItems();
        //
        // foreach ($products as $item ) {
        //     $getProduct = $this->productRepo->findProductById($item->id);
        //     $productRepo = new ProductRepository($getProduct);
        //     $quantity = $productRepo->getQuantity();
        //     dd($productRepo);
        //      dd($quantity);
        // }
        // $productRepo = new ProductRepository($product);
        // $product['price'] = $productRepo->getPrice();
        // $product['quantity'] = $productRepo->getQuantity();

        $customer = $request->user();
        $rates = null;
        $shipment_object_id = null;

        if (env('ACTIVATE_SHIPPING') == 1) {
            $shipment = $this->createShippingProcess($customer, $products);
            if (!is_null($shipment)) {
                $shipment_object_id = $shipment->object_id;
                $rates = $shipment->rates;
            }
        }

        // Get payment gateways
        $paymentGateways = collect(explode(',', config('payees.name')))->transform(function ($name) {
            return config($name);
        })->all();

        $billingAddress = $customer->addresses()->first();

        $user = Auth::user();
        if ($user && $user->role !== 'premium') {
            if ((int) $this->cartRepo->getTotal(2) >= 2000) {
                $couriers = Courier::where(['status' => 1])->where('id', '!=', '3')->get();
            } else {
                $couriers = Courier::where(['status' => 1])->where('id', '!=', '2')->get();
            }
        } else {
            $couriers = Courier::where(['status' => 1])->where('id', '!=', '3')->get();
        }

        $addresses = $customer->addresses()->get();
        $branchList = [];
        $activeAddressList = [];
        foreach ($addresses as $item) {
            $branchList[$item->id] = $this->cartRepo->getBranch($item);
            if ($branchList[$item->id] > 0) {
                $activeAddressList[$item->id] = $branchList[$item->id];
            }
        }

        return view('front.checkout', [
            'customer' => $customer,
            'billingAddress' => $billingAddress,
            'addresses' => $customer->addresses()->get(),
            'products' => $this->cartRepo->getCartItems(),
            'subtotal' => $this->cartRepo->getSubTotal(),
            'tax' => $this->cartRepo->getTax(),
            'total' => $this->cartRepo->getTotal(2),
            'payments' => $paymentGateways,
            'cartItems' => $this->cartRepo->getCartItemsTransformed(),
            'shipment_object_id' => $shipment_object_id,
            'rates' => $rates,
            'couriers' => $couriers,
            'branchList' => $branchList,
            'activeAddressList' => $activeAddressList,
            'isDefaultAddress' => count($activeAddressList) == 1,
        ]);
    }

    /**
     * Checkout the items.
     *
     * @param CartCheckoutRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     * @codeCoverageIgnore
     *
     * @throws \App\Shop\Customers\Exceptions\CustomerPaymentChargingErrorException
     */
    public function store(CartCheckoutRequest $request)
    {
        $shippingFee = 0;

        switch ($request->input('payment')) {
            case 'paypal':
                return $this->payPal->process($shippingFee, $request);
                break;
            case 'stripe':

                $details = [
                    'description' => 'Stripe payment',
                    'metadata' => $this->cartRepo->getCartItems()->all(),
                ];

                $customer = $this->customerRepo->findCustomerById(auth()->id());
                $customerRepo = new CustomerRepository($customer);
                $customerRepo->charge($this->cartRepo->getTotal(2, $shippingFee), $details);
                break;
            default:
        }
    }

    /**
     * Execute the PayPal payment.
     *
     * @param PayPalCheckoutExecutionRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function executePayPalPayment(PayPalCheckoutExecutionRequest $request)
    {
        try {
            $this->payPal->execute($request);
            $this->cartRepo->clearCart();

            return redirect()->route('checkout.success');
        } catch (PayPalConnectionException $e) {
            throw new PaypalRequestError($e->getData());
        } catch (Exception $e) {
            throw new PaypalRequestError($e->getMessage());
        }
    }

    /**
     * @param StripeExecutionRequest $request
     *
     * @return \Stripe\Charge
     */
    public function charge(StripeExecutionRequest $request)
    {
        try {
            $customer = $this->customerRepo->findCustomerById(auth()->id());
            $stripeRepo = new StripeRepository($customer);

            $stripeRepo->execute(
                $request->all(),
                Cart::total(),
                Cart::tax()
            );

            return redirect()->route('checkout.success')->with('message', 'Stripe payment successful!');
        } catch (StripeChargingErrorException $e) {
            Log::info($e->getMessage());

            return redirect()->route('checkout.index')->with('error', 'There is a problem processing your request.');
        }
    }

    /**
     * Cancel page.
     *
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function cancel(Request $request)
    {
        return view('front.checkout-cancel', ['data' => $request->all()]);
    }

    /**
     * Success page.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function success()
    {
        return view('front.checkout-success');
    }

    /**
     * @param Customer   $customer
     * @param Collection $products
     *
     * @return mixed
     */
    private function createShippingProcess(Customer $customer, Collection $products)
    {
        $customerRepo = new CustomerRepository($customer);

        if ($customerRepo->findAddresses()->count() > 0 && $products->count() > 0) {
            $this->shippingRepo->setPickupAddress();
            $deliveryAddress = $customerRepo->findAddresses()->first();
            $this->shippingRepo->setDeliveryAddress($deliveryAddress);
            $this->shippingRepo->readyParcel($this->cartRepo->getCartItems());

            return $this->shippingRepo->readyShipment();
        }
    }

    public function coupon(Request $request)
    {
        // dd($products->sum('price'));
        if ($request->ajax()) {
            $user = Auth::user();
            $branch_zipcode = !empty($user) ? BranchZipcode::where('zipcode' , $user->zipcode)->first() : null ;
            $branch = !empty($branch_zipcode) ? Branch::find($branch_zipcode->branch_id) : null ;
            // dd($branch);
            $products = $this->cartRepo->getCartItems();
            $totalPrice = $products->sum('price');
            $status = [
                'status' => 'empty',
                'message' => 'ไม่พบคูปอง',
            ];
            if (!empty($request->coupon_code)) {
                $couponExist = Coupon::where('coupon_code', $request->coupon_code)
                                        ->where('status' , 1)
                                        ->first();

                if (!empty($couponExist)) {
                    $usedAll = CouponUsed::where([
                        'coupon_id' => $couponExist->id,
                    ])->count();

                    $branchCoupon = BranchCoupon::where('coupon_id' , $couponExist->id)->first();

                    if(!empty($branchCoupon)){
                        $branchCheck = BranchCoupon::where('branch_id' , $branch->id)->where('coupon_id', $couponExist->id)->first();
   
                        if(empty($branchCheck)){
                            $status['status'] = 'not branch';
                            $status['message'] = 'ไม่ได้อยู่ในสาขาที่กำหนด';

                            return response()->json($status);
                        }
                    }

                    $customer = $this->customerRepo->findCustomerById(auth()->id());
                    $usedOwn = CouponUsed::where([
                        'coupon_id' => $couponExist->id,
                        'customer_id' => $customer->id,
                    ])->count();
                    
                    if ($usedAll >= $couponExist->limit) {
                        $status['status'] = 'limit';
                        $status['message'] = 'คูปองนี้หมดแล้ว';

                        return response()->json($status);
                    }

                    $now = Carbon::now();
                    $start = Carbon::parse($couponExist->startDate);
                    $end = Carbon::parse($couponExist->expDate);

                    if($now > $end){
                        $status['status'] = 'expire';
                        $status['message'] = 'คูปองหมดอายุการใช้งานแล้ว';

                        return response()->json($status);
                    }

                    if($start > $now or $start == $now){
                        $status['status'] = 'not yet';
                        $status['message'] = 'ยังไม่สามารถใช้คูปองได้';

                        return response()->json($status);
                    }

                    if ($usedOwn > 0) {
                        $status['status'] = 'used';
                        $status['message'] = 'คุณใช้คูปองนี้ไปแล้ว';

                        return response()->json($status);
                    }

                    $total = (float) str_replace([','], '', Cart::total());
                    if ($total <= $couponExist->minimum) {
                        $status['status'] = 'minimum';
                        $status['message'] = 'คูปองนี้สามารถใช้ได้เมื่อมียอดสั่งซื้อมากกว่า '.$couponExist->minimum.' บาท';

                        return response()->json($status);
                    }

                    return response()->json([
                        'status' => 'can_use',
                        'coupon' => [
                            'coupon_code' => $couponExist['coupon_code'],
                            'value' => $couponExist['type'] === 'price' ? $couponExist['value'].' บาท' : $couponExist['value'].' %',
                            'discount' => $couponExist['type'] === 'price' ? $couponExist['value'] : number_format(($couponExist['value'] / 100) * $this->cartRepo->getTotal() ),
                        ],
                    ]);
                } else {
                    $status['status'] = 'empty';

                    return response()->json($status);
                    // no coupon
                    // return redirect()->route('checkout.index')->with('error', 'ไม่มี Coupon');
                }
            } else {
                $status['status'] = 'empty';

                return response()->json($status);
            }
        }

        // return view('front.coupon.index');
    }
}
