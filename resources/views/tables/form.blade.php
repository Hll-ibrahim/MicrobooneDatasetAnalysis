@extends('layouts.app')

@php($editing = $row !== null)

@section('title', ($editing ? 'Düzenle: ' : 'Yeni: ').$def['singular'])

@section('header')
    <x-page-header :title="$editing ? $def['singular'].' #'.$row->id : 'Yeni '.mb_strtolower($def['singular'])" :icon="$def['icon']"
                   :subtitle="$def['group'].' → '.$table">
        <a href="{{ route('tables.index', ['table' => $table, 'event' => $eventId]) }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Tabloya dön</a>
        @if($eventId)
            <a href="{{ route('events.show', $eventId) }}" class="btn btn-outline-primary"><i class="bi bi-bullseye"></i> Olay</a>
        @endif
    </x-page-header>
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('tables.update', ['table' => $table, 'id' => $row->id]) : route('tables.store', $table) }}">
        @csrf
        @if($editing)
            @method('PUT')
            <input type="hidden" name="back" value="{{ url()->previous() }}">
        @endif

        <div class="card card-royal">
            <div class="card-header d-flex justify-content-between">
                <span>{{ $def['label'] }}</span>
                <small class="text-muted">{{ $def['description'] }}</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="event_id">Olay (events.id) <span class="text-danger">*</span></label>
                        @if($editing)
                            <input class="form-control" id="event_id" value="{{ $row->event_id }}" disabled>
                            <div class="form-text">Bir kaydın olayı değiştirilemez.</div>
                        @else
                            <input type="number" class="form-control @error('event_id') is-invalid @enderror" id="event_id" name="event_id"
                                   value="{{ old('event_id', $eventId) }}" required>
                            @error('event_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                    </div>
                    @foreach($def['columns'] as $col => $meta)
                        <div class="col-sm-6 col-lg-3">
                            @include('partials.field', [
                                'name' => $col,
                                'meta' => $meta,
                                'value' => $values[$col] ?? null,
                                'required' => in_array($col, $def['required'] ?? [], true),
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save"></i> {{ $editing ? 'Kaydet' : 'Ekle' }}</button>
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Vazgeç</a>
            </div>
        </div>
    </form>

    @if($editing)
        <form method="POST" action="{{ route('tables.destroy', ['table' => $table, 'id' => $row->id]) }}" class="mt-3"
              data-confirm="{{ $def['singular'] }} #{{ $row->id }} silinsin mi?">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Bu kaydı sil</button>
        </form>
    @endif
@endsection
