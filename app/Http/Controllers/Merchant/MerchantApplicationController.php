<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MerchantApplicationController extends Controller
{
    public function show(Request $request): Response
    {
        $application = MerchantApplication::query()->where('user_id', $request->user()->id)->first();

        return Inertia::render('merchant/application', [
            'application' => $application?->only([
                'business_name', 'business_description', 'status', 'review_notes', 'reviewed_at',
            ]),
            'canApply' => $application === null || $application->status === 'rejected',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:3000'],
        ]);

        $application = MerchantApplication::query()->firstOrNew(['user_id' => $request->user()->id]);
        abort_if($application->status === 'approved' || $application->status === 'pending', 409);

        $application->fill($validated + [
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your merchant application has been submitted for review.')]);

        return to_route('merchant.application.show');
    }
}
