<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request, Totp $totp)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required'], 'code' => ['required', 'digits:6']]);
        $user = User::where('email', $data['email'])->where('is_super_admin', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->two_factor_confirmed_at || ! $totp->verify($user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['email' => 'ข้อมูลเข้าสู่ระบบหรือรหัส 2FA ไม่ถูกต้อง'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('admin_user_id', $user->id);

        return redirect()->route('admin.dashboard');
    }

    public function dashboard()
    {
        return view('admin.dashboard', [
            'shops' => Shop::withCount('inventoryItems')->latest()->limit(50)->get(),
            'plans' => SubscriptionPlan::orderBy('price_thb')->get(),
            'pendingPayments' => PaymentSubmission::where('status', 'pending_review')->with(['shop', 'plan'])->latest()->limit(20)->get(),
            'totals' => ['shops' => Shop::count(), 'items' => InventoryItem::count(), 'users' => User::where('is_super_admin', false)->count()],
        ]);
    }

    public function updateShop(Request $request, Shop $shop)
    {
        $data = $request->validate(['status' => ['required', 'in:trialing,pending_payment,active,grace_read_only,suspended,cancelled']]);
        $shop->update($data);

        return back()->with('message', 'อัปเดตร้านแล้ว');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $plan->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'active_inventory_limit' => ['required', 'integer', 'min:1'],
            'member_limit' => ['required', 'integer', 'min:1'],
            'price_thb' => ['required', 'numeric', 'min:0'],
        ]));

        return back()->with('message', 'อัปเดตแพ็กเกจแล้ว');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_user_id');
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
