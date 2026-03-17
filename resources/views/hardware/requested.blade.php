@extends('layouts/default')

@section('title0')
  {{ trans('admin/hardware/general.requested') }}
  {{ trans('general.assets') }}
@stop

{{-- Page title --}}
@section('title')
    @yield('title0')  @parent
@stop

{{-- Page content --}}
@section('content')

<div class="row"><!-- .row -->
    <div class="col-md-12"><!-- .col-md-12 -->
        <div class="box box-default"><!-- .box -->
            <div class="box-body"><!-- .bow-body -->
                <div class="row"><!-- .row -->
                    <div class="col-md-12"><!-- col-md-12 -->

                        <table
                            data-toolbar="#toolbar"
                            class="table table-striped snipe-table"
                            id="requestedAssets"
                            data-id-table="requestedAssets"
                            data-cookie-id-table="requestedAssets"
                            data-export-options='{
                            "fileName": "export-assetrequests-{{ date('Y-m-d') }}",
                            "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                        }'>
                            <thead>
                                <tr role="row">
                                    <th class="col-md-1">{{ trans('general.image') }}</th>
                                    <th class="col-md-2">{{ trans('general.name') }}</th>
                                    <th class="col-md-1" data-sortable="true">{{ trans('admin/hardware/table.location') }}</th>
                                    <th class="col-md-1" data-sortable="true">{{ trans('admin/hardware/form.expected_checkin') }}</th>
                                    <th class="col-md-2" data-sortable="true">{{ trans('admin/hardware/table.requesting_user') }}</th>
                                    <th class="col-md-1">{{ trans('admin/hardware/table.requested_date') }}</th>
                                    <th class="col-md-1">{{ trans('general.type') }}</th>
                                    <th class="col-md-1">{{ trans('button.actions') }}</th>
                                    <th class="col-md-1">{{ trans('general.checkout') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requestedItems as $request)

                                    @if ($request->requestable)
                                    <tr>
                                    {{ csrf_field() }}
                                        <td>
                                        @if (($request->itemType() == "asset") && ($request->requestable))
                                            <a href="{{ $request->requestable->getImageUrl() }}" data-toggle="lightbox" data-type="image"><img src="{{ $request->requestable->getImageUrl() }}" style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width: auto;" class="img-responsive" alt="{{ $request->requestable->name }}"></a>
                                        @elseif (($request->itemType() == "asset_model") && ($request->requestable))
                                            <a href="{{ config('app.url') }}/uploads/models/{{ $request->requestable->image }}" data-toggle="lightbox" data-type="image"><img src="{{ config('app.url') }}/uploads/models/{{ $request->requestable->image }}" style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width: auto;" class="img-responsive" alt="{{ $request->requestable->name }}"></a>
                                        @endif
                                        </td>
                                        <td>

                                            @if ($request->itemType() == "asset")
                                                <a href="{{ config('app.url') }}/hardware/{{ $request->requestable->id }}">
                                                    {{ $request->name() }}
                                                </a>
                                            @elseif ($request->itemType() == "asset_model")
                                                <a href="{{ config('app.url') }}/models/{{ $request->requestable->id }}">
                                                    {{ $request->name() }}
                                                </a>
                                            @endif

                                        </td>
                                        <td>
                                            {{ $request->location() ? $request->location()->name : '' }}
                                        </td>

                                        <td>
                                            @if ($request->itemType() == "asset")
                                            {{ App\Helpers\Helper::getFormattedDateObject($request->requestable->expected_checkin, 'datetime', false) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($request->requestingUser() && !$request->requestingUser()->trashed())
                                                <a href="{{ config('app.url') }}/users/{{ $request->requestingUser()->id }}">
                                                    {{ $request->requestingUser()->display_name }}
                                                </a>
                                            @else
                                                {{ trans('admin/reports/general.deleted_user') }}
                                            @endif
                                        </td>
                                        <td>
                                            {{ App\Helpers\Helper::getFormattedDateObject($request->created_at, 'datetime', false) }}
                                        </td>
                                        <td>
                                            @if ($request->type?->value === 'purchase')
                                                <span class="label label-warning">{{ trans('general.purchase_request') }}</span>
                                                @if ($request->itemType() == 'asset')
                                                    <br><small>{{ $snipeSettings->default_currency }}{{ $request->requestable->sale_price }}</small>
                                                @endif
                                            @else
                                                <span class="label label-info">{{ trans('general.checkout') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form
                                            method="POST"
                                            action="{{ route('account/request-item', [
                                            $request->itemType(),
                                            $request->requestable->id,
                                            true,
                                            $request->requestingUser()->id
                                            ]) }}"
                                            accept-charset="UTF-8"
                                            >
                                            @csrf
                                                <button class="btn btn-warning btn-sm" data-tooltip="true" title="{{ trans('general.cancel_request') }}">{{ trans('button.cancel') }}</button>
                                            </form>
                                        </td>
                                        <td>
                                            @if ($request->itemType() == "asset")
                                                @if ($request->type?->value === 'purchase')
                                                    <button type="button" class="btn btn-sm btn-success"
                                                        data-toggle="modal"
                                                        data-target="#approvePurchaseModal-{{ $request->id }}"
                                                        data-tooltip="true"
                                                        title="{{ trans('general.approve_sale') }}">
                                                        {{ trans('general.approve_sale') }}
                                                    </button>
                                                @elseif ($request->requestable->assigned_to=='')
                                                    <a href="{{ config('app.url') }}/hardware/{{ $request->requestable->id }}/checkout" class="btn btn-sm bg-maroon" data-tooltip="true" title="{{ trans('general.checkout_user_tooltip') }}">{{ trans('general.checkout') }}</a>
                                                @else
                                                    <a href="{{ config('app.url') }}/hardware/{{ $request->requestable->id }}/checkin" class="btn btn-sm bg-purple" data-tooltip="true" title="{{ trans('general.checkin_tooltip') }}">{{ trans('general.checkin') }}</a>
                                                @endif
                                            @endif
                                        </td>

                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Purchase approval modals --}}
                        @foreach ($requestedItems as $request)
                            @if ($request->type?->value === 'purchase' && $request->requestable)
                                <div class="modal fade" id="approvePurchaseModal-{{ $request->id }}" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('hardware.purchase.approve', [$request->requestable->id, $request->id]) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                    <h4 class="modal-title">{{ trans('general.approve_sale') }}</h4>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>{{ trans('general.asset') }}:</strong> {{ $request->name() }}</p>
                                                    <p><strong>{{ trans('general.buyer') }}:</strong> {{ $request->requestingUser() ? $request->requestingUser()->display_name : trans('admin/reports/general.deleted_user') }}</p>
                                                    <p><strong>{{ trans('general.sale_price') }}:</strong> {{ $snipeSettings->default_currency }}{{ $request->requestable->sale_price }}</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('button.cancel') }}</button>
                                                    <button type="submit" class="btn btn-success">{{ trans('general.approve_sale') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                    </div> <!-- /.col-md-12 -->
                </div> <!-- /.row -->
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div> <!-- .col-md-12 -->
</div> <!-- .row -->
@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table', [
        'exportFile' => 'requested-export',
        'search' => true,
        'clientSearch' => true,
    ])

@stop
