<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Obtener la representación en string del rol (soporta string simple o BackedEnum)
        $userRole = is_object($user->role) && isset($user->role->value)
            ? $user->role->value
            : (string) $user->role;

        // Si la ruta requiere 'admission', incluir 'admissionist' por compatibilidad
        if (in_array('admission', $roles, true) && !in_array('admissionist', $roles, true)) {
            $roles[] = 'admissionist';
        }

        // Si la ruta requiere 'admissionist', incluir 'admission' por compatibilidad
        if (in_array('admissionist', $roles, true) && !in_array('admission', $roles, true)) {
            $roles[] = 'admission';
        }

        // Administradores tienen acceso global; de lo contrario se valida contra los roles permitidos
        if ($userRole === 'admin' || in_array($userRole, $roles, true)) {
            return $next($request);
        }

        abort(403, 'No tienes permisos para acceder a este recurso.');
    }
}