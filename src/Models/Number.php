<?php

namespace MohamedHathout\Debugger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Number extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'is_int',
    ];

    protected $appends = ['value'];

    protected $hidden = ['number'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'number' => 'decimal:8',
            'is_int' => 'boolean',
        ];
    }

    public function debug(): MorphOne
    {
        return $this->morphOne(Debug::class, 'debug');
    }

    public function getValueAttribute()
    {
        if ($this->is_int) {
            return (int) $this->number;
        }

        return (float) $this->number;
    }
}
