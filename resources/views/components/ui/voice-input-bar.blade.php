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
    processVoiceInput() {
        // Mock / API voice process call
        setTimeout(() => {
            this.transcribing = false;
            this.textPreview = 'Penjualan Kopi Rp 50.000';
            this.$dispatch('voice-recognized', { text: this.textPreview, amount: 50000, type: 'income', category: 'Penjualan' });
        }, 1200);
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
</div>
