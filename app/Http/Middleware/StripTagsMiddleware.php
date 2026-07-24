<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StripTagsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        $request->replace($this->stripTagsFromArray($input));

        return $next($request);
    }

    private function stripTagsFromArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->stripTagsFromArray($value);
            } elseif (is_string($value)) {
                $result[$key] = strip_tags($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
