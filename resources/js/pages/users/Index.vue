<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Minus } from '@lucide/vue';
import { ref } from 'vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Pengguna', href: '/users' }] } });

type Member = { id: number; name: string; email: string; role: string; joined: string | null; is_me: boolean };

defineProps<{
    members: Member[];
    pending: { id: string; email: string; role: string; expires: string }[];
    usage: Record<string, { used: number; limit: number }>;
    matrix: Record<string, string[]>;
    plan: string | null;
}>();

const roleLabel: Record<string, string> = { owner: 'Pemilik', admin: 'Admin', kasir: 'Kasir', gudang: 'Staf gudang' };
const areaLabel: Record<string, string> = {
    pos: 'Kasir', orders: 'Riwayat transaksi', shifts: 'Shift', customers: 'Pelanggan', products: 'Produk',
    categories: 'Kategori', stock: 'Stok', purchases: 'Pembelian', suppliers: 'Supplier', reports: 'Laporan',
    expenses: 'Pengeluaran', payments: 'Metode pembayaran', settings: 'Pengaturan toko', users: 'Pengguna',
};
const editable = ['admin', 'kasir', 'gudang'];

const limitText = (l: number) => (l === -1 ? 'tak terbatas' : String(l));

const form = useForm({ email: '', role: 'kasir' });
const invite = () => form.post('/users/invitations', { preserveScroll: true, onSuccess: () => form.reset('email') });

const rowError = ref('');
const changeRole = (m: Member, e: Event) => {
    rowError.value = '';
    const role = (e.target as HTMLSelectElement).value;
    router.put(`/users/${m.id}`, { role }, {
        preserveScroll: true,
        onError: (errs) => {
            rowError.value = Object.values(errs)[0] as string;
            (e.target as HTMLSelectElement).value = m.role;
        },
    });
};

const target = ref<Member | null>(null);
const remove = () => {
    if (!target.value) return;
    rowError.value = '';
    router.delete(`/users/${target.value.id}`, {
        preserveScroll: true,
        onError: (errs) => (rowError.value = Object.values(errs)[0] as string),
    });
    target.value = null;
};

const cancelInvite = (id: string) => router.delete(`/users/invitations/${id}`, { preserveScroll: true });

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Pengguna" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Pengguna</h1>
            <p class="text-sm text-neutral-500">Atur siapa yang boleh memakai toko ini dan menu apa yang bisa mereka buka.</p>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div v-for="r in ['admin', 'kasir', 'gudang']" :key="r" class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">{{ roleLabel[r] }}</p>
                <p class="text-lg font-bold">{{ usage[r].used }} <span class="text-sm font-normal text-neutral-500">/ {{ limitText(usage[r].limit) }}</span></p>
            </div>
        </div>
        <p class="-mt-2 text-xs text-neutral-500">Paket {{ plan }}. Undangan yang belum diterima ikut dihitung.</p>

        <form class="space-y-3 rounded-xl border p-4 dark:border-neutral-800" @submit.prevent="invite">
            <p class="text-sm font-medium">Undang pengguna</p>
            <div class="grid gap-3 sm:grid-cols-[1fr_180px]">
                <input v-model="form.email" type="email" placeholder="Email (akun Google atau email mereka)" :class="input" />
                <select v-model="form.role" :class="input">
                    <option v-for="r in editable" :key="r" :value="r">{{ roleLabel[r] }}</option>
                </select>
            </div>
            <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
            <p v-if="form.errors.role" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">{{ form.errors.role }}</p>
            <p class="text-xs text-neutral-500">
                Minta orangnya login (Google atau daftar dengan email) memakai alamat email di atas. Mereka otomatis bergabung. Undangan berlaku 14 hari.
            </p>
            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">Kirim Undangan</button>
            </div>
        </form>

        <p v-if="rowError" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">{{ rowError }}</p>

        <div class="space-y-2">
            <p class="text-sm font-semibold">Anggota</p>
            <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="m in members" :key="m.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ m.name }}<span v-if="m.is_me" class="ml-2 text-xs font-normal text-neutral-500">(kamu)</span></p>
                        <p class="text-xs text-neutral-500">{{ m.email }}<span v-if="m.joined"> · bergabung {{ m.joined }}</span></p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span v-if="m.role === 'owner' || m.is_me" class="rounded-full bg-neutral-500/15 px-3 py-1 text-xs font-medium">{{ roleLabel[m.role] }}</span>
                        <template v-else>
                            <select :value="m.role" class="rounded-lg border bg-transparent px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900 dark:[color-scheme:dark]" @change="changeRole(m, $event)">
                                <option v-for="r in editable" :key="r" :value="r">{{ roleLabel[r] }}</option>
                            </select>
                            <button class="text-red-500" @click="target = m">Hapus</button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="pending.length" class="space-y-2">
            <p class="text-sm font-semibold">Undangan menunggu</p>
            <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="p in pending" :key="p.id" class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium">{{ p.email }}</p>
                        <p class="text-xs text-neutral-500">{{ roleLabel[p.role] }} · berlaku sampai {{ p.expires }}</p>
                    </div>
                    <button class="text-sm text-red-500" @click="cancelInvite(p.id)">Batalkan</button>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-sm font-semibold">Hak akses per peran</p>
            <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">Menu</th>
                            <th v-for="r in ['owner', 'admin', 'kasir', 'gudang']" :key="r" class="px-4 py-3 text-center">{{ roleLabel[r] }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-neutral-800">
                        <tr v-for="(label, area) in areaLabel" :key="area">
                            <td class="px-4 py-2.5">{{ label }}</td>
                            <td v-for="r in ['owner', 'admin', 'kasir', 'gudang']" :key="r" class="px-4 py-2.5 text-center">
                                <Check v-if="matrix[r]?.includes(area as string)" :size="16" class="mx-auto text-green-600" />
                                <Minus v-else :size="16" class="mx-auto text-neutral-400" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-neutral-500">Kasir hanya melihat transaksi miliknya sendiri. Peran kustom tersedia di paket Sultan (menyusul).</p>
        </div>
    </div>

    <ConfirmDialog
        :open="!!target"
        title="Hapus anggota ini?"
        :message="target ? `${target.name} tidak bisa lagi masuk ke toko ini. Riwayat transaksinya tetap tersimpan.` : ''"
        confirm-text="Ya, hapus"
        danger
        @confirm="remove"
        @cancel="target = null"
    />
</template>