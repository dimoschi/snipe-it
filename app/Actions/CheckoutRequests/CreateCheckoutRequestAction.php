<?php

namespace App\Actions\CheckoutRequests;

use App\Enums\ActionType;
use App\Enums\CheckoutRequestType;
use App\Exceptions\AssetNotRequestable;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RequestAssetNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Log;

class CreateCheckoutRequestAction
{
    /**
     * Create a checkout or purchase request for an asset.
     *
     * @throws AssetNotRequestable
     * @throws AuthorizationException
     */
    public static function run(Asset $asset, User $user, ?CheckoutRequestType $type = null): bool
    {
        $type = $type ?? CheckoutRequestType::Checkout;

        if ($type === CheckoutRequestType::Purchase) {
            if (is_null(Asset::PurchasableAssets()->find($asset->id))) {
                throw new AssetNotRequestable($asset);
            }
        } else {
            if (is_null(Asset::RequestableAssets()->find($asset->id))) {
                throw new AssetNotRequestable($asset);
            }
        }

        if (! Company::isCurrentUserHasAccess($asset)) {
            throw new AuthorizationException;
        }

        $data['item'] = $asset;
        $data['target'] = $user;
        $data['item_quantity'] = 1;
        $data['request_type'] = $type;
        $settings = Setting::getSettings();

        $logaction = new Actionlog;
        $logaction->item_id = $data['asset_id'] = $asset->id;
        $logaction->item_type = $data['item_type'] = Asset::class;
        $logaction->created_at = $data['requested_date'] = date('Y-m-d H:i:s');
        $logaction->target_id = $data['user_id'] = auth()->id();
        $logaction->target_type = User::class;
        $logaction->location_id = $user->location_id ?? null;

        if ($type === CheckoutRequestType::Purchase) {
            $logaction->logaction(ActionType::PurchaseRequested);
        } else {
            $logaction->logaction(ActionType::Requested);
        }

        $asset->request(1, $type);
        $asset->increment('requests_counter', 1);

        try {
            $settings->notify(new RequestAssetNotification($data));
        } catch (\Exception $e) {
            Log::warning($e);
        }

        return true;
    }
}
