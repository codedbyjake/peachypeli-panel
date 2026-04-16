<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalledPlugin extends Model
{
    protected $fillable = [
        'server_id',
        'source',
        'plugin_id',
        'name',
        'version',
        'file_name',
        'install_dir',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
