@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.purchase_settings') }}
    @parent
@stop

@section('header_right')
    <a href="{{ route('settings.index') }}" class="btn btn-primary"> {{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')

    <form method="POST" action="{{ route('settings.purchases.save') }}" autocomplete="off" class="form-horizontal" role="form">

    {{ csrf_field() }}

    <div class="row">
        <div class="col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2">

            <div class="panel box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title">
                        <i class="fas fa-shopping-cart"></i> {{ trans('general.purchase_settings') }}
                    </h2>
                </div>
                <div class="box-body">

                    <!-- Purchase Age Threshold -->
                    <div class="form-group{{ $errors->has('purchase_age_threshold_months') ? ' has-error' : '' }}">
                        <div class="col-md-3">
                            <label for="purchase_age_threshold_months" class="control-label">
                                {{ trans('general.purchase_age_threshold') }}
                            </label>
                        </div>
                        <div class="col-md-9">
                            <input type="number" name="purchase_age_threshold_months" id="purchase_age_threshold_months"
                                   class="form-control" min="1"
                                   value="{{ old('purchase_age_threshold_months', $setting->purchase_age_threshold_months) }}">
                            {!! $errors->first('purchase_age_threshold_months', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
                            <p class="help-block">{{ trans('general.purchase_age_threshold_help') }}</p>
                        </div>
                    </div>

                </div> <!-- /.box-body -->

                <div class="box-footer">
                    <div class="text-left col-md-6">
                        <a class="btn btn-link text-left" href="{{ route('settings.index') }}">{{ trans('button.cancel') }}</a>
                    </div>
                    <div class="text-right col-md-6">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check icon-white" aria-hidden="true"></i> {{ trans('general.save') }}</button>
                    </div>
                </div>

            </div> <!-- /.box -->
        </div> <!-- /.col -->
    </div> <!-- /.row -->
    </form>

@stop
