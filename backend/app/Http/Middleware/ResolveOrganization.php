<?php

namespace App\Http\Middleware;

use App\Exceptions\WorkflowException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Текущая организация клиента из заголовка X-Organization.
 * Пускает, только если пользователь — активный участник этой организации.
 */
class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = (int) $request->header('X-Organization');
        $membership = $id > 0 ? $user->membershipIn($id) : null;
        if ($membership === null) {
            throw new WorkflowException('organization_required', 'Выберите организацию, от имени которой работаете.');
        }
        $request->attributes->set('organization', $membership->organization);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
