<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip_hash',
        'user_agent_hash',
        'action',
        'job_id',
    ];

    protected function casts(): array
    {
        return [
            'job_id' => 'integer',
        ];
    }
}
