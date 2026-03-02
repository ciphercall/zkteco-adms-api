<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZktecoDeviceCommand extends Model
{
    protected $table = 'zkteco_device_commands';

    protected $fillable = [
        'device_sn',
        'pin',
        'channel',
        'command_template',
        'cmd_id',
        'status',
        'ack_payload',
        'sent_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];
}
