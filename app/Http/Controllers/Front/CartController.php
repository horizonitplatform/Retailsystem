<?php

namespace App\Http\Controllers\Front;

use App\Shop\Carts\Requests\AddToCartRequest;
use App\Shop\Addresses\Repositories\Interfaces\AddressRepositoryInterface;
use App\Shop\Carts\Repositories\Interfaces\CartRepositoryInterface;
use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\ProductAttributes\Repositories\ProductAttributeRepositoryInterface;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Products\Transformations\ProductTransformable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Address;
use Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    use ProductTransformable;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepo;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepo;

    /**
     * @var CourierRepositoryInterface
     */
    private $courierRepo;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $productAttributeRepo;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepo;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * CartController constructor.
     *
     * @param CartRepositoryInterface             $cartRepository
     * @param ProductRepositoryInterface          $productRepository
     * @param CourierRepositoryInterface          $courierRepository
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     */
    public function __construct(
        AddressRepositoryInterface $addressRepository,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        CourierRepositoryInterface $courierRepository,
        ProductAttributeRepositoryInterface $productAttributeRepository
    ) {
        $this->cartRepo = $cartRepository;
        $this->productRepo = $productRepository;
        $this->courierRepo = $courierRepository;
        $this->productAttributeRepo = $productAttributeRepository;
        $this->addressRepo = $addressRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $courier = $this->courierRepo->findCourierById(request()->session()->get('courierId', 1));
        $shippingFee = $this->cartRepo->getShippingFee($courier);

        if (Auth::check()) {
            $user = Auth::user();
            if(empty($user->phone)){
                return redirect('update/social');
            }
            $addresses = Address::where('customer_id', Auth::user()->id)->whereNull('deleted_at')->first();
        } else {
            $addresses = Address::where('customer_id');
        }

        return view('front.carts.cart', [
            'addresses' => $addresses,
            'cartItems' => $this->cartRepo->getCartItemsTransformed(),
            'subtotal' => $this->cartRepo->getSubTotal(),
            'tax' => $this->cartRepo->getTax(),
            'shippingFee' => $shippingFee,
            'total' => $this->cartRepo->getTotal(2, $shippingFee),
        ]);
    }

    public function createAddress(Request $request)
    {
        $rules = [
            'address_1' => 'required|string|max:200',
            'alias' => 'required|string|max:50',
            // 'phone' => 'required|string|max:10|unique:customers,phone',
        ];

        $district = $request->district;
        $amphoe = $request->amphoe;
        $province = $request->province;
        $zipcode = $request->zipcode;
        $address_1 = $request->address_1;

        $validator = Validator::make($request->all(), $rules);

        if (!$validator->fails()) {
            if ($request->ajax()) {
                $request['customer_id'] = $request->user()->id;
                $request['status'] = 1;
                $request['address_1'] = $address_1.' '.$district. ' '.$amphoe.' '.$province;;
                $request['country_id'] = 211;
                $request['zip'] = $zipcode;
                $this->addressRepo->createAddress($request->except('_token', '_method'));

                return response()->json([
                    'status' => true,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'errors' => [
                        'error' => ['***ไม่สามารถเพิ่มที่อยู่ได้***'],
                    ],
                ], 404);
            }
        } else {
            return response()->json([
                'status' => false,
                'errors' => [
                    'error' => $validator->errors(),
                ],
            ], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AddToCartRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(AddToCartRequest $request)
    {
        
        $product = $this->productRepo->findProductById($request->input('product'));
        $qty = $request->quantity;
        $productRepo = new ProductRepository($product);
        $productQuantity = $productRepo->getQuantity(true);
        $options = [];
        
        if ($qty > $productQuantity) {
            if(!empty($request->has('checkout'))){
                return redirect()->back()->with('error', 'สินค้าไม่เพียงพอ');
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => [
                        'qty' => 'สินค้าไม่เพียงพอกรุณาเลือกสินค้าใหม่',
                    ],
                ], 500);
            }
        }

        if ($product->attributes()->count() > 0) {
            $productAttr = $product->attributes()->where('default', 1)->first();
            $options['UOM'] = $productAttr->attributesValues[0]->value;
            if (isset($productAttr->price)) {
                // $product->price = $productAttr->price;
                $product->price =   $productRepo->getPrice($options['UOM'], true);

                if (!is_null($productAttr->sale_price)) {
                    $product->sale_price = $productAttr->sale_price;
                }
            }

            if ($request->has('productAttribute')) {
                $attr = $this->productAttributeRepo->findProductAttributeById($request->input('productAttribute'));
                // $product->price = $attr->price;
                $options['UOM'] = $attr->attributesValues[0]->value;
                $product->price =   $productRepo->getPrice($options['UOM'], true);
            }
            $product->name .= ' ('.$options['UOM'].')';

        }else{
         $product->price = $productRepo->getPrice();
        }

        $this->cartRepo->addToCart($product, $request->input('quantity'), $options);

        if($request->has('buynow')){
            return response()->json([
                'status' => true,
                'count' => $this->cartRepo->countItems() , 
            ]);
        }else if($request->has('checkout')){
            return redirect()->route('checkout.index');
        
        }else{
            return redirect()->route('cart.index')
            ->with('message', 'Add to cart successful');
        }
        
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->cartRepo->updateQuantityInCart($id, $request->input('quantity'));

        request()->session()->flash('message', 'Update cart successful');

        return redirect()->route('cart.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->cartRepo->removeToCart($id);

        request()->session()->flash('message', 'Removed to cart successful');

        return redirect()->route('cart.index');
    }

    public function deleteCart(Request $request)
    {
        $id = $request->id;

        $this->cartRepo->removeToCart($id);
        // $this->cartRepo->clearCart();
        request()->session()->flash('message', 'Removed to cart successful');

        return response()->json([
            'status' => true,
        ], 200);
        // return redirect()->route('cart.index');
    }

    public function updateCartAll(Request $request)
    {
        $id = $request->id;
        $choice = $request->choice;
        $quantity = $request->quantity;
        for ($i = 0; $i < sizeof($id); ++$i) {
            $this->cartRepo->updateQuantityInCart($id[$i], $quantity[$i]);
        }

        if($choice == 1){
            return redirect('/cart');
        }else{
            return redirect()->route('checkout.index');
        }
    }
    
}
