<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'member_code',
        'full_name',
        'nick_name',
        'group_id',
        'birth_date',
        'age',
        'age_group_id',
        'gender',
        'status',
        'membership_type',
        'qr_code_path',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function ageGroup()
    {
        return $this->belongsTo(AgeGroup::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($member) {
            if ($member->birth_date) {
                $birthDate = \Carbon\Carbon::parse($member->birth_date);
                $age = $birthDate->age;
                $member->age = $age;

                // Automatic category matching if not manually set OR if it's a new record
                // We prioritize manual selection if provided in the request
                if (!$member->age_group_id) {
                    $matchingGroup = AgeGroup::where('min_age', '<=', $age)
                        ->where(function ($query) use ($age) {
                            $query->where('max_age', '>=', $age)
                                ->orWhereNull('max_age');
                        })
                        ->first();

                    if ($matchingGroup) {
                        $member->age_group_id = $matchingGroup->id;
                    }
                }
            }
        });
    }}
