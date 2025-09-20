<?php

namespace MohamedHathout\Debugger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Text extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    public function debug(): MorphOne
    {
        return $this->morphOne(Debug::class, 'debug');
    }
}
