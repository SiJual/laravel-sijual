<div x-data="{
    recording: false,
    transcribing: false,
    textPreview: '',
    mediaRecorder: null,
    audioChunks: [],
    startRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Browser Anda tidak mendukung perekaman suara.');
            return;
        }
        this.recording = true;
        this.textPreview = 'Mendengarkan suara Anda... Berbicaralah (contoh: Penjualan Kopi 50 ribu)';
        navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];
            this.mediaRecorder.ondataavailable = event => this.audioChunks.push(event.data);
            this.mediaRecorder.onstop = () => this.processVoiceInput();
            this.mediaRecorder.start();
            setTimeout(() => { if(this.recording) this.stopRecording(); }, 6000);
        }).catch(err => {
            this.recording = false;
            alert('Akses mikrofon ditolak.');
        });
    },
    stopRecording() {
        if (this.mediaRecorder && this.recording) {
            this.recording = false;
            this.transcribing = true;
            this.mediaRecorder.stop();
        }
    },
    async processVoiceInput() {
        this.transcribing = true;
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', audioBlob, 'voice.webm');
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (token) formData.append('_token', token);

        try {
            const response = await fetch('/sikas/voice-input', {
                method: 'POST',
                body: formData,
            });
            const json = await response.json();
            if (json.status === 'success' && json.data) {
                this.textPreview = (json.data.type === 'income' ? '+ ' : '- ') + json.data.description + ' (Rp ' + Number(json.data.amount || 0).toLocaleString('id-ID') + ')';
                this.$dispatch('voice-recognized', json.data);
            } else {
                this.textPreview = json.message || 'Gagal memproses suara. Coba lagi.';
            }
        } catch (err) {
            this.textPreview = 'Gagal menghubungi server AI.';
        } finally {
            this.transcribing = false;
        }
    }
}" class="bg-surface border border-border p-5 rounded-lg shadow-card space-y-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button @click="recording ? stopRecording() : startRecording()"
                    type="button"
                    :class="recording ? 'bg-error animate-pulse text-white' : 'bg-primary text-white hover:bg-primary/90'"
                    class="size-12 rounded-full flex items-center justify-center shadow-md transition">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                </svg>
            </button>
            <div>
                <h4 class="text-sm font-bold font-display text-on-surface">Input Suara Pintar (Voice-First)</h4>
                <p class="text-xs text-on-surface-variant">Klik mic dan sebutkan transaksi dalam Bahasa Indonesia alami</p>
            </div>
        </div>
        <span class="text-xs font-semibold px-2.5 py-1 bg-primary-subtle text-primary rounded-full">AI Noise-Robust</span>
    </div>

    {{-- Waveform / Preview Bar --}}
    <div class="p-3 bg-surface-alt rounded-md border border-border-input/50 flex items-center justify-between text-xs text-on-surface font-medium">
        <span x-text="textPreview || 'Ketik atau klik mic untuk mencatat cepat...' "></span>
        <template x-if="transcribing">
            <span class="text-primary font-semibold animate-pulse">Memproses AI...</span>
        </template>
    </div>

    {{-- Quick Direct Confirmation when used outside dashboard --}}
    <div x-data="{ lastResult: null }" @voice-recognized.window="lastResult = $event.detail" x-show="lastResult" x-transition class="pt-2">
        <div class="p-3 bg-primary/5 border border-primary/20 rounded-lg flex items-center justify-between">
            <div class="text-xs">
                <span class="font-bold text-on-surface" x-text="lastResult?.description"></span>
                <span class="font-bold text-primary ml-1" x-text="'(Rp ' + Number(lastResult?.amount || 0).toLocaleString('id-ID') + ')'"></span>
            </div>
            <form action="{{ route('sikas.transactions.store') }}" method="POST" class="inline flex items-center gap-2">
                @csrf
                <input type="hidden" name="source" value="voice">
                <input type="hidden" name="type" :value="lastResult?.type || 'income'">
                <input type="hidden" name="amount" :value="lastResult?.amount || 0">
                <input type="hidden" name="description" :value="lastResult?.description || ''">
                <input type="hidden" name="category_name" :value="lastResult?.category || ''">
                <input type="hidden" name="transaction_date" value="{{ date('Y-m-d H:i:s') }}">
                <button type="button" @click="lastResult = null" class="text-xs text-on-surface-variant hover:text-on-surface">Batal</button>
                <button type="submit" class="px-3 py-1 bg-primary text-white text-xs font-bold rounded shadow-sm">Simpan</button>
            </form>
        </div>
    </div>
</div>
