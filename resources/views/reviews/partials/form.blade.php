{{--
    Ulasan pembeli di kartu transaksi (tiket COD / pesanan kirim): tampilkan ulasan yang sudah ada,
    atau form bintang + komentar. Butuh: $review (Review|null), $action (url), $field ('booking_id'|'order_id'),
    $id (id transaksi), $hint (placeholder komentar).
--}}
@php
    $key = $field . '-' . $id;
    // Error validasi hanya tampil di kartu transaksi yang baru saja dikirim
    $reviewErrors = (string) old($field) === (string) $id ? $errors->review : null;
@endphp
<div class="border-t border-slate-100 px-4 py-3">
    @if($review)
        <div class="flex items-center gap-2">
            @include('reviews.partials.stars', ['rating' => $review->rating, 'size' => 'text-lg'])
            <span class="text-xs text-slate-400">Ulasanmu</span>
        </div>
        @if($review->comment)
            <p class="mt-1 text-sm text-slate-600 break-words">“{{ $review->comment }}”</p>
        @endif
    @else
        <form action="{{ $action }}" method="POST" class="space-y-2">
            @csrf
            <input type="hidden" name="{{ $field }}" value="{{ $id }}">
            <fieldset>
                <legend class="text-sm font-semibold text-slate-800">Beri ulasan untuk penjual</legend>
                <div class="mt-1 flex flex-row-reverse justify-end gap-1">
                    @for($i = 5; $i >= 1; $i--)
                        <input type="radio" id="rating-{{ $key }}-{{ $i }}" name="rating" value="{{ $i }}" class="sr-only [&:checked~label]:text-amber-400 [&:focus-visible+label]:ring-2 [&:focus-visible+label]:ring-emerald-500"
                               @checked($reviewErrors !== null && (int) old('rating') === $i) @if($i === 5) required @endif>
                        <label for="rating-{{ $key }}-{{ $i }}" class="text-3xl leading-none text-slate-300 cursor-pointer rounded hover:text-amber-400 [&:hover~label]:text-amber-400">
                            ★<span class="sr-only">{{ $i }} bintang</span>
                        </label>
                    @endfor
                </div>
            </fieldset>
            <label for="comment-{{ $key }}" class="sr-only">Komentar ulasan</label>
            <textarea id="comment-{{ $key }}" name="comment" rows="2" maxlength="500" placeholder="{{ $hint }}"
                      class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">{{ $reviewErrors !== null ? old('comment') : '' }}</textarea>
            @if($reviewErrors?->any())
                <p class="text-sm text-rose-600" role="alert">{{ $reviewErrors->first() }}</p>
            @endif
            <button type="submit" class="w-full sm:w-auto px-5 h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Kirim ulasan</button>
        </form>
    @endif
</div>
