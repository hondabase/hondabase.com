@extends('layouts.app')

@section('title', 'My Hondabase')

@section('content')
    <section class="page-head">
        <h2 class="section-head">My Hondabase</h2>
        <p class="text-dim mt-1 max-w-[70ch]">Your garage, your feed and your saved articles.</p>
    </section>

    <livewire:dashboard />

    @if (config('webpush.vapid.public_key'))
    <section class="dash-section" x-data="pushToggle(@js(config('webpush.vapid.public_key')))" x-init="init()" x-cloak>
        <h2 class="section-head">Push notifications</h2>
        <p class="text-dim mt-1 max-w-[70ch]">Get a browser notification the moment an article for something you
        follow is published or updated, even when Hondabase isn't open.</p>

        <div class="push-row" x-show="supported">
            <button type="button" class="btn" @click="toggle()" x-text="busy ? 'Working...' : (subscribed ? 'Turn off push' : 'Turn on push')" :disabled="busy"></button>
            <span class="push-state" x-show="subscribed">On for this browser.</span>
            <span class="push-state push-denied" x-show="denied">Blocked in your browser settings.</span>
        </div>
        <p class="text-muted text-[0.82rem] my-[0.4rem]" x-show="!supported">This browser doesn't support web push.</p>
    </section>
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pushToggle', (vapidKey) => ({
        supported: false, subscribed: false, denied: false, busy: false, vapidKey,
        async init() {
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            if (!this.supported) return;
            this.denied = Notification.permission === 'denied';
            try {
                const reg = await navigator.serviceWorker.register('/sw.js');
                const sub = await reg.pushManager.getSubscription();
                this.subscribed = !!sub;
            } catch (e) { this.supported = false; }
        },
        async toggle() {
            this.busy = true;
            try { this.subscribed ? await this.unsubscribe() : await this.subscribe(); }
            catch (e) { console.error('push toggle failed', e); }
            this.busy = false;
        },
        async subscribe() {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') { this.denied = perm === 'denied'; return; }
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlB64ToUint8Array(this.vapidKey),
            });
            await this.send('POST', sub.toJSON());
            this.subscribed = true;
        },
        async unsubscribe() {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (sub) { await this.send('DELETE', { endpoint: sub.endpoint }); await sub.unsubscribe(); }
            this.subscribed = false;
        },
        send(method, body) {
            return fetch('/me/push', {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
        },
        urlB64ToUint8Array(b64) {
            const pad = '='.repeat((4 - (b64.length % 4)) % 4);
            const base = (b64 + pad).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(base);
            return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
        },
    }));
});
</script>
@endpush
