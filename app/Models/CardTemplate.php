<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    use HasUlids;

    /**
     * Override the default newUniqueId to generate uppercase ULIDs
     */
    public function newUniqueId(): string
    {
        return (string) \Illuminate\Support\Str::ulid();
    }

    protected $fillable = [
        'name',
        'description',
        'background_path',
        'canvas_width',
        'canvas_height',
        'elements',
        'preview_path',
        'is_active',
    ];

    protected $casts = [
        'canvas_width' => 'integer',
        'canvas_height' => 'integer',
        'elements' => 'array',
        'is_active' => 'boolean',
    ];

    public function groups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Group::class, 'card_template_id');
    }
}
