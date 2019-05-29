<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Support\Facades\DB;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/accounts';

    /**
     * Create a new controller instance.
     *
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function showResetForm($token)
    {
        $passwordReset = DB::table('password_resets')->where('token', $token)->first();
  
        if (empty($passwordReset)) {
            return view('auth.resetForm', ['component' => 'reset-wrong', 'title' => 'Link ลืมรหัสผ่านไม่ถูกต้อง']);
        }
       
        return view('auth.resetForm', [
            'component' => 'reset',
            'title' => 'สร้างหรัสผ่านใหม่',
            'passwordReset' => $passwordReset,
        ]);
    }

    protected function reset(Request $request)
    {
        $token = $request->input('token');
        $password = $request->input('password');
        $passwordRepeat = $request->input('password-confirmed');

        $passwordReset = DB::table('password_resets')->where('token', $token)->first();
       
        if (empty($passwordReset)) {
            return response()->json([
                'status' => false,
                'errors' => ['_error' => ['Link ลืมรหัสผ่านไม่ถูกต้อง']]
            ],404);
        }

        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ],404);
        }

        $user = Customer::where('email', $passwordReset->email)->first();
        $user->password = bcrypt($password);
        $user->save();

        return response()->json([
            'status' => true,
        ]);
    }
    protected function resetByOTP(Request $request)
    {
        $phone  = $request->input('phone');
        $password = $request->input('password');
        $passwordRepeat = $request->input('password-confirmed');


        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ],404);
        }

        $user = Customer::where('phone', $phone)->first();
        $user->password = bcrypt($password);
        $user->save();

        return response()->json([
            'status' => true,
        ]);
    }
    
}
