<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    {{-- 'myeditorinstance' class rehne do, taaki hum JS mein target kar sakein --}}
    <textarea id="{{ $id }}" name="{{ $name }}" class="form-control myeditorinstance" placeholder="{{ $placeholder }}">{{ $value }}</textarea>
    @if ($errors->has($name))
        <div class="invalid-feedback d-block">{{ $errors->first($name) }}</div>
    @endif
</div>