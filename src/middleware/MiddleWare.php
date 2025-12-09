<?php

namespace App\Middleware;

class MiddleWare
{
    /**
     * Handle the incoming request
     *
     * @param mixed $request
     * @param callable $next
     * @return mixed
     */
    public function handle($request, callable $next)
    {
        // Pre-processing logic here
        
        // Call the next middleware/controller
        $response = $next($request);
        
        // Post-processing logic here
        
        return $response;
    }
    
    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return isset($_SESSION['user_id']) ?? false;
    }
    
    /**
     * Check if user has required role
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
}