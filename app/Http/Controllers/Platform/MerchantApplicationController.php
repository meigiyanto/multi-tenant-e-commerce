<?php

namespace App\\Http\\Controllers\\Platform;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MerchantApplication;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;
use Inertia\\Inertia;
use Inertia\\Response;

class MerchantApplicationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/merchant-applications/index', [
            'applications' => MerchantApplication::query()
                ->with(['user:id,name,email', 'reviewer:id,name'])
                ->latest()
                ->paginate(20)
                ->through(fn (MerchantApplication $application) => [
                    'id' => $application->id,
                    'business_name' => $application->business_name,
                    'business_description' => $application->business_description,
                    'status' => $application->status,
                    'review_notes' => $application->review_notes,
                    'created_at' => $application->created_at?->toISOString(),
                    'user' => $application->user->only(['id', 'name', 'email']),
                    'reviewer' => $application->reviewer?->only(['id', 'name']),
                ]),
        ]);
    }

    public function update(Request $request, MerchantApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        abort_unless($application->status === 'pending', 409);

        $application->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['status'] === 'approved'
                ? __('Merchant application approved.')
                : __('Merchant application rejected.'),
        ]);

        return to_route('platform.admin.merchant-applications.index');
    }
}
