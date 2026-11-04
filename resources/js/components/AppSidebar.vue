<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
    BookOpen, 
    FolderGit2, 
    LayoutGrid, 
    Package, 
    Tags, 
    Boxes, 
    ShoppingCart, 
    CreditCard, 
    Receipt, 
    Wallet, 
    Store,
    PackagePlus, 
    Truck,
    Users
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import type { NavItem } from '@/types';

type MenuItem = NavItem & { feature?: string; locked?: boolean };

const allNavItems: MenuItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Pengaturan Toko', href: '/receipt-settings', icon: Store },
    { title: 'Kategori', href: '/categories', icon: Tags },
    { title: 'Produk', href: '/products', icon: Package },
    { title: 'Stok', href: '/stock', icon: Boxes, feature: 'stock_management' },
    { title: 'Supplier', href: '/suppliers', icon: Truck, feature: 'stock_management' },
    { title: 'Pembelian', href: '/purchases', icon: PackagePlus, feature: 'stock_management' },
    { title: 'Pelanggan', href: '/customers', icon: Users, feature: 'customers' },
    { title: 'Pembayaran', href: '/payment-settings', icon: CreditCard },
    { title: 'Shift', href: '/shifts', icon: Wallet },
    { title: 'Kasir', href: '/pos', icon: ShoppingCart },
    { title: 'Riwayat', href: '/orders', icon: Receipt },
];

const page = usePage<any>();
const features = computed<string[]>(() => page.props.business?.features ?? []);
const mainNavItems = computed(() =>
    allNavItems.map((i) => ({ ...i, locked: !!i.feature && !features.value.includes(i.feature) })),
);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
