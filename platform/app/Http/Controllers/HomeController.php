<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        // if ($request->path() === "/") {
        //     return redirect()->route('dashboard');
        // }
        if (view()->exists($request->path())) {
            return view($request->path());
        }
        abort(404);
    }

    public function root()
    {
        return redirect()->route('dashboard');
    }


    /*Language Translation*/
    public function lang($locale)
    {
        if ($locale) {
            App::setLocale($locale);
            Session::put('lang', $locale);
            Session::save();
            return redirect()->back()->with('locale', $locale);
        } else {
            return redirect()->back();
        }
    }

    public function updateProfile(Request $request, $id)
    {
        $user = Auth::user();
        abort_unless($user && (int) $id === (int) $user->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($request->file('avatar')) {
            $avatar = $request->file('avatar');
            $avatarName = time() . '.' . $avatar->getClientOriginalExtension();
            $avatar->move(public_path('/images/'), $avatarName);
            $user->avatar = $avatarName;
        }

        $user->save();
        Session::flash('message', 'Profile updated successfully.');
        Session::flash('alert-class', 'alert-success');

        return redirect()->back();
    }

    public function updatePassword(Request $request, $id)
    {
        $user = Auth::user();
        abort_unless($user && (int) $id === (int) $user->id, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'isSuccess' => false,
                'Message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        Session::flash('message', 'Password updated successfully.');
        Session::flash('alert-class', 'alert-success');

        return response()->json([
            'isSuccess' => true,
            'Message' => 'Password updated successfully.',
        ]);
    }
}
