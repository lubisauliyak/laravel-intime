<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PositionCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function memberPositions(): HasMany
    {
        return $this->hasMany(MemberPosition::class);
    }
}
