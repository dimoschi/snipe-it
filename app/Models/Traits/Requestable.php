<?php

namespace App\Models\Traits;

use App\Enums\CheckoutRequestType;
use App\Models\CheckoutRequest;
use App\Models\User;
use Carbon\Carbon;

// $asset->requests
// $asset->isRequestedBy($user)
// $asset->whereRequestedBy($user)
trait Requestable
{
    public function requests()
    {
        return $this->morphMany(CheckoutRequest::class, 'requestable');
    }

    public function isRequestedBy(User $user, ?CheckoutRequestType $type = null)
    {
        $query = $this->requests->where('canceled_at', null)->where('user_id', $user->id);

        if ($type !== null) {
            $query = $query->where('type', $type);
        }

        return $query->first();
    }

    public function scopeRequestedBy($query, User $user)
    {
        return $query->whereHas(
            'requests', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        );
    }

    public function request($qty = 1, ?CheckoutRequestType $type = null)
    {
        $type = $type ?? CheckoutRequestType::Checkout;

        $this->requests()->save(
            new CheckoutRequest([
                'user_id' => auth()->id(),
                'qty' => $qty,
                'type' => $type->value,
            ])
        );
    }

    public function deleteRequest()
    {
        $this->requests()->where('user_id', auth()->id())->delete();
    }

    public function cancelRequest($user_id = null, ?CheckoutRequestType $type = null)
    {
        if (! $user_id) {
            $user_id = auth()->id();
        }

        $query = $this->requests()->where('user_id', $user_id);

        if ($type !== null) {
            $query = $query->where('type', $type->value);
        }

        $query->update(['canceled_at' => Carbon::now()]);
    }
}
