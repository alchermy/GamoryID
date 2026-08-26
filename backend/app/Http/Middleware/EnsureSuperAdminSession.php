<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_user_id');
        abort_unless($adminId && User::whereKey($adminId)->where('is_super_admin', true)->exists(), 403);

        return $next($request);
    }
}
