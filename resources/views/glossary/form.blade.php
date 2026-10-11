@extends('layouts.app')

@php $editing = $term->exists; @endphp

@section('title', $editing ? 'Terimi düzenle' : 'Yeni terim')

@section('header')
    <x-page-header :title="$editing ? 'Terimi düzenle: '.$term->term : 'Yeni terim'" icon="bi-journal-plus"
                   subtitle="glossary_terms tablosundaki bir kaydı düzenleyin">
        <a href="{{ route('glossary.index') }}{{ $editing ? '#'.$term->slug : '' }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Sözlüğe dön</a>
    </x-page-header>
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('glossary.update', $term) : route('glossary.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="card card-royal">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="term">Terim <span class="text-danger">*</span></label>
                        <input class="form-control @error('term') is-invalid @enderror" id="term" name="term" value="{{ old('term', $term->term) }}" required maxlength="150">
                        @error('term')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="slug">Kısa ad (bağlantı)</label>
                        <input class="form-control font-mono @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $term->slug) }}" maxlength="80" placeholder="boşsa otomatik">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Açıklamalarda <span class="font-mono">&#91;&#91;{{ $term->slug ?: 'kisa-ad' }}&#93;&#93;</span> ile bağlanır.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category">
                            @foreach($categories as $k => $label)
                                <option value="{{ $k }}" @selected(old('category', $term->category) === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="short">Kısa tanım <span class="text-danger">*</span></label>
                        <input class="form-control @error('short') is-invalid @enderror" id="short" name="short" value="{{ old('short', $term->short) }}" required maxlength="500">
                        <div class="form-text">Tek cümle; sayfalarda terimin üzerine gelince ipucu balonunda görünür.</div>
                        @error('short')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="body">Ayrıntılı açıklama</label>
                        <textarea class="form-control" id="body" name="body" rows="6">{{ old('body', $term->body) }}</textarea>
                        <div class="form-text">Başka bir terime bağlantı: <span class="font-mono">&#91;&#91;hit&#93;&#93;</span> ya da <span class="font-mono">&#91;&#91;hit|görünen metin&#93;&#93;</span></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="related">İlgili terimler</label>
                        <select class="form-select" id="related" name="related[]" multiple size="8">
                            @foreach($all as $slug => $name)
                                @continue($slug === $term->slug)
                                <option value="{{ $slug }}" @selected(in_array($slug, old('related', $term->related ?? []), true))>{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Birden çok seçmek için Cmd (Mac) / Ctrl tuşuna basılı tutun.</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-primary"><i class="bi bi-save"></i> {{ $editing ? 'Kaydet' : 'Ekle' }}</button>
            </div>
        </div>
    </form>
@endsection
