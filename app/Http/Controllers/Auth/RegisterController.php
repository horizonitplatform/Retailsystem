<?php

namespace App\Http\Controllers\Auth;

use App\Shop\Customers\Customer;
use App\Verify;
use App\Http\Controllers\Controller;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Shop\Customers\Requests\CreateCustomerRequest;
use App\Shop\Customers\Requests\RegisterCustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Carbon\Carbon;
use App\Models\Address;
use App\Models\CustomerType;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/accounts';

    private $customerRepo;

    /**
     * Create a new controller instance.
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->middleware('guest');
        // $this->customerRepo = $customerRepository;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return Customer
     */

    // protected function create(array $data)
    // {   
    //     return $this->customerRepo->createCustomer($data);
    // }

    /**
     * @param RegisterCustomerRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function register(Request $request)
    {   
        // $customer = $this->create($request->except('_method', '_token'));
        // Auth::login($customer);
    
        // return redirect()->route('accounts');

        $data = $request->all();
        $phone_number =  "66" . substr($data['phone'] , 1);

        $verify = Verify::where('phone_number',  $phone_number)->first(); 

        if(empty($verify->verify_code)){
            return response()->json([
                'status' => false,
                'errors' => ['verify' => 'กรุณาขอ รหัสSMS ยืนยันตัวตนใหม่'],
            ],404);
        }
       
        if($verify->verify_code !==  $data['verify'] ){
            
            return response()->json([
                'status' => false,
                'errors' => ['verify' => 'รหัสยืนยันตัวตนไม่ถูกต้อง'],
            ],404);
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:50',
            'zipcode' => 'required|string|max:5|min:5',
            'phone' => 'nullable|string|max:10|unique:customers,phone',
            'email' => 'nullable|string|email|max:50|unique:customers,email',
            'password' => 'required|string|confirmed|min:6',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ],404);
            }

            return redirect()->back();
        }

        // $emailToken = encrypt($data['email'] + time());
       
        if($data['type'] == 1 ){
            $choice = 1 ; 
        }else{
            if(!empty($data['type-choice']) ){
                $choice = $data['type-choice']; 
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => ['type' => "ยังไม่ระบุประเภทของร้านค้า"],
                ],404);
            }
        }

        $user = Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'zipcode' => $data['zipcode'],
            'role' => 'user',
            'type_active' => "1" ,
        ]);

        $address = new Address;
        $address->alias = $data['name'];
        $address->address_1 = $data['address_1']  . " " . $data['district']  . " "  . $data['amphoe']  . " " . $data['province'] . " "  . $data['zipcode'];
        $address->country_id = 211;
        $address->customer_id = $user->id;
        $address->zip = $data['zipcode'];
        $address->phone = $user->phone;
        $address->status = 1;
        $address->save();

        

        $pickType = new CustomerType;
        $pickType->customer_id = $user->id;
        $pickType->type_id =  $choice ;
        $pickType->save();
       

        Auth::login($user);
        if ($request->ajax()) {
            return response()->json([
                'status' => true,
            ]);
        }
        
        return redirect('');

    }

    public function sendSMS(Request $request)
    { 
        $checkVerify = Verify::where('phone_number' , $request->phone)->first();

        $customer = new \App\Models\Customer();
        $phone = false;
        if($checkVerify && strpos($checkVerify['phone_number'],'66') === 0){
            $phone = str_replace('66','0',$checkVerify['phone_number']);
        }else if($checkVerify){
            $phone = $checkVerify['phone_number'];
        }

        if($phone){
            $oldCustomer = $customer->where([
                'phone' => $phone
            ])->first();


            $customer = $request->user();

            if($oldCustomer){
                if($oldCustomer['id'] !== $customer['id']){
                    return response()->json([
                        'status' => false,
                        'errors' =>['repeat' => 'เบอร์โทรศัพทร์นี้มีการใช้งานแล้ว'],
                    ],404);
                }
            }
        }

        if(!empty($checkVerify)){
            $now = Carbon::now();
            $checkVerifyTime = $now->diffInMinutes($checkVerify->updated_at);

            if($checkVerifyTime <= 4){
                $newVerifyTime = $checkVerify->updated_at->addMinutes(5)->format('H:i');
                return response()->json([
                    'status' => false,
                    'errors' => ['repeat' => 'หมายเลขนี้มีการขอรหัสยืนยันตัวตนแล้ว สามารถขอขอใหม่ได้อีกครั้งในเวลา ' . $newVerifyTime],
                ],404);
            }else{
                $checkVerify->verify_code = $request->verify;
                if ($checkVerify->save()) {
                    return response()->json([
                        'status' => true,
                    ] ,200);
                }
            }     
        }else{
            $verify = new Verify ; 
            $verify->phone_number = $request->phone ; 
            $verify->verify_code = $request->verify;

            if ($verify->save()) {
                return response()->json([
                    'status' => true,
                ] ,200);
            }else {
                return response()->json([
                    'status' => false,
                    'errors' => ['_error' => 'ไม่สามารถบันทึกข้อมูลได้ หรือข้อมูลไม่มีการเปลี่ยนแปลง'],
                ],404);
            }
        }
        
    }
}
