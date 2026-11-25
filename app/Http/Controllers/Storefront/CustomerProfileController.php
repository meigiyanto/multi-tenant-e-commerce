<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerProfileUpdateRequest;
use App\Models\CustomerAddress;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function edit(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $customer = $request->user('customer');

        return Inertia::render('storefront/account/profile', [
            'store' => ['name' => $store->name],
            'profile' => $customer->only(['name', 'email']),
            'avatar_url' => $customer->avatar_path ? Storage::disk('public')->url($customer->avatar_path) : null,
            'addresses' => $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (CustomerAddress $address): array => [
                    'id' => $address->id, 'label' => $address->label, 'type' => $address->type,
                    'recipient_name' => $address->recipient_name, 'phone' => $address->phone,
                    'address' => $address->address, 'city' => $address->city,
                    'province' => $address->province, 'postal_code' => $address->postal_code,
                    'is_default' => $address->is_default,
                ]),
        ]);
    }

    public function update(CustomerProfileUpdateRequest $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $customer = $request->user('customer');
        $validated = $request->validated();
        $emailChanged = $customer->email !== $validated['email'];
        unset($validated['current_password'], $validated['password_confirmation']);
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $avatar = $request->file('avatar');
        unset($validated['avatar']);
        $oldAvatar = $customer->avatar_path;
        if ($avatar) {
            $validated['avatar_path'] = $avatar->store('customers/avatars/'.$store->id, 'public');
        }
        $customer->fill($validated);
        if ($emailChanged) {
            $customer->email_verified_at = null;
        }
        $customer->save();
        if ($avatar && $oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }
        if ($emailChanged) {
            $customer->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $emailChanged
                ? __('Profile updated. A verification link has been sent to your new email address.')
                : __('Profile updated.'),
        ]);

        return $emailChanged
            ? to_route('storefront.account.verification', ['storeSubdomain' => $request->route('storeSubdomain')])
            : to_route('storefront.account.profile', ['storeSubdomain' => $request->route('storeSubdomain')]);
    }

    public function storeAddress(Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $customer = $request->user('customer');
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['home', 'office'])],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($customer, $store, $data): void {
            $makeDefault = (bool) ($data['is_default'] ?? false) || ! $customer->addresses()->exists();
            if ($makeDefault) {
                $customer->addresses()->update(['is_default' => false]);
            }
            $customer->addresses()->create([...$data, 'store_id' => $store->id, 'is_default' => $makeDefault]);
        });

        return back()->with('success', 'Address added successfully.');
    }

    public function updateAddress(Request $request, StoreContext $context, string $address): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $customer = $request->user('customer');
        $record = $customer->addresses()->where('store_id', $store->id)->whereKey($address)->firstOrFail();
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['home', 'office'])],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($customer, $record, $data): void {
            $makeDefault = (bool) ($data['is_default'] ?? false) || $record->is_default;
            if ($makeDefault) {
                $customer->addresses()->where('id', '!=', $record->id)->update(['is_default' => false]);
            }
            $record->update([...$data, 'is_default' => $makeDefault]);
        });

        return back()->with('success', 'Address updated successfully.');
    }

    public function setDefaultAddress(Request $request, StoreContext $context, string $address): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $customer = $request->user('customer');
        $record = $customer->addresses()->where('store_id', $store->id)->whereKey($address)->firstOrFail();
        DB::transaction(function () use ($customer, $record): void {
            $customer->addresses()->update(['is_default' => false]);
            $record->update(['is_default' => true]);
        });

        return back()->with('success', 'Default shipping address updated.');
    }

    public function destroyAddress(Request $request, StoreContext $context, string $address): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $customer = $request->user('customer');
        $record = $customer->addresses()->where('store_id', $store->id)->whereKey($address)->firstOrFail();
        DB::transaction(function () use ($customer, $record): void {
            $wasDefault = $record->is_default;
            $record->delete();
            if ($wasDefault) {
                $next = $customer->addresses()->orderBy('id')->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return back()->with('success', 'Address deleted successfully.');
    }
}
