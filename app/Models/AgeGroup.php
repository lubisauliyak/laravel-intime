<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgeGroup extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'min_age',
        'max_age',
    ];

    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get badge color based on sort_order.
     */
    public function getColor(): string
    {
        return match ($this->sort_order) {
            1 => 'info',
            2 => 'warning',
            3 => 'success',
            4 => 'danger',
            5 => 'primary',
            default => 'gray',
        };
    }

    /**
     * Get a mapping of all age group names to their colors.
     * Cached for performance.
     */
    public static function getColorMapping(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('age_group_colors_v1', 3600, function () {
            return self::all()->mapWithKeys(function ($group) {
                return [$group->name => $group->getColor()];
            })->toArray();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('age_group_colors_v1'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('age_group_colors_v1'));
        static::restored(fn () => \Illuminate\Support\Facades\Cache::forget('age_group_colors_v1'));
    }
}
