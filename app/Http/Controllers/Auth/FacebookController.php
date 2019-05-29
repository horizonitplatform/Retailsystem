<?php


namespace App\Http\Controllers\Auth;


use App\Shop\Customers\Customer;
use App\Http\Controllers\Controller;
use App\Verify;
use Illuminate\Http\Request;
use Socialite;
use Exception;
use Auth;
use Hash;
use App\Models\Address;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FacebookController extends Controller
{


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function redirectToProvider()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function handleFacebookCallback()
    {
        try {
            $user = Socialite::driver('facebook')->stateless()->user();
            // dd($user);
            $existingUser = Customer::where('social_id', $user->id)->first();
            if($existingUser){
                auth()->login($existingUser, true);
                if(empty($existingUser['phone']) || empty($existingUser['email'])){
                    return redirect('update/social');
                }
                return redirect('/');
            } else {
                $create['name'] = $user->getName();
                $create['email'] = $user->getEmail();
                $create['social_id'] = $user->getId();
                $create['social_type'] = 'facebook';
                $create['status'] = 0;
                $create['password'] = Hash::make(rand());
                
                $userModel = new Customer;
                try{
                    $createdUser = $userModel->addNew($create);
                    auth()->login($createdUser, true);
                }catch (Exception $e){
                    $oldUser = new Customer;
                    if(!empty($create['email']) && !is_null($create['email'])){
                        $oldAccount = $oldUser->where([
                            'email' => $create['email']
                        ])->first();
                        if($oldAccount){
                            $create['email'] = null;
                            $createdUser = $userModel->addNew($create);
                            auth()->login($createdUser, true);
                        }
                    }
                }
                return redirect('update/social');
            }
        } catch (Exception $e) {
            dd($e);
            return redirect('auth/facebook');
        }
    }

    public function handleGoogleCallback()
    {
        try {
            $user = Socialite::driver('google')->stateless()->user();

            $existingUsergoogle = Customer::where('social_id', $user->id)->first();
            if($existingUsergoogle){
                auth()->login($existingUsergoogle, true);
                if(empty($existingUsergoogle['phone']) || empty($existingUsergoogle['email'])){
                    return redirect('update/social');
                }
                return redirect('/');
            } else {
                $create['name'] = $user->getName();
                $create['email'] = $user->getEmail();
                $create['social_id'] = $user->getId();
                $create['social_type'] = 'google';
                $create['status'] = 0;
                $create['password'] = Hash::make(rand());
    
                $userModel = new Customer;
                try{
                    $createdUser = $userModel->addNew($create);
                    auth()->login($createdUser, true);
                }catch (Exception $e){
                    $oldUser = new Customer;
                    if(!empty($create['email']) && !is_null($create['email'])){
                        $oldAccount = $oldUser->where([
                            'email' => $create['email']
                        ])->first();
                        if($oldAccount){
                            $create['email'] = null;
                            $createdUser = $userModel->addNew($create);
                            auth()->login($createdUser, true);
                        }
                    }
                }
                return redirect('update/social');
            }

        } catch (Exception $e) {
            return redirect('auth/facebook');
        }
    }

    public function showInfoSocial()
    {
        if (!Auth::check()) {
            return redirect('/');
        }
        $user = Auth::user(); 
        $name = $user->name ;
        $email = $user->email;
        if(!empty($user->phone) && !empty($user->email) ){
            return redirect('/');
        }
        return view('frontend.social.update-social' , [
                    'name' => $name, 
                    'email' => $email,
        ]);
    }

    public function updateSocial(Request $request)
    {
        $data = $request->all();
        $phone_number =  "66" . substr($data['phone'] , 1);

        $verify = Verify::where('phone_number',  $phone_number)->first(); 
        
        if(empty($verify->verify_code)){
            return response()->json([
                'status' => false,
                'errors' => ['verify' => 'กรุณาขอ รหัสSMS ยืนยันตัวตนใหม่'],
            ],404);
        }

        if($verify->verify_code !==  $data['verify']){
            return response()->json([
                'status' => false,
                'errors' => ['verify' => 'รหัสยืนยันตัวตนไม่ถูกต้อง'],
            ],404);
        }
        
        $user = Auth::user();
        $rules = [
            'name' => 'required|string|max:50',
            'phone' => 'required|string|max:10',
            'email' =>  'required|string|email|max:50',

        ];

        $validator = Validator::make($data, $rules);

        if (!$validator->fails()) {

            $oldUserEmail = new Customer;
            $oldUserPhone = new Customer;
            $oldAccounteEmail = $oldUserEmail->where([
                'email' => $data['email'],
            ])->where('id','!=',$user->id)->first();

            $oldAccountePhone = $oldUserPhone->where([
                'email' => $data['email'],
            ])->where('id','!=',$user->id)->first();

            if($oldAccounteEmail){
                return response()->json([
                    'status' => false,
                    'errors' => ['_error' => 'ไม่สามารถบันทึกข้อมูลได้ อีเมลล์นี้มีการใช้งานแล้ว'],
                ],404);
            }

            if($oldAccountePhone){
                return response()->json([
                    'status' => false,
                    'errors' => ['_error' => 'ไม่สามารถบันทึกข้อมูลได้ เบอร์โทรศัพทร์นี้มีการใช้งานแล้ว'],
                ],404);
            }

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->phone = $data['phone'];
            $user->zipcode = $data['zipcode'];
            if ($user->save()) {
                $address = new Address;
                $address->alias = $data['name'];
                $address->address_1 = $data['address_1']  . " " . $data['district']  . " "  . $data['amphoe']  . " " . $data['province'] . " "  . $data['zipcode'];
                $address->country_id = 211;
                $address->customer_id = $user->id;
                $address->zip = $data['zipcode'];
                $address->phone = $user->phone;
                $address->status = 1;
                $address->save();
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
    
}