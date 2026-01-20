@extends('layouts.app')

@section('content')
    {{-- NEW: Full-screen overlay for initial website generation --}}
    <div id="initial-build-spinner" class="fixed inset-0 z-50 flex-col items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm" style="display: none;">
        <span class="loading loading-infinity loading-lg text-primary"></span>
        <p class="mt-4 text-lg font-semibold text-white">Building your website...</p>
        <p class="text-sm text-white/80">The AI is warming up. This may take a moment.</p>
    </div>

    {{-- Main container with full height minus the navbar --}}
    <div class="flex flex-col md:flex-row h-[calc(100vh-68px)]">

        {{-- Left Panel: Chat --}}
        <div class="flex flex-col w-full md:w-1/3 border-r border-base-300 bg-base-100">
            {{-- Chat Messages Area --}}
            <div id="chat-messages" class="flex-grow p-4 space-y-4 overflow-y-auto">
                @foreach($chatMessages as $msg)
                    <div class="chat {{ $msg->role === 'user' ? 'chat-start' : 'chat-end' }}">
                        <div class="chat-bubble {{ $msg->role === 'user' ? 'chat-bubble-primary' : '' }}" data-uuid="{{ $msg->uuid }}">
                            {!! \Illuminate\Support\Str::markdown($msg->content) !!}
                            {{-- Optional: If you want to show past images in history, check prompt_image_ids here, though not strictly required by prompt --}}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Image Preview Area --}}
            <div id="image-preview-container" class="hidden px-3 pt-2 bg-base-100">
                <div class="relative inline-block">
                    {{-- FIXED: Added style="max-height: 150px; width: auto;" to strictly enforce height limit --}}
                    <img id="image-preview" src="" alt="Preview" style="max-height: 150px; width: auto;" class="block rounded border border-base-300 object-contain">
                    <button id="remove-image-btn" type="button" class="absolute -top-2 -right-2 btn btn-xs btn-circle btn-error text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Chat Input Form --}}
            <form id="chat-form" class="p-3 border-t border-base-300 flex items-end gap-2 bg-base-100" enctype="multipart/form-data">
                {{-- Hidden File Input --}}
                <input type="file" id="prompt-image-input" name="prompt_image" accept="image/*" class="hidden">

                {{-- Upload Button --}}
                <button type="button" id="upload-btn" class="btn btn-square btn-ghost text-base-content/70 hover:text-primary" title="Attach Image">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                    </svg>
                </button>

                <textarea id="chat-input" placeholder="Ask Author Website Builder to build..." class="textarea textarea-bordered flex-grow resize-none text-sm" rows="1"></textarea>
                <button type="submit" id="chat-submit-btn" class="btn btn-primary btn-square">
                    {{-- The button icon is now managed by JavaScript to show a spinner. --}}
                </button>
            </form>
        </div>

        {{-- Right Panel: Preview/Code --}}
        <div class="flex flex-col w-full md:w-2/3 bg-base-200/50">
            <div class="flex justify-between items-center p-2 border-b border-base-300 bg-base-100">
                <div class="flex items-center gap-2">
                    <div role="tablist" class="tabs tabs-bordered">
                        <a role="tab" class="tab tab-active" data-tab-content="preview-content">Preview</a>
                        <a role="tab" class="tab" data-tab-content="code-content">Code</a>
                    </div>
                    <button id="restore-btn" class="btn btn-outline btn-sm">Undo Changes...</button>
                </div>

                <div id="panel-controls" class="flex items-center gap-1">
                    {{-- Controls will be dynamically added here by JS --}}
                </div>
            </div>

            <div id="preview-content" class="tab-pane flex-grow p-4 overflow-auto">
                <div id="preview-container" class="relative mx-auto transition-all duration-300 ease-in-out w-full h-full">
                    <div id="iframe-spinner" class="absolute inset-0 z-10 flex items-center justify-center bg-base-100 bg-opacity-75 rounded-md" style="display: none;">
                        <span class="loading loading-spinner loading-lg"></span>
                    </div>
                    <iframe id="preview-iframe" src="{{ route('website.preview.serve', $website) }}" class="w-full h-full bg-white rounded-md shadow border-0"></iframe>
                </div>
            </div>

            <div id="code-content" class="tab-pane flex-grow hidden">
                <div class="flex flex-col h-full border-t border-base-300 overflow-hidden">
                    <div class="flex items-center justify-between p-1 border-b border-base-300 bg-base-200">
                        <span id="selected-file-info" class="text-sm font-medium px-2 truncate">Select a file</span>
                        <div id="code-editor-actions" class="flex items-center gap-1">
                        </div>
                    </div>
                    <div class="flex flex-grow overflow-hidden min-h-0" style="max-height: calc(100vh - 155px);">
                        <div id="code-file-list" class="w-1/3 md:w-1/4 border-r border-base-300 bg-base-100 overflow-y-auto p-2 space-y-1">
                            <div class="text-center p-4 text-sm opacity-70">Loading files...</div>
                        </div>
                        <div class="w-2/3 md:w-3/4 flex-1 overflow-auto bg-base-100">
                            <div id="code-viewer" class="whitespace-pre-wrap break-words p-4 font-mono text-sm h-full">
                                <div class="flex items-center justify-center h-full text-sm opacity-70">Select a file to view its content.</div>
                            </div>
                            <textarea id="code-editor" class="w-full h-full p-4 font-mono text-sm rounded-none border-0 focus:ring-0 resize-none bg-base-100 hidden"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <dialog id="restore_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Undo Last Change</h3>
            <p class="py-4">Are you sure you want to undo the last change? This will permanently delete the most recent file operation. This action cannot be undone.</p>
            <!--<p class="py-4">This will permanently delete the last file operations. This action cannot be undone. How many steps would you like to go back?</p>

            <div class="form-control w-full">
                <label class="label" for="restore-steps">
                    <span class="label-text">Number of steps to revert (1 step = 1 file change)</span>
                </label>
                <input type="number" id="restore-steps" class="input input-bordered w-full" value="1" min="1" max="50">
            </div>-->

            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Cancel</button>
                </form>
                <button id="confirm-restore-btn" class="btn btn-error">Yes, Undo</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <!-- Success Toast -->
    <div id="toast-success" class="toast toast-top toast-center z-[100] hidden">
        <div class="alert alert-success bg-emerald-500 text-white border-none shadow-lg py-2 px-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">Undo successful!</span>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- COMMON ELEMENTS ---
            const websiteId = @json($website->id);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const initialBuildSpinner = document.getElementById('initial-build-spinner');

            // --- CHAT ELEMENTS & LOGIC ---
            const chatForm = document.getElementById('chat-form');
            const chatInput = document.getElementById('chat-input');
            const chatSubmitBtn = document.getElementById('chat-submit-btn');
            const chatMessages = document.getElementById('chat-messages');
            let initialPrompt = @json(session('initial_prompt'));

            // --- IMAGE UPLOAD ELEMENTS ---
            const uploadBtn = document.getElementById('upload-btn');
            const promptImageInput = document.getElementById('prompt-image-input');
            const imagePreviewContainer = document.getElementById('image-preview-container');
            const imagePreview = document.getElementById('image-preview');
            const removeImageBtn = document.getElementById('remove-image-btn');
            let selectedImageFile = null;

            // --- PREVIEW ELEMENTS & LOGIC ---
            const previewIframe = document.getElementById('preview-iframe');
            const previewContainer = document.getElementById('preview-container');
            const panelControls = document.getElementById('panel-controls');
            const iframeSpinner = document.getElementById('iframe-spinner');

            // --- CODE EDITOR ELEMENTS & LOGIC ---
            const codeFileList = document.getElementById('code-file-list');
            const selectedFileInfo = document.getElementById('selected-file-info');
            const codeEditorActions = document.getElementById('code-editor-actions');
            const codeViewer = document.getElementById('code-viewer');
            const codeEditor = document.getElementById('code-editor');

            // --- RESTORE ELEMENTS ---
            const restoreBtn = document.getElementById('restore-btn');
            const restoreModal = document.getElementById('restore_modal');
            const restoreStepsInput = document.getElementById('restore-steps');
            const confirmRestoreBtn = document.getElementById('confirm-restore-btn');

            // --- STATE MANAGEMENT ---
            let files = [];
            let selectedFile = null;
            let isEditing = false;
            let isSaving = false;
            let lastUserMessage = '';
            let lastResponseWasError = false;

            // --- ICON SVGs ---
            const icons = {
                send: `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>`,
                spinner: `<span class="loading loading-spinner"></span>`,
                monitor: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>`,
                smartphone: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>`,
                edit: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>`,
                save: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>`,
                cancel: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>`,
                loading: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`,
                refresh: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>`,
                externalLink: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>`
            };

            // --- FUNCTIONS ---

            function scrollChatToBottom() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            };

            function addMessageToUI(role, content, uuid = null) {
                const messageElement = document.createElement('div');
                messageElement.className = `chat ${role === 'user' ? 'chat-start' : 'chat-end'}`;

                const bubble = document.createElement('div');
                bubble.className = `chat-bubble ${role === 'user' ? 'chat-bubble-primary' : ''}`;

                if (uuid) {
                    bubble.setAttribute('data-uuid', uuid);
                }

                // Append content
                bubble.innerHTML = marked.parse(content);

                // If user uploaded an image in this session just now, show it in the bubble
                if (role === 'user' && selectedImageFile) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(selectedImageFile);
                    img.style.maxHeight = '150px';
                    img.style.width = 'auto';
                    img.className = 'mt-2 rounded border border-white/20'; // Removed conflicting classes
                    bubble.appendChild(img);
                }

                messageElement.appendChild(bubble);
                chatMessages.appendChild(messageElement);

                scrollChatToBottom();

                return bubble;
            };

            // Clear Image Selection
            function clearImageSelection() {
                selectedImageFile = null;
                promptImageInput.value = '';
                imagePreview.src = '';
                imagePreviewContainer.classList.add('hidden');
            }

            async function sendMessage(message) {
                if (!message.trim() && !selectedImageFile) {
                    return;
                }

                lastResponseWasError = false;

                const userBubble = addMessageToUI('user', message);
                chatInput.value = '';

                chatInput.disabled = true;
                uploadBtn.disabled = true; // Disable upload button
                chatSubmitBtn.innerHTML = icons.spinner;
                chatSubmitBtn.classList.add('btn-disabled');

                try {
                    // Use FormData to support image upload
                    const formData = new FormData();
                    formData.append('message', message);
                    if (selectedImageFile) {
                        formData.append('prompt_image', selectedImageFile);
                    }

                    // Clear the image selection *before* sending, the local var holds the file
                    const fileToSend = selectedImageFile;
                    clearImageSelection();

                    const response = await fetch("{{ route('websites.chat.store', $website) }}", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                            // Content-Type is auto-set by FormData with boundary
                        },
                        body: formData,
                    });
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Network response was not ok.');
                    }
                    const data = await response.json();

                    if (data.userMessage && data.userMessage.uuid) {
                        userBubble.setAttribute('data-uuid', data.userMessage.uuid);
                    }

                    addMessageToUI('assistant', data.assistantMessage.content, data.assistantMessage.uuid);

                    if (data.files_updated) {
                        await fetchFiles();
                        refreshPreview();
                    }
                } catch (error) {
                    console.error('Chat error:', error);
                    lastResponseWasError = true;
                    lastUserMessage = message;
                    // Note: If sending failed, we lose the file selection here for retry unless we cache it.
                    // For simplicity, we just ask user to try again.
                    addMessageToUI('assistant', 'Sorry, an error occurred: ' + error.message + '<br><br><small class="opacity-70">Please re-attach image if needed and try again.</small>');
                } finally {
                    if (initialBuildSpinner.style.display !== 'none') {
                        initialBuildSpinner.style.display = 'none';
                    }
                    chatInput.disabled = false;
                    uploadBtn.disabled = false;
                    chatSubmitBtn.innerHTML = icons.send;
                    chatSubmitBtn.classList.remove('btn-disabled');
                    chatInput.focus();
                }
            };

            function refreshPreview() {
                if (previewIframe) {
                    iframeSpinner.style.display = 'flex';
                    const url = "{{ route('website.preview.serve', $website) }}";
                    previewIframe.src = url + '?t=' + new Date().getTime();
                }
            };

            function renderFileList() {
                if (files.length === 0) {
                    codeFileList.innerHTML = `<div class="text-center p-4 text-sm opacity-70">No files found.</div>`;
                    return;
                }
                codeFileList.innerHTML = files.map(file => `
                    <button data-file-id="${file.id}" class="btn btn-ghost btn-sm justify-start w-full truncate text-left font-normal">
                        <span class="block truncate">${file.folder !== '/' ? `${file.folder.substring(1)}/` : ''}${file.filename}</span>
                    </button>
                `).join('');
            };

            async function fetchFiles() {
                try {
                    const response = await fetch("{{ route('api.websites.files.index', $website) }}");
                    if (!response.ok) throw new Error('Failed to fetch files.');
                    const data = await response.json();
                    files = data.files || [];
                    renderFileList();
                    if (!selectedFile && files.length > 0) {
                        const initialFile = files.find(f => f.filename === 'index.html' && f.folder === '/') || files[0];
                        selectFile(initialFile.id);
                    }
                } catch (error) {
                    console.error('File fetch error:', error);
                    codeFileList.innerHTML = `<div class="text-center p-4 text-sm text-error">Could not load files.</div>`;
                }
            };

            function selectFile(fileId) {
                const file = files.find(f => f.id == fileId);
                if (!file) return;
                if (isEditing) toggleEditMode(false);
                selectedFile = file;
                document.querySelectorAll('#code-file-list button').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.fileId == fileId);
                });
                selectedFileInfo.textContent = `${file.folder !== '/' ? `${file.folder.substring(1)}/` : ''}${file.filename} (v${file.version})`;
                codeViewer.textContent = file.content;
                codeEditor.value = file.content;
                renderCodeEditorActions();
            };

            function toggleEditMode(editing) {
                isEditing = editing;
                codeViewer.classList.toggle('hidden', isEditing);
                codeEditor.classList.toggle('hidden', !isEditing);
                renderCodeEditorActions();
            };

            function renderCodeEditorActions() {
                if (!selectedFile) {
                    codeEditorActions.innerHTML = '';
                    return;
                }
                if (isEditing) {
                    codeEditorActions.innerHTML = `
                        <button id="save-btn" class="btn btn-success btn-xs gap-1">${icons.save} Save</button>
                        <button id="cancel-btn" class="btn btn-ghost btn-xs gap-1">${icons.cancel} Cancel</button>
                    `;
                } else {
                    codeEditorActions.innerHTML = `<button id="edit-btn" class="btn btn-outline btn-xs gap-1">${icons.edit} Edit</button>`;
                }
            };

            async function saveFile() {
                if (!selectedFile || isSaving) return;
                isSaving = true;
                const saveBtn = document.getElementById('save-btn');
                saveBtn.classList.add('loading');
                saveBtn.disabled = true;
                try {
                    const response = await fetch("{{ route('api.websites.files.update', $website) }}", {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            folder: selectedFile.folder,
                            filename: selectedFile.filename,
                            content: codeEditor.value
                        }),
                    });
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Failed to save file.');
                    }
                    toggleEditMode(false);
                    await fetchFiles();
                    refreshPreview();
                } catch (error) {
                    console.error('Save error:', error);
                    alert('Error saving file: ' + error.message);
                } finally {
                    isSaving = false;
                }
            };

            function renderPanelControls(activeTab) {
                panelControls.innerHTML = '';
                if (activeTab === 'preview-content') {
                    panelControls.innerHTML = `
                        <button data-view="desktop" class="btn btn-outline btn-sm btn-square active" title="Desktop View">${icons.monitor}</button>
                        <button data-view="mobile" class="btn btn-outline btn-sm btn-square" title="Mobile View">${icons.smartphone}</button>
                        <div class="divider divider-horizontal mx-1"></div>
                        <button data-action="refresh" class="btn btn-outline btn-sm btn-square" title="Refresh Preview">${icons.refresh}</button>
                        <button data-action="open" class="btn btn-outline btn-sm btn-square" title="Open in New Tab">${icons.externalLink}</button>
                    `;
                }
            };

            async function restoreHistory() {
                const steps = 1;
                const toast = document.getElementById('toast-success');
                /*const steps = parseInt(restoreStepsInput.value, 10);
                if (isNaN(steps) || steps < 1) {
                    alert('Please enter a valid number of steps.');
                    return;
                }*/

                confirmRestoreBtn.classList.add('btn-disabled', 'loading');
                confirmRestoreBtn.innerHTML = `${icons.spinner} Processing...`;

                try {
                    const response = await fetch("{{ route('websites.restore', $website) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ steps: steps }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || data.message || 'Failed to restore history.');
                    }

                    // --- REMOVE CHAT BUBBLES ---
                    if (data.reverted_uuids && data.reverted_uuids.length > 0) {
                        data.reverted_uuids.forEach(uuid => {
                            // Find the bubble by UUID
                            const bubble = document.querySelector(`[data-uuid="${uuid}"]`);
                            if (bubble) {
                                // Find the parent container with class "chat" and remove it
                                const chatRow = bubble.closest('.chat');
                                if (chatRow) {
                                    chatRow.remove();
                                }
                            }
                        });
                    }

                    toast.classList.remove('hidden');

                    setTimeout(() => {
                        restoreModal.close();
                        // Reset button state for next time
                        confirmRestoreBtn.classList.remove('btn-disabled');
                        confirmRestoreBtn.innerText = 'Yes, Undo';

                        // Hide toast after 3 seconds
                        setTimeout(() => {
                            toast.classList.add('hidden');
                        }, 3000);
                    }, 600);

                    //restoreModal.close();
                    //alert('Successfully restored files!');
                    await fetchFiles();
                    refreshPreview();

                } catch (error) {
                    console.error('Restore error:', error);
                    alert('Error: ' + error.message);
                } finally {
                    confirmRestoreBtn.classList.remove('btn-disabled', 'loading');
                }
            };

            // --- EVENT LISTENERS ---

            // Image Upload Handling
            uploadBtn.addEventListener('click', () => {
                promptImageInput.click();
            });

            promptImageInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) {
                    const file = e.target.files[0];
                    if (!file.type.startsWith('image/')) {
                        alert('Please select an image file.');
                        return;
                    }
                    if (file.size > 10 * 1024 * 1024) { // 10MB Check
                        alert('Image size exceeds 10MB.');
                        return;
                    }

                    selectedImageFile = file;

                    // Show Preview
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });

            removeImageBtn.addEventListener('click', () => {
                clearImageSelection();
            });

            chatForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const currentMessage = chatInput.value.trim();

                if (currentMessage === '' && !selectedImageFile && lastResponseWasError) {
                    // Logic to retry logic is complicated with files, simplifying to text retry or fresh send
                    if (!lastUserMessage) return;
                    sendMessage(lastUserMessage);
                } else {
                    sendMessage(currentMessage);
                }
            });

            chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.ctrlKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });

            if (initialPrompt) {
                if (initialBuildSpinner) {
                    initialBuildSpinner.style.display = 'flex';
                }
                sendMessage(initialPrompt);
                initialPrompt = null;
            }

            const tabs = document.querySelectorAll('[data-tab-content]');
            tabs.forEach(tabLink => {
                tabLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const tabContentId = tabLink.dataset.tabContent;
                    tabs.forEach(t => t.classList.remove('tab-active'));
                    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
                    tabLink.classList.add('tab-active');
                    document.getElementById(tabContentId).classList.remove('hidden');
                    renderPanelControls(tabContentId);
                });
            });

            panelControls.addEventListener('click', e => {
                const button = e.target.closest('button');
                if (!button) return;

                const view = button.dataset.view;
                const action = button.dataset.action;

                if (view) {
                    panelControls.querySelectorAll('button[data-view]').forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    if (view === 'mobile') {
                        previewContainer.className = 'relative mx-auto transition-all duration-300 ease-in-out w-[375px] h-[667px] max-w-full max-h-full overflow-hidden rounded-lg shadow-lg border-4 border-neutral';
                    } else {
                        previewContainer.className = 'relative mx-auto transition-all duration-300 ease-in-out w-full h-full';
                    }
                }

                if (action === 'refresh') {
                    refreshPreview();
                }
                if (action === 'open') {
                    const url = "{{ route('website.preview.serve', $website) }}";
                    window.open(url, '_blank');
                }
            });

            codeEditorActions.addEventListener('click', e => {
                const button = e.target.closest('button');
                if (!button) return;
                if (button.id === 'edit-btn') toggleEditMode(true);
                if (button.id === 'cancel-btn') toggleEditMode(false);
                if (button.id === 'save-btn') saveFile();
            });

            codeFileList.addEventListener('click', e => {
                const button = e.target.closest('button[data-file-id]');
                if (button) {
                    selectFile(button.dataset.fileId);
                }
            });

            if (restoreBtn) {
                restoreBtn.addEventListener('click', () => {
                    if (restoreModal) {
                        restoreModal.showModal();
                    }
                });
            }

            if (confirmRestoreBtn) {
                confirmRestoreBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    restoreHistory();
                });
            }

            if (previewIframe) {
                previewIframe.addEventListener('load', () => {
                    iframeSpinner.style.display = 'none';

                    try {
                        const iframeDoc = previewIframe.contentDocument || previewIframe.contentWindow.document;
                        if (iframeDoc) {
                            iframeDoc.body.addEventListener('click', (e) => {
                                const link = e.target.closest('a');
                                if (link && link.href && link.target !== '_blank' && link.origin === window.location.origin) {
                                    iframeSpinner.style.display = 'flex';
                                }
                            });
                        }
                    } catch (error) {
                        console.warn('Could not attach click listener to iframe content:', error);
                    }
                });

                if (previewIframe.contentWindow) {
                    previewIframe.contentWindow.addEventListener('beforeunload', () => {
                        iframeSpinner.style.display = 'flex';
                    });
                }
            }

            // --- INITIALIZATION ---

            chatSubmitBtn.innerHTML = icons.send;
            renderPanelControls('preview-content');
            fetchFiles();
            scrollChatToBottom();
        });
    </script>
@endpush
