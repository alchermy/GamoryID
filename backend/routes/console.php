<?php

use App\Models\User;
use App\Services\DataRetentionLifecycle;
use App\Services\ReservationLifecycle;
use App\Services\SubscriptionLifecycle;
use App\Services\Totp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gamoryid:admin-2fa {email}', function (Totp $totp) {
    $user = User::where('email', $this->argument('email'))->where('is_super_admin', true)->firstOrFail();
    $secret = $totp->generateSecret();
    $user->update(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
    $this->info('เพิ่ม URI นี้ในแอป Authenticator ก่อนเข้าสู่ Super Admin:');
    $this->line($totp->uri($secret, $user->email));
})->purpose('ตั้งค่า 2FA สำหรับบัญชี Super Admin ในเครื่องพัฒนา');

Schedule::call(fn (SubscriptionLifecycle $lifecycle) => $lifecycle->run())
    ->hourly()->name('subscriptions.lifecycle')->withoutOverlapping();

Schedule::call(fn (ReservationLifecycle $lifecycle) => $lifecycle->run())
    ->everyFiveMinutes()->name('reservations.lifecycle')->withoutOverlapping();

Schedule::call(fn (DataRetentionLifecycle $lifecycle) => $lifecycle->run())
    ->dailyAt('03:20')->name('data.retention')->withoutOverlapping();
