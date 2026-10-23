<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Show and list pages display numbers that other screens change (a Purchase Order's received
 * qty after a Goods Receipt, a Sales Order's delivered qty after a Delivery Receipt, stock...).
 * Browsers re-serve a stored copy of a page on Back/Forward, so the page kept showing the old
 * figures until a manual refresh. `no-store` makes them fetch it again.
 */
class NoStoreOnReadOnlyPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $routeName = (string) $request->route()?->getName();

        if ($request->isMethod('GET')
            && $request->user()
            && preg_match('/\.(show|index)$/', $routeName)
            && ! $response->headers->has('Content-Disposition')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $response;
    }
}
