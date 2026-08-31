<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;

class PaymentController extends Controller
{
    public function plans()
    {
        return response()->json(['data' => SubscriptionPlan::where('is_active', true)->orderBy('price_thb')->get()]);
    }
}
