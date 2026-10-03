<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SomentePlacas
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('staff')->user()?->podePlacas(), 403, 'Área restrita a quem cuida das placas.');

        return $next($request);
    }
}
