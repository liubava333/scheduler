<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Closure;

class AdminMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->role === 'admin') {
            return $next($request);
        }

        // Если не админ — на главную с сообщением
        return redirect('/')->with('error', 'У вас нет прав доступа к этой странице.');
    }
}

