<?php

namespace App\Http\Controllers\Api;

use App\Actions\CheckoutRequests\CancelCheckoutRequestAction;
use App\Actions\CheckoutRequests\CreateCheckoutRequestAction;
use App\Enums\CheckoutRequestType;
use App\Exceptions\AssetNotRequestable;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutRequest extends Controller
{
    public function store(Request $request, Asset $asset): JsonResponse
    {
        try {
            $type = null;
            if ($request->filled('type')) {
                $type = CheckoutRequestType::tryFrom($request->input('type'));
                if (! $type) {
                    return response()->json(
                        Helper::formatStandardApiResponse('error', null, trans('general.invalid_request_type'))
                    );
                }
            }

            CreateCheckoutRequestAction::run($asset, auth()->user(), $type);

            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/hardware/message.requests.success')));
        } catch (AssetNotRequestable $e) {
            return response()->json(Helper::formatStandardApiResponse('error', 'Asset is not requestable'));
        } catch (AuthorizationException $e) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('general.insufficient_permissions')));
        } catch (Exception $e) {
            report($e);

            return response()->json(Helper::formatStandardApiResponse('error', null, trans('general.something_went_wrong')));
        }
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        try {
            $type = null;
            if ($request->filled('type')) {
                $type = CheckoutRequestType::tryFrom($request->input('type'));
            }

            CancelCheckoutRequestAction::run($asset, auth()->user(), $type);

            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/hardware/message.requests.canceled')));
        } catch (AuthorizationException $e) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('general.insufficient_permissions')));
        } catch (Exception $e) {
            report($e);

            return response()->json(Helper::formatStandardApiResponse('error', null, trans('general.something_went_wrong')));
        }
    }
}
