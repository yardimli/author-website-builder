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
							{{-- Sanitize and display content --}}
							{!! nl2br(e($msg->content)) !!}
						</div>
					</div>
				@endforeach
			</div>
			{{-- Chat Input Form --}}
			<form id="chat-form" class="p-3 border-t border-base-300 flex items-end gap-2 bg-base-100">
				<textarea id="chat-input" placeholder="Ask AuthorWebsiteBuilder to build..." class="textarea textarea-bordered flex-grow resize-none text-sm" rows="1"></textarea>
				<button type="submit" id="chat-submit-btn" class="btn btn-primary btn-square">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
				</button>
			</form>
		</div>
		
		{{-- Right Panel: Preview/Code --}}
		<div class="flex flex-col w-full md:w-2/3 bg-base-200/50">
			{{-- MODIFIED: Added a header to contain tabs and controls --}}
			<div class="flex justify-between items-center p-2 border-b border-base-300 bg-base-100">
				<div role="tablist" class="tabs tabs-bordered">
					<a role="tab" class="tab tab-active" data-tab-content="preview-content">Preview</a>
					<a role="tab" class="tab" data-tab-content="code-content">Code</a>
				</div>
				
				{{-- ADDED: Controls for the right panel --}}
				<div id="panel-controls" class="flex items-center gap-1">
					{{-- Controls will be dynamically added here by JS --}}
				</div>
			</div>
			
			{{-- Preview Content --}}
			{{-- MODIFIED: Wrapped iframe in a container for responsive toggling --}}
			<div id="preview-content" class="tab-pane flex-grow p-4 overflow-auto">
				<div id="preview-container" class="mx-auto transition-all duration-300 ease-in-out w-full h-full">
					<iframe id="preview-iframe" src="{{ route('website.preview.serve', ['website' => $website->id, 'path' => '']) }}" class="w-full h-full bg-white rounded-md shadow border-0"></iframe>
				</div>
			</div>
			
			{{-- MODIFIED: Replaced placeholder with a full code editor structure --}}
			<div id="code-content" class="tab-pane flex-grow hidden">
				<div class="flex flex-col h-full border-t border-base-300 overflow-hidden">
					{{-- Header for file info and edit buttons --}}
					<div class="flex items-center justify-between p-1 border-b border-base-300 bg-base-200">
						<span id="selected-file-info" class="text-sm font-medium px-2 truncate">Select a file</span>
						<div id="code-editor-actions" class="flex items-center gap-1">
							{{-- Edit/Save/Cancel buttons will be injected here --}}
						</div>
					</div>
					{{-- Main area with file list and editor --}}
					<div class="flex flex-grow overflow-hidden">
						{{-- File List Sidebar --}}
						<div id="code-file-list" class="w-1/3 md:w-1/4 border-r border-base-300 bg-base-100 overflow-y-auto p-2 space-y-1">
							<div class="text-center p-4 text-sm opacity-70">Loading files...</div>
						</div>
						{{-- Code Viewer/Editor Area --}}
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
@endsection

@push('scripts')
	{{-- MODIFIED: Major additions to JS to handle new UI features --}}
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// --- COMMON ELEMENTS ---
			const websiteId = @json($website->id);
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			
			// --- CHAT ELEMENTS & LOGIC (Existing) ---
			const chatForm = document.getElementById('chat-form');
			const chatInput = document.getElementById('chat-input');
			const chatSubmitBtn = document.getElementById('chat-submit-btn');
			const chatMessages = document.getElementById('chat-messages');
			const initialPrompt = @json(session('initial_prompt'));
			
			// --- PREVIEW ELEMENTS & LOGIC (New) ---
			const previewIframe = document.getElementById('preview-iframe');
			const previewContainer = document.getElementById('preview-container');
			const panelControls = document.getElementById('panel-controls');
			
			// --- CODE EDITOR ELEMENTS & LOGIC (New) ---
			const codeFileList = document.getElementById('code-file-list');
			const selectedFileInfo = document.getElementById('selected-file-info');
			const codeEditorActions = document.getElementById('code-editor-actions');
			const codeViewer = document.getElementById('code-viewer');
			const codeEditor = document.getElementById('code-editor');
			
			// --- STATE MANAGEMENT (New) ---
			let files = [];
			let selectedFile = null;
			let isEditing = false;
			let isSaving = false;
			
			// --- ICON SVGs (New) ---
			const icons = {
				monitor: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>`,
				smartphone: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>`,
				edit: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>`,
				save: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>`,
				cancel: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>`,
				loading: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`
			};
			
			// --- FUNCTIONS ---
			
			// Chat function (existing)
			function addMessageToUI(role, content) { /* ... no changes ... */ }
			
			// Chat send function (modified to refresh files on update)
			async function sendMessage(message) {
				if (!message.trim()) return;
				
				addMessageToUI('user', message);
				chatInput.value = '';
				chatInput.disabled = true;
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
					addMessageToUI('assistant', data.assistantMessage.content);
					
					if (data.files_updated) {
						await fetchFiles(); // Refresh file list
						refreshPreview();   // Refresh iframe
					}
					
				} catch (error) {
					console.error('Chat error:', error);
					addMessageToUI('assistant', 'Sorry, an error occurred: ' + error.message);
				} finally {
					chatInput.disabled = false;
					chatSubmitBtn.classList.remove('btn-disabled');
					chatInput.focus();
				}
			}
			
			// Preview refresh function (new)
			function refreshPreview() {
				if (previewIframe) {
					const url = "{{ route('website.preview.serve', ['website' => $website->id, 'path' => '']) }}";
					previewIframe.src = url + '?t=' + new Date().getTime();
				}
			}
			
			// Function to render the file list in the code editor (new)
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
			}
			
			// Function to fetch files from the API (new)
			async function fetchFiles() {
				try {
					const response = await fetch("{{ route('api.websites.files.index', $website) }}");
					if (!response.ok) throw new Error('Failed to fetch files.');
					const data = await response.json();
					files = data.files || [];
					renderFileList();
					// Auto-select index.php or the first file if nothing is selected
					if (!selectedFile && files.length > 0) {
						const initialFile = files.find(f => f.filename === 'index.php' && f.folder === '/') || files[0];
						selectFile(initialFile.id);
					}
				} catch (error) {
					console.error('File fetch error:', error);
					codeFileList.innerHTML = `<div class="text-center p-4 text-sm text-error">Could not load files.</div>`;
				}
			}
			
			// Function to handle file selection (new)
			function selectFile(fileId) {
				const file = files.find(f => f.id == fileId);
				if (!file) return;
				
				// Exit editing mode if switching files
				if (isEditing) toggleEditMode(false);
				
				selectedFile = file;
				
				// Update UI
				document.querySelectorAll('#code-file-list button').forEach(btn => {
					btn.classList.toggle('active', btn.dataset.fileId == fileId);
				});
				
				selectedFileInfo.textContent = `${file.folder !== '/' ? `${file.folder.substring(1)}/` : ''}${file.filename} (v${file.version})`;
				codeViewer.textContent = file.content;
				codeEditor.value = file.content;
				
				renderCodeEditorActions();
			}
			
			// Function to toggle between view and edit modes (new)
			function toggleEditMode(editing) {
				isEditing = editing;
				codeViewer.classList.toggle('hidden', isEditing);
				codeEditor.classList.toggle('hidden', !isEditing);
				renderCodeEditorActions();
			}
			
			// Function to render the correct action buttons (Edit/Save/Cancel) (new)
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
			}
			
			// Function to save file changes (new)
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
					await fetchFiles(); // Refresh file list with new versions
					refreshPreview();   // Refresh iframe
					
				} catch (error) {
					console.error('Save error:', error);
					alert('Error saving file: ' + error.message);
				} finally {
					isSaving = false;
					// The button will be re-rendered, so no need to remove loading state manually
				}
			}
			
			// Function to render the correct panel controls based on the active tab (new)
			function renderPanelControls(activeTab) {
				panelControls.innerHTML = '';
				if (activeTab === 'preview-content') {
					panelControls.innerHTML = `
                        <button data-view="desktop" class="btn btn-outline btn-sm btn-square active">${icons.monitor}</button>
                        <button data-view="mobile" class="btn btn-outline btn-sm btn-square">${icons.smartphone}</button>
                    `;
				}
			}
			
			// --- EVENT LISTENERS ---
			
			// Existing chat form listener
			chatForm.addEventListener('submit', (e) => {
				e.preventDefault();
				sendMessage(chatInput.value);
			});
			
			// Auto-submit initial prompt
			if (initialPrompt) {
				sendMessage(initialPrompt);
			}
			
			// Tab switching logic
			const tabs = document.querySelectorAll('[data-tab-content]');
			tabs.forEach(tabLink => {
				tabLink.addEventListener('click', (e) => {
					e.preventDefault();
					const tabContentId = tabLink.dataset.tabContent;
					tabs.forEach(t => t.classList.remove('tab-active'));
					document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
					tabLink.classList.add('tab-active');
					document.getElementById(tabContentId).classList.remove('hidden');
					renderPanelControls(tabContentId); // Render controls for the new tab
				});
			});
			
			// Panel controls listener (for preview mode switching) (new)
			panelControls.addEventListener('click', e => {
				const button = e.target.closest('button[data-view]');
				if (!button) return;
				
				// Update button states
				panelControls.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
				button.classList.add('active');
				
				// Update preview container classes
				if (button.dataset.view === 'mobile') {
					previewContainer.className = 'mx-auto transition-all duration-300 ease-in-out w-[375px] h-[667px] max-w-full max-h-full overflow-hidden rounded-lg shadow-lg border-4 border-neutral';
				} else {
					previewContainer.className = 'mx-auto transition-all duration-300 ease-in-out w-full h-full';
				}
			});
			
			// Code editor action listener (Edit/Save/Cancel) (new)
			codeEditorActions.addEventListener('click', e => {
				const button = e.target.closest('button');
				if (!button) return;
				
				if (button.id === 'edit-btn') toggleEditMode(true);
				if (button.id === 'cancel-btn') toggleEditMode(false);
				if (button.id === 'save-btn') saveFile();
			});
			
			// File selection listener (new)
			codeFileList.addEventListener('click', e => {
				const button = e.target.closest('button[data-file-id]');
				if (button) {
					selectFile(button.dataset.fileId);
				}
			});
			
			// --- INITIALIZATION ---
			renderPanelControls('preview-content'); // Set initial controls for the default tab
			fetchFiles(); // Fetch files for the code editor on page load
		});
	</script>
@endpush
