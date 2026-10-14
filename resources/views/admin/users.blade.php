<x-admin-layout title="Pengguna">

    @php
        $roleTabs = ['semua' => 'Semua'] + \App\Models\User::ROLE_LABELS;
        $roleBadge = [
            'admin'   => 'bg-slate-900 text-white',
            'penjual' => 'bg-emerald-100 text-emerald-800',
            'pembeli' => 'bg-sky-100 text-sky-800',
        ];
        $kycLabel = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Verifikasi ditolak'];
    @endphp

    <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Pengguna</h1>
        <p class="text-sm text-slate-500 mt-0.5">Semua akun thriftLO. Akun yang dinonaktifkan tidak bisa masuk sampai diaktifkan lagi.</p>
    </div>

    <!-- Tab peran -->
    <nav class="flex overflow-x-auto no-scrollbar border-b border-slate-200" aria-label="Filter peran">
        @foreach($roleTabs as $key => $label)
            @php $count = $key === 'semua' ? $roleCounts->sum() : ($roleCounts[$key] ?? 0); @endphp
            <a href="{{ route('admin.users', array_filter(['role' => $key === 'semua' ? null : $key, 'status' => $status === 'semua' ? null : $status, 'q' => $search ?: null])) }}"
               class="shrink-0 px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px {{ $role === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
               @if($role === $key) aria-current="page" @endif>
                {{ $label }} <span class="ml-1 text-xs font-bold px-1.5 py-0.5 rounded-full {{ $role === $key ? 'bg-emerald-100' : 'bg-slate-200/70' }}">{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    <!-- Pencarian & status -->
    <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col sm:flex-row gap-2" role="search">
        @if($role !== 'semua')
            <input type="hidden" name="role" value="{{ $role }}">
        @endif
        <label for="q" class="sr-only">Cari nama atau email</label>
        <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau nama toko"
               class="flex-1 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        <label for="status" class="sr-only">Status akun</label>
        <select id="status" name="status" class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="h-[38px] px-4 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-semibold">Cari</button>
    </form>

    <!-- Daftar akun -->
    <section class="rounded-xl bg-white border border-slate-200 overflow-hidden" aria-label="Daftar pengguna">
        <ul class="divide-y divide-slate-100">
            @forelse($users as $account)
                <li class="p-4 flex flex-col sm:flex-row sm:items-center gap-3 {{ $account->isDeactivated() ? 'bg-slate-50' : '' }}">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <x-avatar :user="$account" size="md" class="{{ $account->isDeactivated() ? 'opacity-50' : '' }}" />
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 truncate">
                                {{ $account->name }}
                                @if($account->role === 'penjual' && $account->nama_toko)
                                    <span class="font-normal text-slate-500">· {{ $account->nama_toko }}</span>
                                @endif
                            </p>
                            <p class="text-sm text-slate-500 truncate">{{ $account->email }}</p>
                            <div class="mt-1 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                <span class="px-2 py-0.5 rounded-full {{ $roleBadge[$account->role] ?? 'bg-slate-100 text-slate-700' }}">{{ $account->roleLabel() }}</span>
                                @if($account->role === 'penjual')
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $kycLabel[$account->seller_status] ?? $account->seller_status }}</span>
                                @endif
                                @if($account->isDeactivated())
                                    <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800">Nonaktif sejak {{ $account->deactivated_at->locale('id')->translatedFormat('d M Y') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                                @endif
                                <span class="px-2 py-0.5 text-slate-400 font-normal">Daftar {{ $account->created_at->locale('id')->translatedFormat('d M Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="sm:shrink-0 flex justify-end">
                        @if($account->is(auth()->user()))
                            <span class="text-xs text-slate-400">Akun kamu</span>
                        @elseif($account->isDeactivated())
                            <form action="{{ route('admin.users.activate', $account) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Aktifkan</button>
                            </form>
                        @else
                            <form action="{{ route('admin.users.deactivate', $account) }}" method="POST"
                                  onsubmit="return confirm('Nonaktifkan akun {{ addslashes($account->name) }}? Pengguna ini akan langsung keluar dan tidak bisa masuk.')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="h-9 px-4 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold">Nonaktifkan</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-slate-500">Tidak ada akun yang cocok dengan pencarian.</li>
            @endforelse
        </ul>
        @if($users->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $users->links() }}</div>
        @endif
    </section>
</x-admin-layout>
