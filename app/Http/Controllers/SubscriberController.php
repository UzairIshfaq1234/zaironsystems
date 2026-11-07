<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriberController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['email' => ['required', 'string', 'max:190', 'email:rfc,strict']],
            ['email.*' => 'Please enter a valid email.'],
        );

        Subscriber::firstOrCreate(['email' => Str::lower($data['email'])], ['ip_address' => $request->ip()]);

        // Same answer for new and existing addresses, so the form can't be used to discover subscribers.
        return response()->json(['message' => 'Thanks! You are subscribed.']);
    }
}
