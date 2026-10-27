<?php

namespace App\Http\Middleware;

use App\Exceptions\WorkflowException;
use App\Models\Region;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Регион из заголовка X-Region (код региона). Доступен как $request->attributes->get('region'). */
class ResolveRegion
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->header('X-Region');
        $region = $code ? Region::query()->where('code', $code)->where('is_active', true)->first() : null;
        if ($region === null) {
            throw new WorkflowException('region_required', 'Выберите город.');
        }
        $request->attributes->set('region', $region);

        return $next($request);
    }
}
