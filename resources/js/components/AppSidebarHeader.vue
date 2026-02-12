<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { 
    Search, Bell, MessageSquare, Maximize, Minimize, 
    User, Settings, X, Send, CheckCircle2 
} from 'lucide-vue-next';

// 1. Updated Breadcrumbs
const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [
            { title: 'Dashboard', href: '#' },
            { title: 'Mail Box', href: '#' }
        ],
    },
);

// --- Toggle States ---
const isChatOpen = ref(false);
const isNotificationsOpen = ref(false);
const isFullscreen = ref(false);

// --- Fullscreen Logic ---
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

const syncFullscreen = () => isFullscreen.value = !!document.fullscreenElement;

onMounted(() => document.addEventListener('fullscreenchange', syncFullscreen));
onUnmounted(() => document.removeEventListener('fullscreenchange', syncFullscreen));

// Mock Data
const notifications = [
    { id: 1, title: 'New Mail', text: 'Invoice #4421 received', time: '2m ago' },
    { id: 2, title: 'System', text: 'Storage is 80% full', time: '1h ago' },
];
</script>

<template>
    <Head title="Mail Box" />

    <header
        class="flex h-16 shrink-0 items-center justify-between w-full border-b border-sidebar-border/70 px-6 bg-white sticky top-0 z-40 transition-all"
    >
        <div class="flex items-center gap-4">
            <SidebarTrigger class="-ml-1" />
            
            <div class="relative w-64 hidden md:block">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <Search class="w-4 h-4 text-gray-400" />
                </span>
                <input 
                    type="text" 
                    placeholder="Search messages..." 
                    class="w-full py-2 pl-10 pr-4 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all"
                />
            </div>

            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="h-4 w-px bg-gray-300 mx-2 hidden lg:block"></div>
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center space-x-2 md:space-x-4">
            
            <button 
                @click="isChatOpen = !isChatOpen; isNotificationsOpen = false"
                class="p-2 text-gray-500 hover:bg-gray-100 rounded-full relative transition-colors"
                :class="{ 'bg-blue-50 text-blue-600': isChatOpen }"
            >
                <MessageSquare class="w-5 h-5" />
            </button>

            <div class="relative">
                <button 
                    @click="isNotificationsOpen = !isNotificationsOpen; isChatOpen = false"
                    class="p-2 text-gray-500 hover:bg-gray-100 rounded-full relative transition-colors"
                    :class="{ 'bg-blue-50 text-blue-600': isNotificationsOpen }"
                >
                    <Bell class="w-5 h-5" />
                    <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                </button>

                <div v-if="isNotificationsOpen" class="absolute right-0 mt-3 w-80 bg-white shadow-2xl rounded-xl border border-gray-200 z-50 overflow-hidden animate-in fade-in zoom-in duration-200">
                    <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                        <span class="font-bold text-sm text-gray-700">Notifications</span>
                        <CheckCircle2 class="w-4 h-4 text-blue-500" />
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <div v-for="n in notifications" :key="n.id" class="p-4 border-b hover:bg-gray-50 cursor-pointer">
                            <p class="text-sm font-semibold text-gray-800">{{ n.title }}</p>
                            <p class="text-xs text-gray-500">{{ n.text }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">{{ n.time }}</p>
                        </div>
                    </div>
                    <button class="w-full py-3 text-xs text-blue-600 font-medium hover:bg-gray-50">Mark all as read</button>
                </div>
            </div>

            <button @click="toggleFullscreen" class="p-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors">
                <component :is="isFullscreen ? Minimize : Maximize" class="w-5 h-5" />
            </button>
            
            <div class="h-6 w-px bg-gray-200 mx-1"></div>

            <div class="flex items-center space-x-3 cursor-pointer group">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold text-gray-900 leading-tight">John Doe</p>
                    <p class="text-[11px] text-gray-500">Administrator</p>
                </div>
                <div class="w-9 h-9 bg-gray-800 text-white rounded-full flex items-center justify-center font-bold text-sm border-2 border-white shadow-sm">
                    JD
                </div>
                <button class="p-1 text-gray-400 group-hover:text-blue-600 transition-colors">
                    <Settings class="w-5 h-5" />
                </button>
            </div>
        </div>
    </header>

    <Teleport to="body">
        <div 
            v-if="isChatOpen"
            class="fixed bottom-6 right-6 w-80 h-96 bg-white shadow-[0_20px_50px_rgba(8,_112,_184,_0.7)] rounded-2xl border border-gray-200 flex flex-col z-[60] overflow-hidden transition-all animate-in slide-in-from-bottom-4"
        >
            <div class="p-4 bg-blue-600 text-white flex justify-between items-center shadow-md">
                <span class="font-bold">Messages</span>
                <button @click="isChatOpen = false"><X class="w-4 h-4 hover:scale-110 transition-transform" /></button>
            </div>
            <div class="flex-1 p-4 overflow-y-auto bg-gray-50 space-y-3">
                <div class="bg-white p-3 rounded-2xl rounded-tl-none shadow-sm text-sm text-gray-700 max-w-[85%] border border-gray-100">
                    Welcome to the help desk!
                </div>
            </div>
            <div class="p-3 bg-white border-t flex gap-2">
                <input type="text" placeholder="Aa" class="flex-1 bg-gray-100 border-none rounded-full px-4 text-sm focus:ring-1 focus:ring-blue-500" />
                <button class="text-blue-600 p-1"><Send class="w-5 h-5" /></button>
            </div>
        </div>
    </Teleport>

    <div 
        v-if="isNotificationsOpen" 
        @click="isNotificationsOpen = false" 
        class="fixed inset-0 z-40 bg-transparent"
    ></div>
</template>

<style scoped>
/* Optional: Adding a smooth slide-in for the chat */
.animate-in {
    animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>