<script setup lang="ts">
import { Square, RefreshCcw, MoreVertical, ChevronLeft, ChevronRight } from 'lucide-vue-next';
</script>

<template>
    <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-white">
        <div class="flex items-center gap-4">
            <button class="p-2 hover:bg-gray-100 rounded-md">
                <Square class="w-4 h-4 text-gray-400" />
            </button>
            <button class="p-2 hover:bg-gray-100 rounded-md">
                <RefreshCcw class="w-4 h-4 text-gray-400" />
            </button>
            <button class="p-2 hover:bg-gray-100 rounded-md">
                <MoreVertical class="w-4 h-4 text-gray-400" />
            </button>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <span>1-50 of 1,240</span>
            <div class="flex gap-1">
                <button class="p-1 hover:bg-gray-100 rounded">
                    <ChevronLeft class="w-4 h-4" />
                </button>
                <button class="p-1 hover:bg-gray-100 rounded">
                    <ChevronRight class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>
