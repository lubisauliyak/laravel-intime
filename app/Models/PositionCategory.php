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

    /**
     * Get badge color based on sort_order.
     */
    public function getColor(): string
    {
        return match ($this->sort_order) {
            1 => 'emerald',
            2 => 'amber',
            3 => 'rose',
            4 => 'info',
            5 => 'primary',
            default => 'gray',
        };
    }

    /**
     * Get a mapping of all position category names to their colors.
     * Cached for performance.
     */
    public static function getColorMapping(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('position_category_colors_v1', 3600, function () {
            return self::all()->mapWithKeys(function ($cat) {
                return [$cat->name => $cat->getColor()];
            })->toArray();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('position_category_colors_v1'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('position_category_colors_v1'));
        static::restored(fn () => \Illuminate\Support\Facades\Cache::forget('position_category_colors_v1'));
    }
}
