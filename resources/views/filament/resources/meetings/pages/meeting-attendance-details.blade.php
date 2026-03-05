<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->getSchema('infolist') }}
        
        @php
            $meeting = $this->meeting;
            $group = $this->group;
            $descendantIds = $group->getAllDescendantIds();
        @endphp
        
        <div style="margin-top: 24px;">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
