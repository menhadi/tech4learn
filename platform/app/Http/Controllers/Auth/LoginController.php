<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Support\AttendanceBridge;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function username()
    {
        $login = request()->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        request()->merge([$field => $login]);
        return $field;
    }

    protected function credentials(Request $request)
    {
        return $request->only($this->username(), 'password');
    }

    protected function attemptLogin(Request $request)
    {
        if (!config('attendance.api_url')) {
            return $this->guard()->attempt($this->credentials($request), $request->boolean('remember'));
        }
        try {
            $user=app(AttendanceBridge::class)->authenticate($request);
            $this->guard()->login($user,false);
            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            if (in_array($error->getStatusCode(),[401,403,404],true)) { return false; }
            throw $error;
        }
    }

    protected function validateLogin(Request $request)
    {
        if (config('attendance.api_url')) {
            $request->validate(['login'=>['required','string','email'],'password'=>['required','string']]);
        } else {
            $request->validate([$this->username()=>['required','string'],'password'=>['required','string']]);
        }
    }

    public function logout(Request $request)
    {
        app(AttendanceBridge::class)->signOut($request);
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
