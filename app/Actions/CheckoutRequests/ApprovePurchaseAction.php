<?php

namespace App\Actions\CheckoutRequests;

use App\Enums\ActionType;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\Statuslabel;
use App\Models\User;
use App\Notifications\PurchaseApprovedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ApprovePurchaseAction
{
    /**
     * Approve a purchase request, marking the asset as sold.
     *
     * @throws \Exception if asset is already archived/sold or purchase feature is not configured
     */
    public static function run(Asset $asset, CheckoutRequest $checkoutRequest): bool
    {
        $purchaseStatus = Statuslabel::where('default_purchase_label', 1)->first();

        if (! $purchaseStatus) {
            throw new \Exception(trans('general.purchase_not_configured'));
        }

        // Fresh lookup to guard against concurrent approvals
        $asset = Asset::where('id', $asset->id)
            ->whereHas('assetstatus', fn ($q) => $q->where('archived', 0))
            ->first();

        if (! $asset) {
            throw new \Exception(trans('general.asset_already_sold'));
        }

        $salePrice = $asset->sale_price;
        $buyer = $checkoutRequest->user;

        // Update asset status to sold
        $asset->status_id = $purchaseStatus->id;
        $asset->assigned_to = null;
        $asset->assigned_type = null;
        $asset->save();

        // Fulfill the request
        $checkoutRequest->fulfilled_at = Carbon::now();
        $checkoutRequest->save();

        // Decrement requests counter
        $asset->decrement('requests_counter', 1);

        // Log the purchase approval
        $logaction = new Actionlog;
        $logaction->item_id = $asset->id;
        $logaction->item_type = Asset::class;
        $logaction->target_id = $buyer->id;
        $logaction->target_type = User::class;
        $logaction->created_at = Carbon::now();
        $logaction->location_id = $buyer->location_id ?? null;
        $logaction->note = trans('general.purchase_approved_log', [
            'price' => $salePrice,
            'buyer' => $buyer->display_name,
        ]);
        $logaction->logaction(ActionType::PurchaseApproved);

        // Auto-cancel remaining open purchase requests for this asset
        CheckoutRequest::where('requestable_id', $asset->id)
            ->where('requestable_type', Asset::class)
            ->where('type', 'purchase')
            ->whereNull('canceled_at')
            ->whereNull('fulfilled_at')
            ->where('id', '!=', $checkoutRequest->id)
            ->update(['canceled_at' => Carbon::now()]);

        // Send notification to buyer
        try {
            $buyer->notify(new PurchaseApprovedNotification([
                'item' => $asset,
                'target' => $buyer,
                'sale_price' => $salePrice,
            ]));
        } catch (\Exception $e) {
            Log::warning($e);
        }

        return true;
    }
}
