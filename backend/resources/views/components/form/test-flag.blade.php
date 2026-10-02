@props(['model' => null])
{{-- Only users allowed to work with test data may create/flag test records. --}}
@can('testdata.include')
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_test" value="0">
            <input class="form-check-input" type="checkbox" name="is_test" value="1" id="f_is_test" @checked(old('is_test', $model?->is_test))>
            <label class="form-check-label" for="f_is_test">Test record <span class="text-muted small">(excluded from official reports)</span></label>
        </div>
    </div>
@endcan
