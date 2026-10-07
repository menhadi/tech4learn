<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);
        foreach ($guards as $guard) {
            if (in_array($guard, ['student', 'student-api'], true) && $this->auth->guard($guard)->check()) {
                $student = $this->auth->guard($guard)->user();
                abort_unless(\App\Models\Student::whereKey($student->id)->where('status', 'Active')->exists(), 403);
                break;
            }
        }
    }

    protected function redirectTo($request)
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->is('student/*')) {
            return route('student.signin');
        }

        return route('login');
    }
}
