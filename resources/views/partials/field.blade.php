{{-- Tek bir form alanı: $name, $meta = [etiket, tür, birim?, azami?], $value --}}
@php
    $label = $meta[0];
    $type = $meta[1];
    $unit = $meta[2] ?? null;
    $val = old($name, $value);
    $fid = 'f_'.$name;
    if (is_bool($val)) {
        $val = $val ? '1' : '0';
    }
@endphp
<label class="form-label" for="{{ $fid }}">
    {{ $label }} @if($unit)<small class="text-muted fw-normal">[{{ $unit }}]</small>@endif
    @if(! empty($required))<span class="text-danger">*</span>@endif
</label>
@if($type === 'bool')
    <select class="form-select @error($name) is-invalid @enderror" id="{{ $fid }}" name="{{ $name }}">
        <option value="" @selected($val === null || $val === '')>—</option>
        <option value="1" @selected((string) $val === '1')>Evet</option>
        <option value="0" @selected((string) $val === '0')>Hayır</option>
    </select>
@elseif($type === 'text')
    <textarea class="form-control @error($name) is-invalid @enderror" id="{{ $fid }}" name="{{ $name }}" rows="3">{{ $val }}</textarea>
@elseif($type === 'string')
    <input type="text" class="form-control @error($name) is-invalid @enderror" id="{{ $fid }}" name="{{ $name }}"
           value="{{ $val }}" maxlength="{{ $meta[3] ?? 64 }}" @if(! empty($required)) required @endif>
@else
    <input type="number" step="{{ $type === 'int' ? '1' : 'any' }}" class="form-control @error($name) is-invalid @enderror"
           id="{{ $fid }}" name="{{ $name }}" value="{{ $val }}" @if(! empty($required)) required @endif>
@endif
@error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
<div class="form-text font-mono small">{{ $name }}</div>
