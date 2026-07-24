@props([
    'description',
    'category' => 'Umum',
    'date',
    'amount',
    'type' => 'income', // 'income' | 'expense'
])

<div class="flex items-center justify-between p-3 rounded-md bg-surface-alt border border-border/40 hover:border-border transition">
    <div class="flex items-center gap-3">
        <div class="size-9 rounded-full {{ $type === 'income' ? 'bg-success-bg text-success' : 'bg-muted text-on-surface-variant' }} flex items-center justify-center text-sm font-bold">
            {{ $type === 'income' ? '+' : '-' }}
        </div>
        <div>
            <p class="text-sm font-semibold text-on-surface">{{ $description }}</p>
            <p class="text-xs text-on-surface-variant">{{ $category }} • {{ $date }}</p>
        </div>
    </div>
    <span class="text-sm font-bold {{ $type === 'income' ? 'text-success' : 'text-on-surface' }}">
        {{ $type === 'income' ? '+' : '-' }} Rp {{ is_numeric($amount) ? number_format($amount, 0, ',', '.') : $amount }}
    </span>
</div>
