<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MethodOverride
{
    public function handle(Request $request, Closure $next): Response
    {
        $override = $request->header('X-HTTP-Method-Override');

        if ($override && $request->isMethod('POST')) {
            $override = strtoupper($override);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'])) {
                $request->setMethod($override);
            }
        }

        return $next($request);
    }
}
