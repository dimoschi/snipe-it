<?php

namespace Tests\Unit;

use App\Actions\CheckoutRequests\ApprovePurchaseAction;
use App\Actions\CheckoutRequests\CreateCheckoutRequestAction;
use App\Enums\CheckoutRequestType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Depreciation;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    public function test_is_requested_by_filters_on_type()
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        CheckoutRequest::factory()->forAsset()->create([
            'user_id' => $user->id,
            'requestable_id' => $asset->id,
            'type' => 'checkout',
        ]);

        $this->assertNotNull($asset->isRequestedBy($user, CheckoutRequestType::Checkout));
        $this->assertNull($asset->isRequestedBy($user, CheckoutRequestType::Purchase));
        $this->assertNotNull($asset->isRequestedBy($user));
    }

    public function test_cancel_request_scopes_to_type()
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        CheckoutRequest::factory()->forAsset()->create([
            'user_id' => $user->id,
            'requestable_id' => $asset->id,
            'type' => 'checkout',
        ]);
        CheckoutRequest::factory()->forAsset()->forPurchase()->create([
            'user_id' => $user->id,
            'requestable_id' => $asset->id,
        ]);

        $asset->cancelRequest($user->id, CheckoutRequestType::Purchase);

        $asset->refresh();
        $asset->load('requests');
        $this->assertNotNull($asset->isRequestedBy($user, CheckoutRequestType::Checkout));
        $this->assertNull($asset->isRequestedBy($user, CheckoutRequestType::Purchase));
    }

    public function test_scope_purchasable_assets_filters_correctly()
    {
        $this->settings->set([
            'purchase_age_threshold_months' => 48,
        ]);

        $depreciation = Depreciation::factory()->create(['months' => 36]);
        $model = AssetModel::factory()->create(['depreciation_id' => $depreciation->id]);
        $deployableStatus = Statuslabel::factory()->rtd()->create();

        // Eligible: old enough, has depreciation, has purchase_cost, deployable
        $eligible = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'purchase_date' => now()->subMonths(60)->format('Y-m-d'),
            'purchase_cost' => 1000,
        ]);
        $this->assertNotNull($eligible->id, 'Eligible asset not saved');

        // Too new
        Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'purchase_date' => now()->subMonths(12)->format('Y-m-d'),
            'purchase_cost' => 1000,
        ]);

        // No purchase_cost
        Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'purchase_date' => now()->subMonths(60)->format('Y-m-d'),
            'purchase_cost' => null,
        ]);

        // No depreciation on model
        $modelNoDep = AssetModel::factory()->create(['depreciation_id' => null]);
        Asset::factory()->create([
            'model_id' => $modelNoDep->id,
            'status_id' => $deployableStatus->id,
            'purchase_date' => now()->subMonths(60)->format('Y-m-d'),
            'purchase_cost' => 1000,
        ]);

        $results = Asset::PurchasableAssets()->get();

        $this->assertTrue($results->contains('id', $eligible->id));
        $this->assertCount(1, $results);
    }

    public function test_sale_price_uses_purchase_price_override_when_set()
    {
        $asset = Asset::factory()->create([
            'purchase_cost' => 1000,
            'purchase_price' => 350.00,
        ]);

        $this->assertEquals(350.00, $asset->sale_price);
    }

    public function test_sale_price_falls_back_to_depreciated_value()
    {
        $depreciation = Depreciation::factory()->create(['months' => 36]);
        $model = AssetModel::factory()->create(['depreciation_id' => $depreciation->id]);

        $asset = Asset::factory()->create([
            'model_id' => $model->id,
            'purchase_cost' => 1000,
            'purchase_date' => now()->subMonths(60)->format('Y-m-d'),
            'purchase_price' => null,
        ]);

        $this->assertNotNull($asset->sale_price);
        $this->assertNull($asset->purchase_price);
    }

    public function test_create_checkout_request_action_handles_purchase_type()
    {
        $this->settings->set([
            'purchase_age_threshold_months' => 48,
        ]);

        Statuslabel::factory()->archived()->create(['default_purchase_label' => 1]);

        $depreciation = Depreciation::factory()->create(['months' => 36]);
        $model = AssetModel::factory()->create(['depreciation_id' => $depreciation->id]);
        $deployableStatus = Statuslabel::factory()->rtd()->create();

        $asset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'purchase_date' => now()->subMonths(60)->format('Y-m-d'),
            'purchase_cost' => 1000,
            'requestable' => 0,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        CreateCheckoutRequestAction::run(
            $asset, $user, CheckoutRequestType::Purchase
        );

        $request = CheckoutRequest::where('requestable_id', $asset->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($request);
        $this->assertEquals(CheckoutRequestType::Purchase, $request->type);
    }

    public function test_approve_purchase_action_sets_asset_to_sold()
    {
        // Ensure only one label has the default_purchase_label flag
        Statuslabel::where('default_purchase_label', 1)->update(['default_purchase_label' => 0]);
        $soldStatus = Statuslabel::factory()->archived()->create([
            'name' => 'ApprovalTestSold',
            'default_purchase_label' => 1,
        ]);
        $this->settings->set([
            'purchase_age_threshold_months' => 48,
        ]);

        $depreciation = Depreciation::factory()->create(['months' => 36]);
        $model = AssetModel::factory()->create(['depreciation_id' => $depreciation->id]);
        $deployableStatus = Statuslabel::factory()->rtd()->create();

        $asset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'purchase_date' => now()->subMonths(60)->format('Y-m-d'),
            'purchase_cost' => 1000,
            'requests_counter' => 1,
        ]);

        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $checkoutRequest = CheckoutRequest::factory()->forAsset()->forPurchase()->create([
            'user_id' => $user->id,
            'requestable_id' => $asset->id,
        ]);

        $result = ApprovePurchaseAction::run($asset, $checkoutRequest);

        $this->assertTrue($result);

        $asset->refresh();
        $this->assertEquals($soldStatus->id, $asset->status_id);
        $this->assertNull($asset->assigned_to);
        $this->assertEquals(0, $asset->requests_counter);

        $checkoutRequest->refresh();
        $this->assertNotNull($checkoutRequest->fulfilled_at);
    }

    public function test_approve_purchase_action_rejects_archived_asset()
    {
        Statuslabel::factory()->archived()->create([
            'name' => 'RejectionTestSold',
            'default_purchase_label' => 1,
        ]);

        $archivedStatus = Statuslabel::factory()->archived()->create();

        $asset = Asset::factory()->create([
            'status_id' => $archivedStatus->id,
        ]);

        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $checkoutRequest = CheckoutRequest::factory()->forAsset()->forPurchase()->create([
            'user_id' => $user->id,
            'requestable_id' => $asset->id,
        ]);

        $this->expectException(\Exception::class);
        ApprovePurchaseAction::run($asset, $checkoutRequest);
    }

    public function test_approve_purchase_auto_cancels_other_requests()
    {
        Statuslabel::factory()->archived()->create([
            'name' => 'AutoCancelTestSold',
            'default_purchase_label' => 1,
        ]);

        $deployableStatus = Statuslabel::factory()->rtd()->create();
        $asset = Asset::factory()->create([
            'status_id' => $deployableStatus->id,
            'requests_counter' => 2,
        ]);

        $buyer = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $winningRequest = CheckoutRequest::factory()->forAsset()->forPurchase()->create([
            'user_id' => $buyer->id,
            'requestable_id' => $asset->id,
        ]);
        $losingRequest = CheckoutRequest::factory()->forAsset()->forPurchase()->create([
            'user_id' => $otherUser->id,
            'requestable_id' => $asset->id,
        ]);

        ApprovePurchaseAction::run($asset, $winningRequest);

        $losingRequest->refresh();
        $this->assertNotNull($losingRequest->canceled_at);
    }
}
