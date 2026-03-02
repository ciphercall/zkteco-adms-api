<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZktecoDeviceUser extends Model
{
    protected $table = 'zkteco_device_users';

    protected $fillable = [
        'device_sn',
        'pin',
        'name',
        'privilege',
        'card_no',
        'group_id',
        'disabled',
        'face_template',
        'fingerprint_template',
    ];

    protected $casts = [
        'disabled' => 'boolean',
    ];
}
