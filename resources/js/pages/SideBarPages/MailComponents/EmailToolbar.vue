<script setup lang="ts">
import { Filter, RefreshCcw, SlidersHorizontal } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineProps<{
    currentFolder: string;
    visibleCount: number;
    totalCount: number;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-card/80 px-4 py-3 backdrop-blur-sm">
        <div class="flex items-center gap-2">
            <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px]">
                {{ currentFolder }}
            </Badge>
            <p class="text-xs text-muted-foreground">
                Showing <span class="font-semibold text-foreground">{{ visibleCount }}</span>
                of {{ totalCount }}
            </p>
        </div>

        <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon-sm" title="Refresh">
                <RefreshCcw class="h-4 w-4 text-muted-foreground" />
            </Button>
            <Button variant="ghost" size="icon-sm" title="Sort">
                <SlidersHorizontal class="h-4 w-4 text-muted-foreground" />
            </Button>
            <Button variant="ghost" size="icon-sm" title="Filter">
                <Filter class="h-4 w-4 text-muted-foreground" />
            </Button>
        </div>
    </div>
</template>
