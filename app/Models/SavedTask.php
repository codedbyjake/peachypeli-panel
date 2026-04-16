<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedTask extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'action',
        'payload',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
