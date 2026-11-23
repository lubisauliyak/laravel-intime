<?php

namespace App\Services\Groups;

use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupHierarchyService
{
    /**
     * Get the hierarchy chain from root ancestor down to the specified group (leaf).
     *
     * @param Group $group
     * @return Collection<int, Group>
     */
    public function getHierarchyChain(Group $group): Collection
    {
        $chain = collect();
        $visited = [];
        $curr = $group;

        while ($curr && !isset($visited[$curr->id])) {
            $visited[$curr->id] = true;
            $chain->prepend($curr);

            if (!$curr->parent_id) {
                break;
            }

            $curr = $curr->relationLoaded('parent')
                ? $curr->parent
                : Group::with('level')->find($curr->parent_id);
        }

        return $chain;
    }

    /**
     * Validate chain integrity based on code assignments:
     * - If all groups in chain have empty codes -> valid ('no_code').
     * - If an ancestor has a code, all descendants down to the group MUST have codes -> invalid if broken ('broken_chain').
     * - If chain from first non-empty code to group is continuous -> valid ('valid').
     *
     * @param Group $group
     * @return array{is_valid: bool, status: string, prefix: ?string, message: string, broken_group: ?Group}
     */
    public function validateChainIntegrity(Group $group): array
    {
        $chain = $this->getHierarchyChain($group);

        // Check if all groups in the chain have empty codes
        $allEmpty = $chain->every(fn (Group $g) => empty(trim((string) $g->code)));
        if ($allEmpty) {
            return [
                'is_valid' => true,
                'status' => 'no_code',
                'prefix' => null,
                'message' => 'Seluruh tingkatan grup belum memiliki kode. Anggota akan disimpan tanpa ID.',
                'broken_group' => null,
            ];
        }

        $firstCodeIndex = null;
        $prefix = '';

        foreach ($chain->values() as $index => $g) {
            $code = trim((string) $g->code);

            if ($firstCodeIndex === null) {
                if ($code !== '') {
                    $firstCodeIndex = $index;
                    $prefix .= $code;
                }
            } else {
                // After the first group with code, all subsequent groups MUST have code
                if ($code === '') {
                    $levelName = $g->relationLoaded('level') && $g->level
                        ? $g->level->name
                        : ($g->level?->name ?? 'Tingkat');

                    return [
                        'is_valid' => false,
                        'status' => 'broken_chain',
                        'prefix' => null,
                        'message' => "Rantai kode terputus pada tingkat {$levelName}: {$g->name}. Harap tentukan kodenya terlebih dahulu.",
                        'broken_group' => $g,
                    ];
                }
                $prefix .= $code;
            }
        }

        return [
            'is_valid' => true,
            'status' => 'valid',
            'prefix' => $prefix,
            'message' => 'Rantai kode valid.',
            'broken_group' => null,
        ];
    }

    /**
     * Get the calculated prefix string for a group (e.g. '1007').
     * Returns null if all groups have empty codes or if chain is broken.
     *
     * @param Group $group
     * @return string|null
     */
    public function getCalculatedPrefix(Group $group): ?string
    {
        $validation = $this->validateChainIntegrity($group);

        return ($validation['is_valid'] && $validation['status'] === 'valid')
            ? $validation['prefix']
            : null;
    }

    /**
     * Resolve the effective card template for a group through inheritance:
     * - Checks ancestors from root downwards. If any ancestor sets a template,
     *   all children inherit it and are locked from modifying it.
     * - If no ancestor has a template, returns the group's own template (unlocked).
     *
     * @param Group $group
     * @return array{template: ?CardTemplate, inherited_from: ?Group, is_locked: bool}
     */
    public function getEffectiveCardTemplate(Group $group): array
    {
        $chain = $this->getHierarchyChain($group);

        // Check ancestors (excluding current group itself)
        $ancestors = $chain->slice(0, -1);

        foreach ($ancestors as $ancestor) {
            if ($ancestor->card_template_id) {
                $template = $ancestor->relationLoaded('cardTemplate') && $ancestor->cardTemplate
                    ? $ancestor->cardTemplate
                    : CardTemplate::find($ancestor->card_template_id);

                if ($template) {
                    return [
                        'template' => $template,
                        'inherited_from' => $ancestor,
                        'is_locked' => true,
                    ];
                }
            }
        }

        // If no ancestor has set a template, check current group's own template
        if ($group->card_template_id) {
            $template = $group->relationLoaded('cardTemplate') && $group->cardTemplate
                ? $group->cardTemplate
                : CardTemplate::find($group->card_template_id);

            return [
                'template' => $template,
                'inherited_from' => null,
                'is_locked' => false,
            ];
        }

        return [
            'template' => null,
            'inherited_from' => null,
            'is_locked' => false,
        ];
    }

    /**
     * Atomically generate the next sequential member code for a group.
     * - Returns null if the group chain has no code (safety net).
     * - Throws exception if chain integrity is broken.
     * - Locks the group row in a DB transaction to guarantee race-condition free incrementing.
     * - Format: prefix + 3-digit padded number (e.g. 1007001), expanding to 4+ digits if >= 1000.
     *
     * @param Group $group
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function generateNextMemberCode(Group $group): ?string
    {
        $validation = $this->validateChainIntegrity($group);

        if ($validation['status'] === 'no_code') {
            return null;
        }

        if (!$validation['is_valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        $prefix = $validation['prefix'];

        return DB::transaction(function () use ($group, $prefix) {
            // 1. Lock the group row to serialize concurrent requests for this group
            Group::where('id', $group->id)->lockForUpdate()->first();

            // 2. Query the highest member_code starting with this prefix globally
            // Include soft-deleted records to prevent unique constraint collisions
            $latestMember = Member::withTrashed()
                ->where('member_code', 'like', $prefix . '%')
                ->orderByRaw('LENGTH(member_code) DESC')
                ->orderBy('member_code', 'DESC')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;
            if ($latestMember && !empty($latestMember->member_code)) {
                $suffix = substr($latestMember->member_code, strlen($prefix));
                if (is_numeric($suffix)) {
                    $nextNumber = ((int) $suffix) + 1;
                }
            }

            // At least 3 digits padded (001 - 999), expandable to 4+ digits
            $formattedSuffix = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

            return $prefix . $formattedSuffix;
        });
    }
}
