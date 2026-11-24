<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Level extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'level_number',
    ];

    public function groups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Get human-readable level name (e.g. "Daerah", "Desa", "Kelompok").
     */
    public function getDisplayNameAttribute(): string
    {
        return Str::title(Str::lower($this->name));
    }

    /**
     * Get badge color based on level_number.
     */
    public function getColor(): string
    {
        return match (($this->level_number ?? 0) % 5) {
            1 => 'success',
            2 => 'warning',
            3 => 'danger',
            4 => 'info',
            0 => 'primary',
            default => 'gray',
        };
    }

    /**
     * Get a mapping of all level names to their colors.
     * Cached for performance.
     */
    public static function getColorMapping(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('level_colors_v1', 3600, function () {
            return self::all()->mapWithKeys(function ($level) {
                return [$level->name => $level->getColor()];
            })->toArray();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('level_colors_v1'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('level_colors_v1'));
        static::restored(fn () => \Illuminate\Support\Facades\Cache::forget('level_colors_v1'));
    }
}
