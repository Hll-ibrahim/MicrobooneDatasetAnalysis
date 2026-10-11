<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir olaya (events) bağlı tüm HDF5 tablolarının ortak tabanı.
 */
abstract class EventRecord extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function h5File(): BelongsTo
    {
        return $this->belongsTo(H5File::class);
    }
}
