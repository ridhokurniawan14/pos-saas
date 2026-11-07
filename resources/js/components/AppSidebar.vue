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
    Users,
    Banknote,
    TrendingUp,
    ShieldCheck
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

type MenuItem = NavItem & { feature?: string; locked?: boolean; area?: string };

const allNavItems: MenuItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Kasir', href: '/pos', icon: ShoppingCart, area: 'pos' },
    { title: 'Riwayat', href: '/orders', icon: Receipt, area: 'orders' },
    { title: 'Shift', href: '/shifts', icon: Wallet, area: 'shifts' },
    { title: 'Produk', href: '/products', icon: Package, area: 'products' },
    { title: 'Kategori', href: '/categories', icon: Tags, area: 'categories' },
    { title: 'Stok', href: '/stock', icon: Boxes, area: 'stock', feature: 'stock_management' },
    { title: 'Supplier', href: '/suppliers', icon: Truck, area: 'suppliers', feature: 'stock_management' },
    { title: 'Pembelian', href: '/purchases', icon: PackagePlus, area: 'purchases', feature: 'stock_management' },
    { title: 'Pelanggan', href: '/customers', icon: Users, area: 'customers', feature: 'customers' },
    { title: 'Laporan', href: '/reports', icon: TrendingUp, area: 'reports' },
    { title: 'Pengeluaran', href: '/expenses', icon: Banknote, area: 'expenses', feature: 'advanced_report' },
    { title: 'Pembayaran', href: '/payment-settings', icon: CreditCard, area: 'payments' },
    { title: 'Pengaturan Toko', href: '/receipt-settings', icon: Store, area: 'settings' },
    { title: 'Pengguna', href: '/users', icon: ShieldCheck, area: 'users' },
];

const page = usePage<any>();
const features = computed<string[]>(() => page.props.business?.features ?? []);
const permissions = computed<string[]>(() => page.props.business?.permissions ?? []);
const mainNavItems = computed(() =>
    allNavItems
        .filter((i) => !i.area || permissions.value.includes(i.area))
        .map((i) => ({ ...i, locked: !!i.feature && !features.value.includes(i.feature) })),
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
