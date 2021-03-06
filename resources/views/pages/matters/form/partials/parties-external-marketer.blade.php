<div class="col-4">
    <label class="form-check form-switch form-switch-sm form-check-custom form-check-solid flex-stack mt-12 mb-5">
        <span class="form-check-label ms-0 fw-bolder fs-6 text-gray-700">{{__('app.external_marketing')}}</span>
        <input class="form-check-input" name="has_external_commission" value="1" wire:model="hasExternalCommission"
            type="checkbox">
    </label>
</div>
@if ($hasExternalCommission)
    <div class="col-6">
        <label class="form-label w-100">{{ __('app.external-marketer') }}
            <a href="javascript:;" wire:click="showModal" class="fw-light text-active-dark float-end"
                data-bs-toggle="modal" data-bs-target="#kt_thirdparty_modal">{{__('app.add_new')}}</a>
        </label>
        <!--end::Label-->
        <!--begin::Select-->
        <select id="externalMarketer" data-placeholder="{{__('app.select_marketer')}}" wire:model="marketing.external_markter.id"
            data-control="select2"
            class="@error('marketing.external_markter.id') is-invalid @enderror form-select form-select-solid"
            name="parties[third_party][name]">
            <option value=""></option>
            @foreach ($externalMarketersList as $externalMarketer)
                <option value="{{ $externalMarketer['id'] }}">{{ $externalMarketer['id'] }} -
                    {{ $externalMarketer['name'] }}</option>
            @endforeach
        </select>
        @error('marketing.external_markter.id')
            <div class="invalid-feedback fv-plugins-message-container">
                {{ $message }}
            </div>
        @enderror
    </div>
    <div class="col-2">
        <label class="form-label fw-bolder fs-6 text-gray-700">{{ __('app.rate') }}</label>
        <div class="input-group input-group-solid">
            <input type="text" name="matter[external_commission_percent]"
                wire:model="matter.external_commission_percent"
                class="@error('matter.external_commission_percent') pe-5  is-invalid @enderror form-control form-control-solid">
            <span class="input-group-text" id="basic-addon2">%</span>
        </div>
        @error('matter.external_commission_percent')
            <div class="invalid-feedback fv-plugins-message-container">
                {{ $message }}
            </div>
        @enderror
    </div>
@endif
