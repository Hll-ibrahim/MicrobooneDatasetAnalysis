@extends('layouts.app')

@php
    $editing = $event->exists;
    $sections = [
        'Kimlik' => ['run', 'subrun', 'event'],
        'Etkileşim' => ['is_cc', 'nu_pdg', 'nu_energy', 'lep_energy'],
        'Yön' => ['nu_dir_x', 'nu_dir_y', 'nu_dir_z'],
        'Etkileşim noktası' => ['nu_vtx_x', 'nu_vtx_y', 'nu_vtx_z'],
        'Düzeltilmiş etkileşim noktası' => ['nu_vtx_corr_x', 'nu_vtx_corr_y', 'nu_vtx_corr_z'],
        'Tel koordinatları' => ['nu_vtx_wire_pos_u', 'nu_vtx_wire_pos_v', 'nu_vtx_wire_pos_y', 'nu_vtx_wire_time'],
        'Araştırmacı notları' => ['label', 'notes'],
    ];
    $required = \App\Support\DataTables::get('events')['required'];
@endphp

@section('title', $editing ? 'Olay düzenle '.$event->key() : 'Yeni olay')

@section('header')
    <x-page-header :title="$editing ? 'Olay düzenle: '.$event->key() : 'Yeni olay'" icon="bi-pencil-square"
                   subtitle="events tablosundaki bir satırı web arayüzünden düzenleyin">
        @if($editing)
            <a href="{{ route('events.show', $event) }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Olaya dön</a>
        @else
            <a href="{{ route('events.index') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Listeye dön</a>
        @endif
    </x-page-header>
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('events.update', $event) : route('events.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card card-royal mb-3">
            <div class="card-header">Dosya</div>
            <div class="card-body">
                <label class="form-label" for="h5_file_id">Ait olduğu H5 dosyası <span class="text-danger">*</span></label>
                <select class="form-select @error('h5_file_id') is-invalid @enderror" id="h5_file_id" name="h5_file_id" required>
                    <option value="">Seçin…</option>
                    @foreach($files as $f)
                        <option value="{{ $f->id }}" @selected((int) old('h5_file_id', $event->h5_file_id) === $f->id)>{{ $f->name }}</option>
                    @endforeach
                </select>
                @error('h5_file_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row g-3">
            @foreach($sections as $title => $cols)
                <div class="{{ in_array($title, ['Araştırmacı notları', 'Tel koordinatları'], true) ? 'col-lg-6' : 'col-lg-6 col-xl-4' }}">
                    <div class="card card-royal h-100">
                        <div class="card-header">{{ $title }}</div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($cols as $col)
                                    <div class="{{ $columns[$col][1] === 'text' ? 'col-12' : 'col-sm-6' }}">
                                        @include('partials.field', [
                                            'name' => $col,
                                            'meta' => $columns[$col],
                                            'value' => $event->getAttribute($col),
                                            'required' => in_array($col, $required, true),
                                        ])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> {{ $editing ? 'Değişiklikleri kaydet' : 'Olayı oluştur' }}</button>
            <a href="{{ $editing ? route('events.show', $event) : route('events.index') }}" class="btn btn-outline-secondary btn-lg">Vazgeç</a>
        </div>
        @if($editing)
            <p class="small text-muted mt-3"><i class="bi bi-info-circle"></i>
                Not: Burada yapılan değişiklikler yalnızca PostgreSQL'deki kopyayı etkiler; orijinal .h5 dosyası değişmez.</p>
        @endif
    </form>
@endsection
