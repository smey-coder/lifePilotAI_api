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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if(!$request->user()){
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        // Get user roles
        $userRoles = $request->user()
        ->roles()
        ->pluck('name')
        ->toArray();

        // Check if user has allowed role
        foreach($roles as $role){
            if(in_array($role, $userRoles)){
                return $next($request);
            }
        }
        return response()->json([
            'message' => 'Forbidden'
        ], 403);
    }
}
