@pushOnce('scripts')
    <script type="text/x-template" id="v-ai-dictation-template">
        <div ref="root" class="relative shrink-0">
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                :class="{ 'border-red-400 text-red-700 dark:text-red-300': phase === 'recording' }"
                :aria-label="label"
                :title="label"
                :disabled="phase === 'idle' ? disabled || remaining <= 0 : phase !== 'recording'"
                @click="activate"
            >
                <svg v-if="phase === 'recording'" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                </svg>

                <svg v-else aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                    <rect x="9" y="3" width="6" height="12" rx="3"/>
                    <path d="M5 11v1a7 7 0 0 0 14 0v-1m-7 8v3m-3 0h6"/>
                </svg>
            </button>

            <div
                v-if="phase !== 'idle' || error || showUnavailable"
                class="absolute bottom-full z-10 mb-2 w-52 rounded-md border border-gray-300 bg-white p-2 text-xs leading-5 text-gray-700 shadow-md dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                :class="placement === 'end' ? 'ltr:right-0 rtl:left-0' : 'ltr:left-0 rtl:right-0'"
            >
                <p v-if="error" role="alert" class="text-red-700 dark:text-red-300" v-text="error"></p>
                <p v-else-if="phase === 'recording'" role="status">@lang('human_resources::ai.dictation.recording')</p>
                <p v-else role="status" v-text="label"></p>

                <button
                    v-if="phase !== 'idle'"
                    type="button"
                    class="font-semibold text-brandColor hover:underline dark:text-blue-300"
                    @click="cancel"
                >@lang('human_resources::ai.dictation.cancel')</button>
            </div>
        </div>
    </script>

    <script type="module">
        const audioFormats = { 'audio/webm': 'webm', 'audio/ogg': 'ogg', 'audio/mp4': 'm4a', 'audio/x-m4a': 'm4a' };
        const audioLimit = 8 * 1024 * 1024;
        const durationLimit = 60_000;

        app.component('v-ai-dictation', {
            template: '#v-ai-dictation-template',
            props: {
                disabled: Boolean,
                remaining: { type: Number, required: true },
                placement: { type: String, default: 'start' },
            },
            emits: ['transcript', 'busy-change'],

            data() {
                return {
                    supported: Boolean(window.isSecureContext && navigator.mediaDevices?.getUserMedia && window.MediaRecorder),
                    showUnavailable: false,
                    phase: 'idle',
                    error: '',
                    session: null,
                    dialog: null,
                };
            },

            computed: {
                label() {
                    if (! this.supported) {
                        return @js(trans('human_resources::ai.dictation.unavailable'));
                    }

                    return {
                        idle: @js(trans('human_resources::ai.dictation.start')),
                        connecting: @js(trans('human_resources::ai.dictation.connecting')),
                        recording: @js(trans('human_resources::ai.dictation.stop')),
                        transcribing: @js(trans('human_resources::ai.dictation.transcribing')),
                    }[this.phase];
                },
            },

            mounted() {
                this.dialog = this.$refs.root.closest('dialog');
                this.dialog?.addEventListener('close', this.cancel);
            },

            beforeUnmount() {
                this.dialog?.removeEventListener('close', this.cancel);
                this.cancel();
            },

            methods: {
                updatePhase(phase) {
                    this.phase = phase;
                    this.$emit('busy-change', phase !== 'idle');
                },

                activate() {
                    if (! this.supported) {
                        this.showUnavailable = ! this.showUnavailable;
                    } else if (this.phase === 'recording') {
                        this.stop();
                    } else {
                        this.start();
                    }
                },

                async start() {
                    const session = { request: new AbortController(), stream: null, recorder: null, timer: null, chunks: [] };
                    this.session = session;
                    this.error = '';
                    this.updatePhase('connecting');

                    try {
                        session.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        if (session.request.signal.aborted) {
                            this.release(session);
                            return;
                        }

                        const mimeType = Object.keys(audioFormats).find(type => MediaRecorder.isTypeSupported(type));
                        session.recorder = mimeType ? new MediaRecorder(session.stream, { mimeType }) : new MediaRecorder(session.stream);
                        session.recorder.addEventListener('dataavailable', event => {
                            if (event.data.size > 0) {
                                session.chunks.push(event.data);
                            }
                        });
                        session.recorder.addEventListener('stop', () => this.transcribe(session), { once: true });
                        session.recorder.addEventListener('error', () => this.fail(session), { once: true });
                        session.recorder.start();
                        session.timer = window.setTimeout(() => this.stop(), durationLimit);
                        this.updatePhase('recording');
                    } catch {
                        this.fail(session, @js(trans('human_resources::ai.dictation.microphone_error')));
                    }
                },

                stop() {
                    if (this.session?.recorder?.state === 'recording') {
                        this.session.recorder.stop();
                        this.updatePhase('transcribing');
                    }
                },

                release(session) {
                    window.clearTimeout(session.timer);
                    session.stream?.getTracks().forEach(track => track.stop());
                },

                cancel() {
                    const session = this.session;
                    if (session) {
                        session.request.abort();
                        if (session.recorder?.state === 'recording') {
                            session.recorder.stop();
                        }
                        this.release(session);
                        this.session = null;
                    }

                    this.error = '';
                    this.showUnavailable = false;
                    this.updatePhase('idle');
                },

                fail(session, message = @js(trans('human_resources::ai.dictation.failed'))) {
                    if (! session.request.signal.aborted) {
                        this.cancel();
                        this.error = message;
                    }
                },

                async transcribe(session) {
                    this.release(session);
                    if (session.request.signal.aborted) {
                        return;
                    }

                    const mime = (session.recorder.mimeType || session.chunks[0]?.type || '').split(';')[0];
                    const extension = audioFormats[mime];
                    const audio = new Blob(session.chunks, { type: mime });
                    session.chunks.length = 0;
                    if (! extension || ! audio.size || audio.size > audioLimit) {
                        this.fail(session);
                        return;
                    }

                    this.updatePhase('transcribing');

                    try {
                        const body = new FormData();
                        body.append('audio', audio, 'ditado.' + extension);

                        const { data } = await this.$axios.post(@js(route('admin.ai.transcribe')), body, {
                            signal: session.request.signal,
                            headers: { Accept: 'application/json' },
                        });
                        if (session.request.signal.aborted) {
                            return;
                        }

                        const transcript = data.transcript.trim();
                        if (transcript.length > this.remaining) {
                            this.fail(session, @js(trans('human_resources::ai.dictation.too_long')));
                            return;
                        }

                        this.$emit('transcript', transcript);
                        this.cancel();
                    } catch {
                        this.fail(session);
                    }
                },
            },
        });
    </script>
@endPushOnce
