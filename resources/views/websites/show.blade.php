@extends('layouts.app')

@section('content')
	{{-- Main container with full height minus the navbar --}}
	<div class="flex flex-col md:flex-row h-[calc(100vh-68px)]">
		
		{{-- Left Panel: Chat --}}
		<div class="flex flex-col w-full md:w-1/3 border-r border-base-300 bg-base-100">
			{{-- Chat Messages Area --}}
			<div id="chat-messages" class="flex-grow p-4 space-y-4 overflow-y-auto">
				@foreach($chatMessages as $msg)
					<div class="chat {{ $msg->role === 'user' ? 'chat-end' : 'chat-start' }}">
						<div class="chat-bubble {{ $msg->role === 'user' ? 'chat-bubble-primary' : '' }}">
							{{-- MODIFIED: Render initial messages as Markdown for rich formatting. --}}
							{!! \Illuminate\Support\Str::markdown($msg->content) !!}
						</div>
					</div>
				@endforeach
			</div>
			{{-- Chat Input Form --}}
			<form id="chat-form" class="p-3 border-t border-base-300 flex items-end gap-2 bg-base-100">
				<textarea id="chat-input" placeholder="Ask AuthorWebsiteBuilder to build..." class="textarea textarea-bordered flex-grow resize-none text-sm" rows="1"></textarea>
				<button type="submit" id="chat-submit-btn" class="btn btn-primary btn-square">
					{{-- MODIFIED: The button icon is now managed by JavaScript to show a spinner. --}}
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
					<button id="restore-btn" class="btn btn-outline btn-sm">Restore...</button>
				</div>
				
				<div id="panel-controls" class="flex items-center gap-1">
					{{-- Controls will be dynamically added here by JS --}}
				</div>
			</div>
			
			<div id="preview-content" class="tab-pane flex-grow p-4 overflow-auto">
				<div id="preview-container" class="mx-auto transition-all duration-300 ease-in-out w-full h-full">
					<iframe id="preview-iframe" src="{{ route('website.preview.serve', $website) }}" class="w-full h-full bg-white rounded-md shadow border-0"></iframe>
				</div>
			</div>
			
			<div id="code-content" class="tab-pane flex-grow hidden">
				<div class="flex flex-col h-full border-t border-base-300 overflow-hidden">
					<div class="flex items-center justify-between p-1 border-b border-base-300 bg-base-200">
						<span id="selected-file-info" class="text-sm font-medium px-2 truncate">Select a file</span>
						<div id="code-editor-actions" class="flex items-center gap-1">
							{{-- Edit/Save/Cancel buttons will be injected here --}}
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
			<h3 class="font-bold text-lg">Restore Previous Version</h3>
			<p class="py-4">This will permanently delete the last file operations. This action cannot be undone. How many steps would you like to go back?</p>
			
			<div class="form-control w-full">
				<label class="label" for="restore-steps">
					<span class="label-text">Number of steps to revert (1 step = 1 file change)</span>
				</label>
				<input type="number" id="restore-steps" class="input input-bordered w-full" value="1" min="1" max="50">
			</div>
			
			<div class="modal-action">
				<form method="dialog">
					<button class="btn">Cancel</button>
				</form>
				<button id="confirm-restore-btn" class="btn btn-error">Yes, Restore</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop"><button>close</button></form>
	</dialog>
@endsection

@push('scripts')
	{{-- NEW: Added marked.js library for client-side Markdown rendering. --}}
	<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// --- COMMON ELEMENTS ---
			const websiteId = @json($website->id);
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			
			// --- CHAT ELEMENTS & LOGIC ---
			const chatForm = document.getElementById('chat-form');
			const chatInput = document.getElementById('chat-input');
			const chatSubmitBtn = document.getElementById('chat-submit-btn');
			const chatMessages = document.getElementById('chat-messages');
			const initialPrompt = @json(session('initial_prompt'));
			
			// --- PREVIEW ELEMENTS & LOGIC ---
			const previewIframe = document.getElementById('preview-iframe');
			const previewContainer = document.getElementById('preview-container');
			const panelControls = document.getElementById('panel-controls');
			
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
			let lastUserMessage = ''; // NEW: Store the last user message that resulted in an error.
			let lastResponseWasError = false; // NEW: Flag to check if the last assistant response was an error.
			
			// --- ICON SVGs ---
			const icons = {
				// MODIFIED: Added send and spinner icons for the chat button.
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
			
			// NEW: Helper function to scroll the chat container to the very bottom.
			function scrollChatToBottom() {
				chatMessages.scrollTop = chatMessages.scrollHeight;
			};
			
			// MODIFIED: This function now uses marked.js to render Markdown and scrolls down after adding a message.
			function addMessageToUI(role, content) {
				const messageElement = document.createElement('div');
				messageElement.className = `chat ${role === 'user' ? 'chat-end' : 'chat-start'}`;
				
				const bubble = document.createElement('div');
				bubble.className = `chat-bubble ${role === 'user' ? 'chat-bubble-primary' : ''}`;
				// Use marked.js to parse markdown content for rich text
				bubble.innerHTML = marked.parse(content);
				
				messageElement.appendChild(bubble);
				chatMessages.appendChild(messageElement);
				
				// NEW: Scroll to the new message.
				scrollChatToBottom();
			};
			
			// MODIFIED: This function now provides instant UI feedback and manages the loading state.
			async function sendMessage(message) {
				if (!message.trim()) {
					return;
				}
				
				// A new message is being sent, so the previous error state is no longer relevant for resending.
				lastResponseWasError = false;
				
				addMessageToUI('user', message);
				chatInput.value = '';
				
				// MODIFIED: Disable input and show a spinner on the button to indicate processing.
				chatInput.disabled = true;
				chatSubmitBtn.innerHTML = icons.spinner;
				chatSubmitBtn.classList.add('btn-disabled');
				
				try {
					const response = await fetch("{{ route('websites.chat.store', $website) }}", {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
						body: JSON.stringify({ message: message }),
					});
					if (!response.ok) {
						const errorData = await response.json();
						throw new Error(errorData.error || 'Network response was not ok.');
					}
					const data = await response.json();
					
					// MODIFIED: Add assistant message to the UI. The scroll is handled by addMessageToUI.
					addMessageToUI('assistant', data.assistantMessage.content);
					
					if (data.files_updated) {
						await fetchFiles();
						refreshPreview();
					}
				} catch (error) {
					console.error('Chat error:', error);
					// MODIFIED: Set the error flag, store the failed message, and add a more helpful error message.
					lastResponseWasError = true;
					lastUserMessage = message; // Store the message that failed.
					addMessageToUI('assistant', 'Sorry, an error occurred: ' + error.message + '<br><br><small class="opacity-70">You can try sending your message again by clicking the send button.</small>');
				} finally {
					// MODIFIED: Re-enable the input and restore the original button icon.
					chatInput.disabled = false;
					chatSubmitBtn.innerHTML = icons.send;
					chatSubmitBtn.classList.remove('btn-disabled');
					chatInput.focus();
				}
			};
			
			function refreshPreview() {
				if (previewIframe) {
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
						const initialFile = files.find(f => f.filename === 'index.php' && f.folder === '/') || files[0];
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
				const steps = parseInt(restoreStepsInput.value, 10);
				if (isNaN(steps) || steps < 1) {
					alert('Please enter a valid number of steps.');
					return;
				}
				
				confirmRestoreBtn.classList.add('btn-disabled', 'loading');
				
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
					
					restoreModal.close();
					alert('Successfully restored files!');
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
			
			// MODIFIED: The submit handler now decides whether to resend a failed message or send a new one.
			chatForm.addEventListener('submit', (e) => {
				e.preventDefault();
				const currentMessage = chatInput.value.trim();
				
				// If the input is empty and the last response was an error, resend the last message.
				if (currentMessage === '' && lastResponseWasError) {
					sendMessage(lastUserMessage);
				} else {
					sendMessage(currentMessage);
				}
			});
			
			// MODIFIED: The keydown listener now triggers the submit event to avoid duplicating logic.
			chatInput.addEventListener('keydown', (e) => {
				// If the user presses Enter without holding Ctrl, submit the form.
				if (e.key === 'Enter' && !e.ctrlKey) {
					e.preventDefault(); // Prevent the default action (new line).
					chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
				}
				// If Ctrl+Enter is pressed, the default action (new line) is allowed.
			});
			
			if (initialPrompt) { sendMessage(initialPrompt); }
			
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
						previewContainer.className = 'mx-auto transition-all duration-300 ease-in-out w-[375px] h-[667px] max-w-full max-h-full overflow-hidden rounded-lg shadow-lg border-4 border-neutral';
					} else {
						previewContainer.className = 'mx-auto transition-all duration-300 ease-in-out w-full h-full';
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
			
			// --- INITIALIZATION ---
			
			// NEW: Set the initial icon for the chat submit button.
			chatSubmitBtn.innerHTML = icons.send;
			
			renderPanelControls('preview-content');
			fetchFiles();
			
			// NEW: Scroll the chat to the bottom when the page first loads.
			scrollChatToBottom();
		});
	</script>
@endpush
