@php
    $messages = [
        'select' => __('chunked_upload.select_video'),
        'replace' => __('chunked_upload.replace_video'),
        'preparing' => __('chunked_upload.preparing'),
        'uploading' => __('chunked_upload.uploading'),
        'paused' => __('chunked_upload.paused'),
        'assembling' => __('chunked_upload.assembling'),
        'completed' => __('chunked_upload.completed'),
        'failed' => __('chunked_upload.failed'),
        'pause' => __('chunked_upload.pause'),
        'resume' => __('chunked_upload.resume'),
        'retry' => __('chunked_upload.retry'),
        'cancel' => __('chunked_upload.cancel'),
        'courseRequired' => __('chunked_upload.course_required'),
        'invalidType' => __('chunked_upload.invalid_type'),
        'fileTooLarge' => __('chunked_upload.file_too_large'),
        'requestFailed' => __('chunked_upload.request_failed'),
        'remainingShort' => __('chunked_upload.remaining_short'),
        'closeWarning' => __('chunked_upload.close_warning'),
    ];
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            courseId: @js($getCourseId()),
            maxFileSize: @js($getMaxFileSize()),
            defaultChunkSize: @js($getChunkSize()),
            initializeUrl: @js(route('admin.video-uploads.store')),
            uploadBaseUrl: @js(url('/admin/video-uploads')),
            messages: @js($messages),
            file: null,
            fileName: null,
            previousState: null,
            uploadId: null,
            chunkSize: @js($getChunkSize()),
            nextChunk: 0,
            totalChunks: 0,
            uploadedBytes: 0,
            progress: 0,
            speed: 0,
            etaSeconds: null,
            status: 'idle',
            errorMessage: null,
            paused: false,
            cancelled: false,
            activeRequest: null,
            samples: [],

            get isActive() {
                return ['preparing', 'uploading', 'paused', 'assembling'].includes(this.status)
            },

            get canRetry() {
                return this.status === 'failed' && this.file && this.uploadId
            },

            get statusText() {
                return this.messages[this.status] ?? ''
            },

            get selectedLabel() {
                return this.state ? this.messages.replace : this.messages.select
            },

            async selectFile(event) {
                const selected = event.target.files?.[0]
                event.target.value = ''

                if (! selected) return

                if (! this.courseId) {
                    this.fail(this.messages.courseRequired)
                    return
                }

                const extension = selected.name.split('.').pop()?.toLowerCase()
                const acceptedMime = ['', 'video/mp4', 'video/x-m4v', 'application/mp4', 'application/octet-stream'].includes(selected.type.toLowerCase())

                if (extension !== 'mp4' || ! acceptedMime) {
                    this.fail(this.messages.invalidType)
                    return
                }

                if (selected.size > this.maxFileSize) {
                    this.fail(this.messages.fileTooLarge)
                    return
                }

                const fallbackState = this.uploadId ? this.previousState : this.state

                if (this.uploadId) {
                    await this.removeUploadSession()
                }

                this.previousState = fallbackState
                this.state = null
                this.file = selected
                this.fileName = selected.name
                this.status = 'preparing'
                this.errorMessage = null
                this.cancelled = false
                this.paused = false
                this.progress = 0
                this.uploadedBytes = 0
                this.speed = 0
                this.etaSeconds = null
                this.samples = []

                try {
                    const session = await this.request(this.initializeUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            course_id: this.courseId,
                            name: selected.name,
                            size: selected.size,
                            mime: selected.type,
                        }),
                    })

                    this.uploadId = session.upload_id
                    this.chunkSize = session.chunk_size || this.defaultChunkSize
                    this.nextChunk = session.next_chunk || 0
                    this.totalChunks = session.total_chunks
                    this.uploadedBytes = session.uploaded_bytes || 0
                    this.progress = session.progress || 0
                    this.status = 'uploading'
                    this.addSample(this.uploadedBytes)

                    await this.runUpload()
                } catch (error) {
                    if (! this.cancelled) this.fail(error.message)
                }
            },

            async runUpload() {
                if (! this.file || ! this.uploadId) return

                this.status = 'uploading'
                this.errorMessage = null
                this.cancelled = false

                try {
                    while (this.nextChunk < this.totalChunks) {
                        while (this.paused && ! this.cancelled) {
                            this.status = 'paused'
                            await this.sleep(200)
                        }

                        if (this.cancelled) return

                        this.status = 'uploading'
                        const start = this.nextChunk * this.chunkSize
                        const end = Math.min(start + this.chunkSize, this.file.size)
                        const blob = this.file.slice(start, end)
                        const result = await this.sendChunk(blob, this.nextChunk)

                        this.nextChunk = result.next_chunk
                        this.uploadedBytes = result.uploaded_bytes
                        this.progress = Math.min(99, result.progress)
                        this.addSample(this.uploadedBytes)
                    }

                    this.status = 'assembling'
                    this.progress = 99
                    this.etaSeconds = 0

                    const completed = await this.request(`${this.uploadBaseUrl}/${this.uploadId}/complete`, {
                        method: 'POST',
                    })

                    this.state = completed.source_path
                    this.uploadedBytes = this.file.size
                    this.progress = 100
                    this.status = 'completed'
                    this.errorMessage = null
                } catch (error) {
                    if (! this.cancelled) this.fail(error.message)
                }
            },

            async sendChunk(blob, index) {
                let lastError = null

                for (let attempt = 0; attempt < 3; attempt++) {
                    if (this.cancelled) throw new Error(this.messages.requestFailed)

                    const form = new FormData()
                    form.append('chunk_index', index)
                    form.append('chunk', blob, `chunk-${index}.part`)

                    try {
                        return await this.request(`${this.uploadBaseUrl}/${this.uploadId}/chunks`, {
                            method: 'POST',
                            body: form,
                        })
                    } catch (error) {
                        lastError = error

                        if (attempt < 2 && ! this.cancelled) {
                            await this.sleep(500 * (2 ** attempt))
                        }
                    }
                }

                throw lastError ?? new Error(this.messages.requestFailed)
            },

            togglePause() {
                this.paused = ! this.paused
                this.status = this.paused ? 'paused' : 'uploading'
            },

            async retry() {
                if (! this.canRetry) return

                this.paused = false
                this.cancelled = false
                await this.runUpload()
            },

            async cancel() {
                this.cancelled = true
                this.paused = false
                this.activeRequest?.abort()
                await this.removeUploadSession()
                this.state = this.previousState
                this.resetRuntime()
            },

            async removeUploadSession() {
                if (! this.uploadId) return

                const uploadId = this.uploadId
                this.uploadId = null

                try {
                    await this.request(`${this.uploadBaseUrl}/${uploadId}`, { method: 'DELETE' }, false)
                } catch (_) {
                    // The scheduled cleanup command removes abandoned sessions if this request fails.
                }
            },

            resetRuntime() {
                this.file = null
                this.fileName = null
                this.nextChunk = 0
                this.totalChunks = 0
                this.uploadedBytes = 0
                this.progress = 0
                this.speed = 0
                this.etaSeconds = null
                this.status = 'idle'
                this.errorMessage = null
                this.cancelled = false
                this.samples = []
            },

            fail(message) {
                this.status = 'failed'
                this.errorMessage = message || this.messages.requestFailed
            },

            addSample(bytes) {
                const now = performance.now()
                this.samples.push({ at: now, bytes })
                this.samples = this.samples.filter(sample => now - sample.at <= 8000)

                if (this.samples.length < 2) return

                const first = this.samples[0]
                const elapsed = (now - first.at) / 1000
                const transferred = bytes - first.bytes

                if (elapsed <= 0 || transferred <= 0) return

                const currentSpeed = transferred / elapsed
                this.speed = this.speed > 0
                    ? (this.speed * 0.65) + (currentSpeed * 0.35)
                    : currentSpeed

                const remaining = Math.max(0, (this.file?.size || 0) - bytes)
                this.etaSeconds = this.speed > 0 ? Math.ceil(remaining / this.speed) : null
            },

            async request(url, options = {}, expectJson = true) {
                this.activeRequest = new AbortController()
                const headers = new Headers(options.headers || {})
                const csrfToken = document.querySelector('meta[name=csrf-token]')?.content

                headers.set('Accept', 'application/json')
                headers.set('X-Requested-With', 'XMLHttpRequest')
                if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken)

                let response

                try {
                    response = await fetch(url, {
                        ...options,
                        headers,
                        credentials: 'same-origin',
                        signal: this.activeRequest.signal,
                    })
                } catch (error) {
                    if (error.name === 'AbortError' && this.cancelled) throw error
                    throw new Error(this.messages.requestFailed)
                } finally {
                    this.activeRequest = null
                }

                if (! response.ok) {
                    let message = this.messages.requestFailed

                    try {
                        const payload = await response.json()
                        message = payload.message || Object.values(payload.errors || {})?.[0]?.[0] || message
                    } catch (_) {}

                    throw new Error(message)
                }

                if (! expectJson || response.status === 204) return null

                return response.json()
            },

            formatBytes(bytes) {
                if (! Number.isFinite(bytes) || bytes <= 0) return '0 B'

                const units = ['B', 'KB', 'MB', 'GB', 'TB']
                const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
                const value = bytes / (1024 ** unit)

                return `${value.toFixed(unit === 0 || value >= 10 ? 0 : 1)} ${units[unit]}`
            },

            formatTime(seconds) {
                if (! Number.isFinite(seconds) || seconds < 0) return '—'
                if (seconds < 60) return `${seconds}s`

                const minutes = Math.floor(seconds / 60)
                const remainingSeconds = seconds % 60
                if (minutes < 60) return `${minutes}m ${remainingSeconds}s`

                const hours = Math.floor(minutes / 60)
                return `${hours}h ${minutes % 60}m`
            },

            sleep(milliseconds) {
                return new Promise(resolve => setTimeout(resolve, milliseconds))
            },

            destroy() {
                if (! this.isActive || ! this.uploadId) return

                this.cancelled = true
                this.activeRequest?.abort()

                const csrfToken = document.querySelector('meta[name=csrf-token]')?.content
                fetch(`${this.uploadBaseUrl}/${this.uploadId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    credentials: 'same-origin',
                    keepalive: true,
                }).catch(() => {})
            },
        }"
        class="space-y-3"
    >
        <input
            x-ref="fileInput"
            type="file"
            accept="video/mp4,.mp4"
            class="hidden"
            x-on:change="selectFile($event)"
        />

        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white" x-text="fileName || state || messages.select"></p>
                </div>

                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                    x-on:click="$refs.fileInput.click()"
                    x-bind:disabled="isActive"
                    x-text="selectedLabel"
                ></button>
            </div>
        </div>

        <div
            x-cloak
            x-show="status !== 'idle'"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="statusText"></p>
                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400" x-text="fileName"></p>
                </div>
                <span class="text-sm font-semibold tabular-nums text-primary-600" x-text="`${Math.round(progress)}%`"></span>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    x-bind:class="status === 'failed' ? 'bg-danger-600' : (status === 'completed' ? 'bg-success-600' : 'bg-primary-600')"
                    x-bind:style="`width: ${progress}%`"
                ></div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                <span x-text="`${formatBytes(uploadedBytes)} / ${formatBytes(file?.size || uploadedBytes)}`"></span>
                <span x-show="speed > 0" x-text="`${formatBytes(speed)}/s`"></span>
                <span x-show="etaSeconds !== null && status === 'uploading'" x-text="`${formatTime(etaSeconds)} ${messages.remainingShort}`"></span>
            </div>

            <p
                x-cloak
                x-show="isActive"
                class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700 dark:bg-warning-950/40 dark:text-warning-300"
                x-text="messages.closeWarning"
            ></p>

            <p
                x-cloak
                x-show="errorMessage"
                class="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-950/40 dark:text-danger-300"
                x-text="errorMessage"
            ></p>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    x-show="['uploading', 'paused'].includes(status)"
                    x-on:click="togglePause()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                    x-text="paused ? messages.resume : messages.pause"
                ></button>

                <button
                    type="button"
                    x-cloak
                    x-show="canRetry"
                    x-on:click="retry()"
                    class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-500"
                    x-text="messages.retry"
                ></button>

                <button
                    type="button"
                    x-show="status !== 'completed' || uploadId"
                    x-on:click="cancel()"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950/40"
                    x-text="messages.cancel"
                ></button>
            </div>
        </div>
    </div>
</x-dynamic-component>
