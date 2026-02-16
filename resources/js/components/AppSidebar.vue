<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, Inbox, LayoutGrid, MessageCircle } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    }, 
    {
        title: 'Mail',
        href: '/mail',
        icon: Inbox,
    }, 
    {
        title: 'Quizzes',
        href: '/quiz',
        icon: BookOpen,
    }, 
    {
        title: 'Group Chat',
        href: '/groupchat',
        icon: MessageCircle,
    }, 
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="animate-fade-in-up border-r border-sidebar-border/70 bg-sidebar/85 backdrop-blur-md">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="rounded-xl border border-sidebar-border/60 bg-sidebar-accent/40 hover:bg-sidebar-accent">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="stagger-children">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/70 pt-3">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
