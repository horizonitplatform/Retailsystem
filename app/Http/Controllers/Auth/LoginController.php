<?php

namespace App\Http\Controllers\Auth;

use App\Shop\Admins\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Shop\Customers\Customer;
use Response;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/accounts?tab=profile';

    /**
     * Create a new controller instance.
     *
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Login the admin
     *
     * @param LoginRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     * @throws \Illuminate\Validation\ValidationException
     */

    public function login(Request $request)
    {   
    
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:50',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        $user = Customer::where('email', $email)->first();

        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            Auth::loginUsingId($user->id, true);

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                ]);
            }

            return redirect('');
        }else if(Auth::attempt(['phone' => $email, 'password' => $password])){
            $phone = Customer::where('phone', $email)->first();
            Auth::loginUsingId($phone->id, true);

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                ]);
            }

            return redirect('');
        } else {

            if ($request->ajax()) {
                return response()->json([
                    'status' => false,
                    'errors' => [
                        'error' => ['***Email หรือ รหัสผ่านไม่ถูกต้อง***'],
                    ],
                ] , 404);
            }

            return redirect()->back()->with('errors', ['Email หรือรหัสผ่านไม่ถูกต้อง']);
        }
    }

    protected function credentials(Request $request)
    {
        if(is_numeric($request->get('email'))){
            return ['phone'=>$request->get('email'),'password'=>$request->get('password')];
        }
        elseif (filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
            return ['email' => $request->get('email'), 'password'=>$request->get('password')];
          }
          return ['username' => $request->get('email'), 'password'=>$request->get('password')];
    }

    public function authenticate()
    {
        $username = $request->email; //the input field has name='username' in form

        if(filter_var($username, FILTER_VALIDATE_EMAIL)) {
            //user sent their email 
            Auth::attempt(['email' => $username, 'password' => $password]);
        } else {
            //they sent their username instead 
            Auth::attempt(['phone' => $username, 'password' => $password]);
        }

        //was any of those correct ?
        if ( Auth::check() ) {
            //send them where they are going 
            return redirect()->intended('dashboard');
        }

        //Nope, something wrong during authentication 
        return redirect()->back()->withErrors([
            'credentials' => 'Please, check your credentials'
        ]);
    }

}
