<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZktecoRawLog extends Model
{
    protected $table = 'zkteco_raw_logs';

    protected $fillable = [
        'device_sn',
        'endpoint',
        'method',
        'ip',
        'user_agent',
        'content_type',
        'query_params',
        'form_params',
        'headers',
        'raw_body',
    ];

    protected $casts = [
        'query_params' => 'array',
        'form_params' => 'array',
        'headers' => 'array',
    ];
}
