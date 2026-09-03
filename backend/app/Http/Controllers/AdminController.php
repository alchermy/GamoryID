<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CreditTransaction;
use App\Models\InventoryItem;
use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentReviewedNotification;
use App\Services\CreditWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $user = User::where('email', $data['email'])->where('is_super_admin', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->recordAdminLog($request, null, 'admin.login_failed', null, ['email' => $data['email']]);

            return back()->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('admin_user_id', $user->id);
        $this->recordAdminLog($request, null, 'admin.logged_in', $user, ['email' => $user->email]);

        return redirect()->route('admin.dashboard');
    }

    public function dashboard()
    {
        return $this->page('dashboard', 'Dashboard', [
            'recentTopUps' => PaymentSubmission::whereNotNull('credit_amount')->with('shop')->latest()->limit(6)->get(),
            'recentLogs' => ActivityLog::with(['shop', 'user'])->latest('created_at')->limit(8)->get(),
        ]);
    }

    public function shops(Request $request)
    {
        $query = trim((string) $request->query('q'));
        $shops = Shop::withTrashed()
            ->with(['latestSubscription.plan'])
            ->withCount(['users as staff_count' => fn ($builder) => $builder->where('shop_members.role', 'staff')])
            ->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('name', 'like', "%{$query}%")->orWhere('slug', 'like', "%{$query}%")))
            ->orderByRaw('deleted_at is not null')
            ->latest()->paginate(25)->withQueryString();

        return $this->page('shops', 'จัดการร้านค้า', compact('shops', 'query'));
    }

    public function createShop()
    {
        return $this->page('shop-create', 'เพิ่มร้านค้า');
    }

    public function storeShop(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:120', 'unique:shops,slug'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $shop = DB::transaction(function () use ($data) {
            $trialEndsAt = now()->addDays((int) $data['trial_days']);
            $shop = Shop::create([
                'name' => $data['name'],
                'slug' => Str::lower($data['slug']),
                'status' => 'trialing',
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => $trialEndsAt->copy()->addDays(14),
            ]);
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => Str::lower($data['owner_email']),
                'password' => $data['password'],
                'current_shop_id' => $shop->id,
                'email_verified_at' => now(),
            ]);
            ShopMember::create([
                'shop_id' => $shop->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'permissions' => [],
                'joined_at' => now(),
            ]);
            Subscription::create([
                'shop_id' => $shop->id,
                'status' => 'trialing',
                'starts_at' => now(),
                'ends_at' => $trialEndsAt,
                'grace_ends_at' => $trialEndsAt->copy()->addDays(14),
            ]);

            return $shop;
        });

        $this->recordAdminLog($request, $shop, 'shop.created', $shop, ['slug' => $shop->slug]);

        return redirect()->route('admin.shops.show', $shop)->with('message', 'สร้างร้านค้าและบัญชีเจ้าของร้านแล้ว');
    }

    public function showShop(Shop $shop)
    {
        $shop->load(['users' => fn ($builder) => $builder->orderBy('shop_members.role')->orderBy('users.name'), 'latestSubscription.plan']);
        $subscriptions = $shop->subscriptions()->with('plan')->latest('created_at')->paginate(10, ['*'], 'subscriptions_page')->withQueryString();
        $topUps = $shop->paymentSubmissions()->whereNotNull('credit_amount')->with('submittedBy')->latest()->paginate(10, ['*'], 'topups_page')->withQueryString();
        $creditTransactions = $shop->creditTransactions()->with('plan')->latest()->limit(12)->get();
        $directoryListings = $shop->inventoryItems()
            ->where('status', 'available')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'tag', 'title', 'list_price', 'hidden_from_directory']);

        return $this->page('shop-show', $shop->name, compact('shop', 'subscriptions', 'topUps', 'creditTransactions', 'directoryListings'));
    }

    public function editShop(Shop $shop)
    {
        abort_if($shop->trashed(), 409, 'ร้านค้านี้ถูกเก็บถาวรแล้ว');

        return $this->page('shop-edit', 'แก้ไข '.$shop->name, compact('shop'));
    }

    public function plans()
    {
        return $this->page('plans', 'จัดการแพ็กเกจ', ['plans' => SubscriptionPlan::orderBy('sort_order')->orderBy('price_monthly')->get()]);
    }

    public function createPlan()
    {
        return $this->page('plan-create', 'เพิ่มแพ็กเกจ');
    }

    public function editPlan(SubscriptionPlan $plan)
    {
        return $this->page('plan-edit', 'แก้ไขแพ็กเกจ '.$plan->name, compact('plan'));
    }

    public function topUps(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'pending_review', 'verified', 'rejected'])],
        ]);
        $query = trim((string) ($filters['q'] ?? ''));
        $date = (string) ($filters['date'] ?? '');
        $status = (string) ($filters['status'] ?? 'all');
        $topUps = PaymentSubmission::whereNotNull('credit_amount')->with(['shop', 'submittedBy'])
            ->when($query !== '', fn ($builder) => $builder->whereHas('shop', fn ($shop) => $shop->where('name', 'like', "%{$query}%")->orWhere('slug', 'like', "%{$query}%")))
            ->when($date !== '', fn ($builder) => $builder->whereDate('created_at', $date))
            ->when($status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->latest()->paginate(25)->withQueryString();

        return $this->page('top-ups', 'รายการเติมเครดิต', compact('topUps', 'query', 'date', 'status'));
    }

    public function showTopUp(PaymentSubmission $payment)
    {
        abort_unless($payment->credit_amount, 404);
        $payment->load(['shop', 'submittedBy', 'plan']);

        return $this->page('top-up-show', 'รายละเอียดเติมเครดิต #'.$payment->id, compact('payment'));
    }

    public function logs(Request $request)
    {
        $query = trim((string) $request->query('q'));
        $logs = ActivityLog::with(['shop', 'user'])
            ->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('event', 'like', "%{$query}%")->orWhereHas('shop', fn ($shop) => $shop->where('name', 'like', "%{$query}%"))))
            ->latest('created_at')->paginate(50)->withQueryString();

        return $this->page('logs', 'Log การทำรายการ', compact('logs', 'query'));
    }

    public function updateShop(Request $request, Shop $shop)
    {
        abort_if($shop->trashed(), 409, 'ร้านค้านี้ถูกเก็บถาวรแล้ว');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('shops', 'slug')->ignore($shop->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'line_url' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in(['trialing', 'pending_payment', 'active', 'grace_read_only', 'suspended', 'cancelled'])],
            'hidden_from_directory' => ['sometimes', 'boolean'],
        ]);
        $data['hidden_from_directory'] = $request->boolean('hidden_from_directory');
        $before = $shop->only(['name', 'slug', 'status', 'hidden_from_directory']);
        $data['slug'] = Str::lower($data['slug']);
        $shop->update($data);
        $this->recordAdminLog($request, $shop, 'shop.updated', $shop, ['before' => $before, 'after' => $shop->only(['name', 'slug', 'status', 'hidden_from_directory'])]);

        return redirect()->route('admin.shops.show', $shop)->with('message', 'บันทึกข้อมูลร้านค้าแล้ว');
    }

    public function destroyShop(Request $request, Shop $shop)
    {
        DB::transaction(function () use ($request, $shop) {
            $shop->update(['status' => 'suspended']);
            $this->recordAdminLog($request, $shop, 'shop.archived', $shop, ['slug' => $shop->slug]);
            $shop->delete();
        });

        return redirect()->route('admin.shops.index')->with('message', 'เก็บร้านค้าไว้ในรายการถาวรแล้ว ประวัติและข้อมูลทางการเงินยังคงอยู่');
    }

    public function restoreShop(Request $request, Shop $shop)
    {
        abort_unless($shop->trashed(), 404);
        $shop->restore();
        $this->recordAdminLog($request, $shop, 'shop.restored', $shop, ['status' => $shop->status]);

        return redirect()->route('admin.shops.show', $shop)->with('message', 'กู้คืนร้านค้าแล้ว ร้านยังอยู่ในสถานะระงับจนกว่าจะเปิดใช้งาน');
    }

    public function updateAutoRenew(Request $request, Shop $shop)
    {
        abort_if($shop->trashed(), 409, 'ร้านค้านี้ถูกเก็บถาวรแล้ว');
        $data = $request->validate(['auto_renew' => ['required', 'boolean']]);
        $subscription = $shop->subscriptions()
            ->whereIn('status', ['trialing', 'active', 'grace_read_only'])
            ->latest('created_at')->first();
        if (! $subscription) {
            return back()->withErrors(['auto_renew' => 'ร้านค้านี้ยังไม่มีแพ็กเกจปัจจุบัน จึงตั้งค่าต่ออายุอัตโนมัติไม่ได้']);
        }

        $before = $subscription->auto_renew;
        $subscription->update(['auto_renew' => (bool) $data['auto_renew']]);
        $this->recordAdminLog($request, $shop, 'subscription.auto_renew_updated', $subscription, ['before' => $before, 'after' => $subscription->auto_renew]);

        return back()->with('message', $subscription->auto_renew ? 'เปิดต่ออายุอัตโนมัติแล้ว' : 'ปิดต่ออายุอัตโนมัติแล้ว');
    }

    public function shopBranding(Shop $shop, string $target)
    {
        abort_unless(in_array($target, ['logo', 'banner'], true), 404);
        $path = $shop->{"{$target}_path"};
        abort_if(! $path, 404);

        return Storage::disk('private')->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function toggleListingVisibility(Request $request, Shop $shop, InventoryItem $item)
    {
        abort_unless($item->shop_id === $shop->id, 404);
        $hidden = ! $item->hidden_from_directory;
        $item->update(['hidden_from_directory' => $hidden]);
        $this->recordAdminLog($request, $shop, 'listing.directory_hidden', $item, ['tag' => $item->tag, 'hidden' => $hidden]);

        return back()->with('message', $hidden ? "ซ่อน #{$item->tag} จากหน้ารวมแล้ว" : "แสดง #{$item->tag} ในหน้ารวมแล้ว");
    }

    public function storePlan(Request $request)
    {
        $data = $this->validatePlan($request);
        $plan = SubscriptionPlan::create($data);
        $this->recordAdminLog($request, null, 'plan.created', $plan, ['code' => $plan->code]);

        return redirect()->route('admin.plans.index')->with('message', 'สร้างแพ็กเกจแล้ว');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $this->validatePlan($request, $plan);
        $plan->update($data);
        $this->recordAdminLog($request, null, 'plan.updated', $plan, ['code' => $plan->code]);

        return redirect()->route('admin.plans.index')->with('message', 'อัปเดตแพ็กเกจแล้ว');
    }

    public function reviewTopUp(Request $request, PaymentSubmission $payment, CreditWallet $wallet)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'required_if:decision,rejected', 'string', 'min:3', 'max:1000'],
        ]);
        abort_unless($payment->credit_amount, 404);
        if (! in_array($payment->status, ['pending', 'pending_review'], true)) {
            return back()->withErrors(['review_note' => 'รายการนี้ถูกตรวจสอบแล้ว กรุณาโหลดหน้าใหม่เพื่อดูสถานะล่าสุด']);
        }
        if ($data['decision'] === 'approved') {
            $wallet->approveTopUp($payment);
            $payment->update(['review_note' => ($data['review_note'] ?? null) ?: 'ตรวจสอบสลิปและยอดเงินถูกต้อง']);
            $message = 'อนุมัติการเติมเครดิตแล้ว';
        } else {
            $payment->update(['status' => 'rejected', 'review_note' => $data['review_note']]);
            $message = 'ไม่อนุมัติการเติมเครดิตแล้ว';
        }
        $this->recordAdminLog($request, $payment->shop, $data['decision'] === 'approved' ? 'credit.top_up_approved' : 'credit.top_up_rejected', $payment, ['credits' => $payment->credit_amount, 'note' => $payment->review_note]);

        $payment->loadMissing('shop');
        if ($payment->shop) {
            $recipients = $payment->shop->billingRecipients();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PaymentReviewedNotification(
                    $payment->fresh('shop'),
                    $data['decision'] === 'approved'
                        ? PaymentReviewedNotification::OUTCOME_APPROVED
                        : PaymentReviewedNotification::OUTCOME_REJECTED,
                ));
            }
        }

        return redirect()->route('admin.top-ups.show', $payment)->with('message', $message);
    }

    public function slip(Request $request, PaymentSubmission $payment)
    {
        abort_unless($payment->credit_amount && Storage::disk($payment->slip_disk)->exists($payment->slip_path), 404);
        $this->recordAdminLog($request, $payment->shop, 'admin.slip_viewed', $payment, ['credits' => $payment->credit_amount]);

        return Storage::disk($payment->slip_disk)->response($payment->slip_path);
    }

    public function logout(Request $request)
    {
        $this->recordAdminLog($request, null, 'admin.logged_out', null);
        $request->session()->forget('admin_user_id');
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function page(string $page, string $title, array $data = [])
    {
        return view('admin.console', array_merge($this->sharedData(), compact('page', 'title'), $data));
    }

    private function sharedData(): array
    {
        return ['totals' => [
            'shops' => Shop::count(),
            'items' => InventoryItem::count(),
            'users' => User::where('is_super_admin', false)->count(),
            'credit_balance' => Shop::sum('credit_balance'),
            'pending_top_ups' => PaymentSubmission::where('status', 'pending_review')->whereNotNull('credit_amount')->count(),
            'credited_total' => CreditTransaction::where('type', 'credit_top_up')->sum('credits'),
        ]];
    }

    private function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'alpha_dash', 'max:40', Rule::unique('subscription_plans', 'code')->ignore($plan?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active_inventory_limit' => ['nullable', 'integer', 'min:1'],
            'member_limit' => ['nullable', 'integer', 'min:1'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'sale_price_monthly' => ['nullable', 'integer', 'min:0'],
            'sale_price_yearly' => ['nullable', 'integer', 'min:0'],
            'sale_label' => ['nullable', 'string', 'max:60'],
            'sale_ends_at' => ['nullable', 'date'],
            'monthly_days' => ['required', 'integer', 'min:1', 'max:366'],
            'yearly_days' => ['required', 'integer', 'min:1', 'max:400'],
            'features' => ['array'],
            'features.*' => ['in:'.implode(',', SubscriptionPlan::FEATURES)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $selected = (array) ($data['features'] ?? []);
        $data['features'] = collect(SubscriptionPlan::FEATURES)
            ->mapWithKeys(fn ($key) => [$key => in_array($key, $selected, true)])
            ->all();

        return $data;
    }

    private function recordAdminLog(Request $request, ?Shop $shop, string $event, $subject, array $metadata = []): void
    {
        ActivityLog::create([
            'shop_id' => $shop?->id,
            'user_id' => $request->session()->get('admin_user_id'),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
