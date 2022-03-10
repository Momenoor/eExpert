<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use \Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

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
    protected $redirectTo = RouteServiceProvider::HOME;


    private $username = 'name';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->username();
    }

    protected function validateLogin(Request $request)
    {
        $extraRule = null;
        $username = $request->name;

        if ($request->isEmail($username)) {

            $this->username = 'email';
            $request->merge([$this->username => $username]);
            $extraRule = '|email';
        }

        $request->validate([
            $this->username => 'required|string' . $extraRule,
            'password' => 'required|string',
        ]);
    }

    public function username()
    {
        return $this->username;
    }
}