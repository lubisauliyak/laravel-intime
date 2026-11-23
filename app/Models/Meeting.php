<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

/**
 * @property \Illuminate\Support\Carbon $meeting_date
 * @property \Illuminate\Support\Carbon|null $start_time
 * @property \Illuminate\Support\Carbon|null $checkin_open_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property \App\Enums\MeetingTargetGender $target_gender
 */
class Meeting extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingFactory> */
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'uid',
        'name',
        'description',
        'meeting_date',
        'start_time',
        'checkin_open_time',
        'end_time',
        'group_id',
        'target_gender',
        'target_age_groups',
        'is_permission_template_active',
        'is_permission_enabled',
        'is_presence_enabled',
        'permission_opening_text',
        'permission_statement_text',
        'permission_closing_text',
        'created_by',
    ];

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::ulid();
            }
        });
    }

    protected $casts = [
        'meeting_date' => 'date',
        'start_time' => 'datetime:H:i',
        'checkin_open_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'target_age_groups' => 'array',
        'target_gender' => \App\Enums\MeetingTargetGender::class,
        'is_permission_template_active' => 'boolean',
        'is_permission_enabled' => 'boolean',
        'is_presence_enabled' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function pengurusAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->whereHas('member', function ($query) {
            $query->where('membership_type', \App\Enums\MembershipType::PENGURUS->value)
                  ->orWhereHas('positions');
        });
    }

    public function getTargetGroupIds(): array
    {
        return $this->group->getAllDescendantIds();
    }

    public function isExpired(): bool
    {
        if (!$this->meeting_date || !$this->end_time) {
            return false;
        }

        $endDateTime = $this->meeting_date->copy()->setTimeFrom($this->end_time);
        
        return now()->isAfter($endDateTime);
    }

    /**
     * Check if check-in/attendance is currently open.
     * Uses checkin_open_time if set, otherwise defaults to start_time.
     */
    public function isCheckinOpen(): bool
    {
        if (!$this->meeting_date || !$this->meeting_date->isToday()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        $openTime = $this->checkin_open_time ?? $this->start_time;
        if ($openTime) {
            $openDateTime = $this->meeting_date->copy()->setTimeFrom($openTime);
            return now()->isAfter($openDateTime);
        }

        return true;
    }
}
