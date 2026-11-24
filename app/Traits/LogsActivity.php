<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\User;
use App\Support\ActivityLogDictionary;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            if ($model->shouldLogActivityEvent('created')) {
                $model->recordActivity('created');
            }
        });

        static::updated(function (Model $model) {
            if ($model->shouldLogActivityEvent('updated')) {
                $model->recordActivity('updated');
            }
        });

        static::deleted(function (Model $model) {
            if ($model->shouldLogActivityEvent('deleted')) {
                $model->recordActivity('deleted');
            }
        });
    }

    /**
     * Tentukan apakah event ini perlu dicatat.
     */
    public function shouldLogActivityEvent(string $event): bool
    {
        $allowedEvents = method_exists($this, 'getLoggedEvents')
            ? $this->getLoggedEvents()
            : ['created', 'updated', 'deleted'];

        if (! in_array($event, $allowedEvents)) {
            return false;
        }

        // Jika event updated tetapi tidak ada atribut dirty yang signifikan, abaikan
        if ($event === 'updated') {
            $dirty = $this->getFilteredDirtyAttributes();

            return ! empty($dirty);
        }

        return true;
    }

    /**
     * Dapatkan daftar atribut yang dikecualikan dari log aktivitas.
     */
    public function getIgnoredActivityAttributes(): array
    {
        $defaultIgnored = [
            'password',
            'remember_token',
            'updated_at',
            'created_at',
            'deleted_at',
            'qr_code_path',
            'profile_photo_path',
            'membership_card_photo_path',
            'evidence_path',
        ];

        if (property_exists($this, 'ignoredActivityAttributes') && is_array($this->ignoredActivityAttributes)) {
            return array_merge($defaultIgnored, $this->ignoredActivityAttributes);
        }

        return $defaultIgnored;
    }

    /**
     * Ambil perubahan atribut (dirty) yang telah disanitasi.
     */
    protected function getFilteredDirtyAttributes(): array
    {
        $dirty = $this->getDirty();
        $ignored = $this->getIgnoredActivityAttributes();

        return array_diff_key($dirty, array_flip($ignored));
    }

    /**
     * Rekam jejak aktivitas ke dalam basis data.
     */
    public function recordActivity(string $event): ?ActivityLog
    {
        try {
            $ignored = $this->getIgnoredActivityAttributes();
            $properties = [
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ];

            $oldData = [];
            $newData = [];

            if ($event === 'created') {
                $newData = array_diff_key($this->getAttributes(), array_flip($ignored));
                $properties['attributes'] = $newData;
            } elseif ($event === 'updated') {
                $dirty = $this->getFilteredDirtyAttributes();
                if (empty($dirty)) {
                    return null;
                }

                foreach (array_keys($dirty) as $key) {
                    $oldData[$key] = $this->getOriginal($key);
                    $newData[$key] = $this->getAttribute($key);
                }

                $properties['old'] = $oldData;
                $properties['attributes'] = $newData;
            } elseif ($event === 'deleted') {
                $oldData = array_diff_key($this->getOriginal(), array_flip($ignored));
                $properties['old'] = $oldData;
            }

            // Tentukan group_id untuk multi-tenancy scoping
            $groupId = $this->resolveActivityGroupId();

            // Tentukan causer (pelaku)
            $causer = auth()->user();
            $causerType = $causer ? get_class($causer) : null;
            $causerId = $causer?->getKey();
            $causerName = $causer?->name ?? 'Sistem';

            // Nama subject
            $subjectName = $this->resolveActivitySubjectName();

            // Bangun deskripsi humanis
            $description = ActivityLogDictionary::buildDescription(
                $event,
                $this,
                $newData ?: null,
                $oldData ?: null
            );

            return ActivityLog::create([
                'group_id' => $groupId,
                'causer_type' => $causerType,
                'causer_id' => $causerId,
                'causer_name' => $causerName,
                'subject_type' => static::class,
                'subject_id' => $this->getKey(),
                'subject_name' => $subjectName,
                'event' => $event,
                'description' => $description,
                'properties' => $properties,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Jangan biarkan kegagalan pencatatan audit trail menghentikan alur transaksi utama
            report($e);

            return null;
        }
    }

    /**
     * Resolusi group_id dari entitas atau user yang sedang login.
     */
    protected function resolveActivityGroupId(): ?int
    {
        if (isset($this->group_id) && $this->group_id) {
            return (int) $this->group_id;
        }

        if ($this instanceof Group) {
            return (int) $this->getKey();
        }

        if ($this instanceof Attendance) {
            return (int) ($this->meeting?->group_id ?? $this->member?->group_id ?? null);
        }

        if ($this instanceof User && $this->group_id) {
            return (int) $this->group_id;
        }

        return auth()->user()?->group_id ? (int) auth()->user()->group_id : null;
    }

    /**
     * Resolusi nama/identitas subjek yang mudah dibaca.
     */
    protected function resolveActivitySubjectName(): string
    {
        if (isset($this->full_name) && $this->full_name) {
            return (string) $this->full_name;
        }

        if (isset($this->name) && $this->name) {
            return (string) $this->name;
        }

        if ($this instanceof Attendance && $this->member) {
            return (string) $this->member->full_name;
        }

        return class_basename($this)." #{$this->getKey()}";
    }
}
