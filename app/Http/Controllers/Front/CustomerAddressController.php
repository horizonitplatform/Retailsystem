<?php

namespace App\Http\Controllers\Front;

use App\Shop\Addresses\Repositories\AddressRepository;
use App\Shop\Addresses\Repositories\Interfaces\AddressRepositoryInterface;
use App\Shop\Addresses\Requests\CreateAddressRequest;
use App\Shop\Addresses\Requests\UpdateAddressRequest;
use App\Shop\Cities\Repositories\Interfaces\CityRepositoryInterface;
use App\Shop\Countries\Repositories\Interfaces\CountryRepositoryInterface;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Shop\Provinces\Repositories\Interfaces\ProvinceRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Shop\Provinces\Province;
use Auth;
use App\Models\CustomerType;

class CustomerAddressController extends Controller
{
    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepo;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * @var CountryRepositoryInterface
     */
    private $countryRepo;

    /**
     * @var CityRepositoryInterface
     */
    private $cityRepo;

    /**
     * @var ProvinceRepositoryInterface
     */
    private $provinceRepo;

    public function __construct(
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository,
        CountryRepositoryInterface $countryRepository,
        CityRepositoryInterface $cityRepository,
        ProvinceRepositoryInterface $provinceRepository
    ) {
        $this->addressRepo = $addressRepository;
        $this->customerRepo = $customerRepository;
        $this->countryRepo = $countryRepository;
        $this->cityRepo = $cityRepository;
        $this->provinceRepo = $provinceRepository;
    }

    /**
     * @param int $customerId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($customerId)
    {
        $customer = $this->customerRepo->findCustomerById($customerId);

        return view('front.customers.addresses.list', [
            'customer' => $customer,
            'addresses' => $customer->addresses
        ]);
    }

    /**
     * @param int $customerId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create($customerId)
    {
        $countries = $this->countryRepo->listCountries();

        return view('front.customers.addresses.create', [
            'customer' => $this->customerRepo->findCustomerById($customerId),
            'countries' => $countries,
            'cities' => $this->cityRepo->listCities(),
            'provinces' => $this->provinceRepo->listProvinces()
        ]);
    }

    /**
     * @param CreateAddressRequest $request
     * @param int $customerId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateAddressRequest $request, $customerId)
    {
        $request['customer_id'] = $request->user()->id;
        $this->addressRepo->createAddress($request->except('_token', '_method'));

        return redirect()->route('accounts', ['tab' => 'address'])
            ->with('message', 'Address creation successful');
    }

    /**
     * @param $customerId
     * @param $addressId
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($customerId, $addressId)
    {
        $countries = $this->countryRepo->listCountries();

        return view('front.customers.addresses.edit', [
            'customer' => $this->customerRepo->findCustomerById($customerId),
            'address' => $this->addressRepo->findAddressById($addressId),
            'countries' => $countries,
            'cities' => $this->cityRepo->listCities(),
            'provinces' => $this->provinceRepo->listProvinces()
        ]);
    }

    /**
     * @param UpdateAddressRequest $request
     * @param $customerId
     * @param $addressId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateAddressRequest $request, $customerId, $addressId)
    {
        $address = $this->addressRepo->findAddressById($addressId);

        $addressRepo = new AddressRepository($address);
        $request['customer'] = $customerId;
        $addressRepo->updateAddress($request->except('_token', '_method'));

        return redirect()->route('accounts', ['tab' => 'address'])
            ->with('message', 'Address update successful');
    }

    /**
     * @param $customerId
     * @param $addressId
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy($customerId, $addressId)
    {
        $address = $this->addressRepo->findAddressById($addressId);
        $address->delete();

        // return redirect()->route('customer.address.index', $customerId)
        //     ->with('message', 'Address delete successful');
        return redirect('/accounts')->with('message', 'Address delete successful');
    }

    public function createAddress(Request $request)
    {   
        $rules = [
            'address_1' => 'required|string|max:200',
            'alias' => 'required|string|max:50',
            // 'phone' => 'required|string|max:10|unique:customers,phone',
        ];
        
        $district = $request->district ;
        $amphoe = $request->amphoe;
        $province =$request->province;
        $zipcode = $request->zipcode;
        $address_1 = $request->address_1 ;

        $validator = Validator::make($request->all(), $rules);
        
        if (!$validator->fails()) {
            if ($request->ajax()) {
                $request['customer_id'] = $request->user()->id;
                $request['status'] = 1;
                $request['address_1'] = $address_1 . " " .  $district . " " . $amphoe . " " . $province . " " . $zipcode;
                $request['country_id'] = 211;
                $request['zip'] = $zipcode;
                $this->addressRepo->createAddress($request->except('_token', '_method'));
                return response()->json([
                    'status' => true,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => [
                        'error' => ['***ไม่สามารถเพิ่มที่อยู่ได้***'],
                    ],
                ] , 404);
            }
        }else{
            return response()->json([
                'status' => false,
                'errors' => [
                    'error' => $validator->errors(),
                ],
            ] , 404);
        }
    }

    public function updateAddress(Request $request)
    {   
        
        $rules = [
            'address_1' => 'required|string|max:200',
            'alias' => 'required|string|max:50',
            'phone' => 'required|string|max:10|',
        ];

        $addressId = $request->id;
        $address_1 = $request->address_1;
        $validator = Validator::make($request->all(), $rules);
        if (!$validator->fails()) {
            if ($request->ajax()) {
                $address = $this->addressRepo->findAddressById($addressId);
                $addressRepo = new AddressRepository($address);
                $request['customer_id'] = $request->user()->id;
                $request['zip'] = $request['zipcode'];
                $request['address_1'] = $address_1 . " " .  $request['district'] . " " . $request['amphoe'] . " " . $request['province']  . " " . $request['zipcode'];
                $addressRepo->updateAddress($request->except('_token', '_method'));
                
                if($request['index'] == 0){
                    $user = Customer::where('id' , $request->user()->id)->first();
                    $user->zipcode = $request['zipcode'];
                    $user->save();
                }
                return response()->json([
                    'status' => true,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => [
                        'error' => ['***ไม่สามารถเพิ่มที่อยู่ได้***'],
                    ],
                ] , 404);
            }
        }else{
            return response()->json([
                'status' => false,
                'errors' => [
                    'error' => $validator->errors(),
                ],
            ] , 404);
        }
    }

    public function updateTypeShop(Request $request)
    { 
        $user = Auth::user();
        $data = $request->all();
        $customerType = CustomerType::where('customer_id' ,$user->id )->first();
        
        if($data['type'] == 1 ){
            $choice = 1 ; 
        }else{
            if(!empty($data['type-choice']) ){
                $choice = $data['type-choice']; 
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => "ยังไม่ระบุประเภทของร้านค้า",
                ],404);
            }
        }
    
        if(empty($customerType) and $user->type_active == 0 ){
            $user->type_active = 1 ; 
            $user->save();

            $pickType = new CustomerType;
            $pickType->customer_id = $user->id;
            $pickType->type_id =  $choice ;
            $pickType->save();
        }else{
            $user->type_active = 1 ; 
            $user->save();

            $customerType->customer_id = $user->id;
            $customerType->type_id =  $choice ;
            $customerType->save();

        }

        return response()->json([
            'status' => true,
        ] , 200);

    }
}
