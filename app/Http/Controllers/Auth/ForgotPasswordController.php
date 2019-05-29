<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordLink;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Message\Response;
use App\Models\VerifyPasswordReset;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function sendResetLinkEmail(Request $request)
    {   
        $email = $request->input('email');
        $token = str_random(64);
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email'
        ]);

        $user = Customer::where('email', $email)->first();

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ],404);
            }
        }

        if (empty($user)) {
            return response()->json([
                'status' => false,
                'errors' => ['email' => ['ไม่พบอีเมลนี้ในระบบ']],
            ]);
        }
        
        
        $passwordReset = new PasswordReset;
        $passwordReset->email = $email;
        $passwordReset->token = $token;
        $passwordReset->created_at = Carbon::now();
        $passwordReset->save();

       
        // $message = (new ResetPasswordLink($passwordReset))
        //     ->onConnection('database')
        //     ->onQueue('emails');
        // Mail::to($email)->queue($message);
        Mail::to($email)->queue(new ResetPasswordLink($passwordReset));

        return response()->json([
            'status' => true,
        ]);
    }
    protected function sendOTP(Request $request)
    {   
        $phone = $request->input('phone');
     
        $validator = Validator::make(['phone' => $phone], [
            'phone' => 'required|string|max:10|',
        ]);

        $user = Customer::where('phone', $phone)->first();
        
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ],404);
            }
        }

        if(!empty($user)){
             //save to db
            $phoneAPI = "66" . substr($request->input('phone'), 1);
            $verifyAPI = random_int(100000 , 999999);
             $checkVerify = VerifyPasswordReset::where('phone_number' , $phoneAPI )->first();
             if(!empty($checkVerify)){
                 $now = Carbon::now();
                 $checkVerifyTime = $now->diffInMinutes($checkVerify->updated_at);
                 if($checkVerifyTime < 5){
                     $newVerifyTime = $checkVerify->updated_at->addMinutes(5)->format('H:i');
                     return response()->json([
                         'status' => false,
                         'errors' => ['repeat' => 'หมายเลขนี้มีการขอรหัสยืนยันตัวตนแล้ว สามารถขอขอใหม่ได้อีกครั้งในเวลา ' . $newVerifyTime],
                     ],404);
                 }else{
                     $checkVerify->verify_code = $verifyAPI;
                     $checkVerify->save();
                    //  if ($checkVerify->save()) {
                    //      return response()->json([
                    //          'status' => true,
                    //      ] ,200);
                    //  }
                 }   
             }else{
                 $verify = new VerifyPasswordReset;
                 $verify->phone_number = $phoneAPI;
                 $verify->verify_code = $verifyAPI;
                 $verify->save();
             }

            $client = new Client();
            $request = $client->post('https://api2.ants.co.th/sms/1/text/single', [
                'headers' => [
                    'contentType' => 'application/json',
                    'Authorization' => 'Basic SG9yaXpvbnRAOkhAejU2OTg4=',
                ],
                'json'    => [ "form" => "Horizont" ,"to" => $phoneAPI , "text"=> 'รหัส OTP สำหรับเปลี่ยนรหัสผ่าน : '. $verifyAPI ],
            ]);

            $response = $request->getBody()->getContents();
            $status =  $request->getStatusCode();

            if($status == '200'){
                return response()->json([
                    'status' => true,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => ['errorAPI' => 'กรุณาขอรหัส OTP ใหม่'],
                ],404);
            }
            
        }else{
            return response()->json([
                'status' => false,
                'errors' => ['phone' => 'ไม่พบหมายเลขโทรศัพท์นี้ในระบบ'],
            ],404);
        }

    }

    protected function checkOTP(Request $request)
    {   
        $phone = $request->phone;
        $verify = $request->verify;
        $phoneAPI = "66" . substr($phone, 1);
        $user = Customer::where('phone', $phone)->first();
       
        if(!empty($user)){
            $checkVerify = VerifyPasswordReset::where('phone_number' , $phoneAPI )
                                                ->where('verify_code' ,$verify )
                                                ->first();
            if(!empty($checkVerify)){
                return response()->json([
                    'status' => true,
                    'phone' => $phone,
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'errors' => ['errorAPI' => 'รหัส OTP ไม่ถูกต้อง'],
                ],404);
            }
        }else{
            return response()->json([
                'status' => false,
                'errors' => ['phone' => 'ไม่พบหมายเลขโทรศัพท์นี้ในระบบ'],
            ],404);
        }

    }
}
