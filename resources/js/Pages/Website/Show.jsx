import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { useToast } from "@/hooks/use-toast"

// --- Icons ---
import {
	SendHorizonal, RefreshCw, ExternalLink, Monitor, Smartphone, Edit,
	Save, XCircle, Loader2
} from 'lucide-react';
import { cn } from "@/lib/utils";


// --- CodeViewer Component (Modified) ---
const CodeViewer = ({ files, websiteId, onSaveSuccess, onSaveError }) => {
	const [selectedFile, setSelectedFile] = useState(null);
	const [isEditing, setIsEditing] = useState(false);
	const [editedContent, setEditedContent] = useState('');
	const [isSaving, setIsSaving] = useState(false);
	const { toast } = useToast();
	
	useEffect(() => {
		// Logic to select initial file or update selection when files change
		if (files && files.length > 0) {
			const currentSelectedExists = selectedFile && files.some(f => f.id === selectedFile.id);
			if (!currentSelectedExists) {
				// If current selection is gone (e.g., after save/refresh), try selecting index.php or first file
				const newSelection = files.find(f => f.filename === 'index.php' && f.folder === '/') || files[0];
				setSelectedFile(newSelection);
				setIsEditing(false); // Exit edit mode if file changes
			} else if (!selectedFile) {
				// If no file selected initially, select index.php or first file
				setSelectedFile(files.find(f => f.filename === 'index.php' && f.folder === '/') || files[0]);
			}
		} else {
			setSelectedFile(null); // No files, no selection
			setIsEditing(false);
		}
	}, [files]); // Rerun when files array changes
	
	// Update selected file state if the file content/version changes externally
	useEffect(() => {
		if (selectedFile) {
			const updatedFile = files.find(f => f.id === selectedFile.id);
			if (updatedFile && updatedFile.content !== selectedFile.content) {
				// If the content of the *same file ID* changed (less likely with versioning, but possible)
				// Or more likely, if the version number changed after a save
				setSelectedFile(updatedFile);
				// If editing, maybe warn user? For now, just update the view.
				// If we were editing, the content in the editor might be stale.
				// Let's exit edit mode on external change for simplicity.
				if (isEditing) {
					// console.warn("File content changed externally while editing. Exiting edit mode.");
					// setIsEditing(false);
					// setEditedContent(updatedFile.content); // Or reset editor
				}
			}
		}
	}, [files, selectedFile?.id]); // Rerun if selected file ID or files array changes
	
	
	const handleEditClick = () => {
		if (selectedFile) {
			setEditedContent(selectedFile.content);
			setIsEditing(true);
		}
	};
	
	const handleCancelClick = () => {
		setIsEditing(false);
		setEditedContent(''); // Clear edited content
	};
	
	const handleSaveClick = async () => {
		if (!selectedFile || isSaving) return;
		
		setIsSaving(true);
		try {
			const response = await axios.put(route('api.websites.files.update', { website: websiteId }), {
				folder: selectedFile.folder,
				filename: selectedFile.filename,
				content: editedContent,
				// base_version_id: selectedFile.id // Optional: For concurrency check on backend
			});
			
			setIsEditing(false);
			setEditedContent('');
			toast({ title: "File Saved!", description: `${selectedFile.filename} saved successfully as v${response.data.version}.` });
			onSaveSuccess(response.data); // Notify parent about the new file data
			
		} catch (error) {
			console.error("Error saving file:", error);
			const errorMsg = error.response?.data?.error || "An unexpected error occurred.";
			toast({
				variant: "destructive",
				title: "Save Failed",
				description: errorMsg,
			});
			onSaveError(error); // Notify parent (optional)
		} finally {
			setIsSaving(false);
		}
	};
	
	if (!files || files.length === 0) {
		return <div className="p-4 text-muted-foreground h-full flex items-center justify-center">No code files generated yet.</div>;
	}
	
	// Adjusted heights to account for potential save/cancel bar
	const editorContentHeightMobile = "h-[calc(100vh-72px-46px-55px-42px)]"; // Approx 40px for buttons
	const editorContentHeightDesktop = "md:h-[calc(100vh-72px-55px-44px)]";
	
	return (
		<div className="flex flex-col h-full border dark:border-gray-700 rounded-md overflow-hidden">
			{/* Top Bar: File List Selector / Edit Controls */}
			<div className="flex items-center justify-between p-1 border-b dark:border-gray-700 bg-muted/30">
				{/* File Selector Dropdown (Alternative to sidebar for mobile?) - Or keep sidebar */}
				{/* For now, assuming sidebar is primary navigation */}
				<span className="text-sm font-medium px-2 truncate">
                    {selectedFile ? `${selectedFile.folder !== '/' ? `${selectedFile.folder.substring(1)}/` : ''}${selectedFile.filename} (v${selectedFile.version})` : 'Select a file'}
                 </span>
				
				{/* Action Buttons */}
				<div className="flex items-center gap-1">
					{isEditing ? (
						<>
							<Button
								onClick={handleSaveClick}
								variant="outline"
								size="sm"
								className="h-7 px-2 text-xs bg-green-100 hover:bg-green-200 dark:bg-green-800/50 dark:hover:bg-green-700/60 border-green-300 dark:border-green-600"
								disabled={isSaving || !selectedFile}
								title="Save Changes"
							>
								{isSaving ? <Loader2 size={14} className="animate-spin mr-1" /> : <Save size={14} className="mr-1" />}
								Save
							</Button>
							<Button
								onClick={handleCancelClick}
								variant="ghost"
								size="sm"
								className="h-7 px-2 text-xs"
								disabled={isSaving}
								title="Cancel Edit"
							>
								<XCircle size={14} className="mr-1" />
								Cancel
							</Button>
						</>
					) : (
						<Button
							onClick={handleEditClick}
							variant="outline"
							size="sm"
							className="h-7 px-2 text-xs"
							disabled={!selectedFile || isSaving} // Disable if no file selected or if saving
							title="Edit File"
						>
							<Edit size={14} className="mr-1" />
							Edit
						</Button>
					)}
				</div>
			</div>
			
			{/* Main Area: File List + Editor/Viewer */}
			<div className="flex flex-grow overflow-hidden">
				{/* File List Sidebar */}
				<div className="w-1/3 md:w-1/4 border-r dark:border-gray-700 bg-muted/30 overflow-y-auto">
					<ul className="p-2 space-y-1">
						{files.map((file) => (
							<li key={file.id}>
								<button
									onClick={() => {
										if (isEditing) {
											// Maybe ask for confirmation if changes are unsaved?
											// For now, just switch and cancel edit.
											setIsEditing(false);
											setEditedContent('');
										}
										setSelectedFile(file);
									}}
									className={cn(
										"w-full text-left px-2 py-1 rounded text-sm truncate",
										selectedFile?.id === file.id
											? 'bg-primary/10 dark:bg-primary/20 text-primary font-semibold'
											: 'hover:bg-muted dark:hover:bg-muted/50'
									)}
									title={`${file.folder !== '/' ? `${file.folder.substring(1)}/` : ''}${file.filename}`}
								>
									<span className="block truncate">{file.folder !== '/' ? `${file.folder.substring(1)}/` : ''}{file.filename}</span>
									<span className="text-xs text-muted-foreground">(v{file.version})</span>
								</button>
							</li>
						))}
					</ul>
				</div>
				
				{/* Code Content Area (Viewer or Editor) */}
				<div className={cn("w-2/3 md:w-3/4 flex-1 overflow-auto", editorContentHeightMobile, editorContentHeightDesktop)}> {/* Let flex-grow handle height */}
					{selectedFile ? (
						isEditing ? (
							<Textarea
								value={editedContent}
								onChange={(e) => setEditedContent(e.target.value)}
								placeholder={`Editing ${selectedFile.filename}...`}
								className="w-full h-full p-4 font-mono text-sm rounded-none border-0 focus:ring-0 resize-none dark:bg-gray-900 dark:text-gray-100" // Basic styling, consider a real editor component
								disabled={isSaving}
							/>
						) : (
							<pre className="text-sm whitespace-pre-wrap break-words p-4 font-mono overflow-auto h-full"> {/* Added h-full */}
								<code>{selectedFile.content}</code>
                            </pre>
						)
					) : (
						<div className="p-4 text-muted-foreground h-full flex items-center justify-center">Select a file to view its content.</div>
					)}
				</div>
			</div>
		</div>
	);
};
// --- End CodeViewer Component ---

export default function WebsiteShow({ auth, website, chatMessages: initialChatMessages }) {
// --- State ---
	const [messages, setMessages] = useState(initialChatMessages || []);
	const [currentMessage, setCurrentMessage] = useState('');
	const [isLoading, setIsLoading] = useState(false); // For chat messages
	const [isFetchingFiles, setIsFetchingFiles] = useState(true);
	const [files, setFiles] = useState([]);
	const [mobileView, setMobileView] = useState('chat');
	const [previewCodeTab, setPreviewCodeTab] = useState('preview');
	const [previewMode, setPreviewMode] = useState('desktop');
	const [autoSubmitDone, setAutoSubmitDone] = useState(false); // Track auto-submit
	
	// --- Refs ---
	const iframeRef = useRef(null);
	const chatEndRef = useRef(null);
	
	// --- Hooks ---
	const { toast } = useToast();
	const { props } = usePage(); // Get page props
	console.log("WebsiteShow received props:", props);
	const initial_prompt_from_props = props.initial_prompt; // Explicitly get it
	console.log("Extracted initial_prompt:", initial_prompt_from_props);
	
	// --- Constants ---
	const previewUrl = route('website.preview.serve', { website: website.id, path: '' });
	const mainContentHeightMobile = "h-[calc(100vh-73px-83px)]"; // Adjusted for mobile tabs
	const mainContentHeightDesktop = "md:h-[calc(100vh-76px)]"; // Adjusted for header
	
	const fetchFiles = async () => {
		setIsFetchingFiles(true);
		try {
			const response = await axios.get(route('api.websites.files.index', { website: website.id }));
			setFiles(response.data.files || []);
		} catch (error) {
			console.error("Error fetching files:", error);
			toast({ variant: "destructive", title: "Error", description: "Could not load file list." });
		} finally {
			setIsFetchingFiles(false);
		}
	};
	
	const sendMessageToServer = async (messageToSend) => {
		if (!messageToSend?.trim() || isLoading) return; // Add null/undefined check
		
		const userMsg = {
			role: 'user',
			content: messageToSend,
			created_at: new Date().toISOString(),
			id: `temp-${Date.now()}`
		};
		setMessages(prev => [...prev, userMsg]);
		setIsLoading(true);
		
		try {
			const response = await axios.post(route('websites.chat.store', { website: website.id }), {
				message: messageToSend,
			});
			// Replace temp message with actual messages from backend
			setMessages(prev => [...prev.filter(m => m.id !== userMsg.id), response.data.userMessage, response.data.assistantMessage]);
			if (response.data.files_updated) {
				await fetchFiles(); // Fetch files if LLM updated them
				refreshPreview();
			}
		} catch (error) {
			console.error("Error sending message:", error);
			const errorDetail = error?.response?.data?.error || error.message || "An unknown error occurred";
			// Remove temp user message and add error message
			setMessages(prev => [...prev.filter(m => m.id !== userMsg.id), {
				role: 'assistant',
				content: `Sorry, I encountered an error processing your request. ${errorDetail}`,
				created_at: new Date().toISOString(),
				id: `error-${Date.now()}`
			}]);
			toast({ variant: "destructive", title: "Chat Error", description: errorDetail });
		} finally {
			setIsLoading(false);
		}
	};
	
	// Handler for manual form submission
	const handleSendMessage = (e) => {
		e.preventDefault();
		sendMessageToServer(currentMessage);
		setCurrentMessage(''); // Clear input after initiating send
	};
	
	// Handler for successful file save from CodeViewer
	const handleFileSaveSuccess = (newFileData) => {
		fetchFiles();
		refreshPreview();
	};
	
	// Handler for file save error from CodeViewer (optional)
	const handleFileSaveError = (error) => {
		console.error("File save failed (reported by CodeViewer):", error);
	};
	
	
	const refreshPreview = () => {
		if (iframeRef.current) {
			iframeRef.current.src = 'about:blank';
			setTimeout(() => {
				// Append timestamp to force reload, bypassing cache
				iframeRef.current.src = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
			}, 50);
		}
	}
	
	const openPreviewInNewTab = () => {
		window.open(previewUrl, '_blank');
	}
	
	useEffect(() => {
		fetchFiles();
	}, [website.id]);
	
	useEffect(() => {
		chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
	}, [messages]);
	
	// Effect for auto-submitting the initial prompt
	useEffect(() => {
		console.log("Checking for initial prompt..."); // Debug log
		console.log("Initial prompt:", initial_prompt_from_props ); // Debug log
		// Check if initial_prompt exists, is not empty, and auto-submit hasn't run yet
		if (initial_prompt_from_props  && initial_prompt_from_props .trim() !== '' && !autoSubmitDone) {
			console.log("Received initial prompt, auto-submitting..."); // Debug log
			setAutoSubmitDone(true); // Mark as done immediately to prevent race conditions/re-runs
			sendMessageToServer(initial_prompt_from_props ); // Send the prompt
			// No need to set currentMessage or clear it here, as it's not user input
			// Optional: Clear the prop from Inertia's memory if needed, though flashing should handle this.
			// router.remember({ ...props, initial_prompt_from_props : null }, { replace: true }); // Might be overkill
		}
	}, [initial_prompt_from_props , autoSubmitDone, sendMessageToServer]); // Add dependencies
	
	return (
		<AuthenticatedLayout
			user={auth.user}
		>
			<Head title={`Editing ${website.name}`} />
			
			{/* Mobile Tab Selector */}
			<div className="md:hidden border-b border-border bg-background sticky top-[65px] z-10"> {/* Adjusted sticky */}
				<Tabs value={mobileView} onValueChange={setMobileView} className="w-full">
					<TabsList className="grid w-full grid-cols-2 rounded-none h-10">
						<TabsTrigger value="chat" className="rounded-none">Chat</TabsTrigger>
						<TabsTrigger value="preview" className="rounded-none">Preview/Code</TabsTrigger>
					</TabsList>
				</Tabs>
			</div>
			
			{/* Main Content Area */}
			<div className={cn("flex flex-col md:flex-row", mainContentHeightMobile, mainContentHeightDesktop)}>
				
				{/* Left Panel: Chat */}
				<div className={cn(
					"flex flex-col w-full border-r border-border bg-background overflow-hidden",
					"md:w-1/3",
					mobileView === 'chat' ? 'flex' : 'hidden',
					"md:flex"
				)}>
					{/* Chat Messages Area */}
					<div className="flex-grow p-4 space-y-4 overflow-y-auto">
						{messages.map((msg, index) => (
							<div key={msg.id || index} className={`flex w-full mb-3 ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
								<div className={`max-w-[85%] p-3 rounded-lg shadow-sm ${msg.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'}`}>
									<p className="text-sm whitespace-pre-wrap break-words">{msg.content}</p>
									<p className="text-xs opacity-70 mt-1 text-right">{new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
								</div>
							</div>
						))}
						<div ref={chatEndRef} />
					</div>
					{/* Chat Input Form */}
					<form onSubmit={handleSendMessage} className="p-3 border-t border-border flex items-end gap-2 bg-background">
						<Textarea
							value={currentMessage}
							onChange={(e) => setCurrentMessage(e.target.value)}
							placeholder="Ask AuthorWebsiteBuilder to build..."
							className="flex-grow resize-none text-sm"
							rows={1}
							disabled={isLoading}
							onKeyDown={(e) => {
								if (e.key === 'Enter' && !e.shiftKey) {
									e.preventDefault();
									handleSendMessage(e);
								}
							}}
						/>
						<Button type="submit" disabled={isLoading || !currentMessage.trim()} size="icon" className="shrink-0">
							{isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <SendHorizonal size={18} />}
						</Button>
					</form>
				</div>
				
				{/* Right Panel: Preview/Code */}
				<div className={cn(
					"flex flex-col w-full bg-muted/20 h-full", // Removed fixed height, let flex handle it
					"md:w-2/3",
					mobileView === 'preview' ? 'flex' : 'hidden',
					"md:flex"
				)}>
					<Tabs value={previewCodeTab} onValueChange={setPreviewCodeTab} className="flex flex-col flex-grow"> {/* Added flex-grow */}
						{/* Panel Header with Tabs and Controls */}
						<div className="flex justify-between items-center p-2 border-b border-border bg-background">
							{/* --- START: Website Name and Tabs --- */}
							<div className="flex items-center gap-3"> {/* Increased gap */}
								<TabsList className="h-8">
									<TabsTrigger value="preview" className="text-xs px-2 py-1 h-full">Preview</TabsTrigger>
									<TabsTrigger value="code" className="text-xs px-2 py-1 h-full">Code</TabsTrigger>
								</TabsList>
								<h3 className="font-semibold text-lg text-foreground truncate hidden sm:block" title={website.name}>
									{website.name}
								</h3>
							</div>
							{/* Controls Area */}
							<div className="flex items-center gap-1">
								{previewCodeTab === 'preview' && (
									<>
										{/* View Mode Buttons */}
										<Button onClick={() => setPreviewMode('desktop')} variant={previewMode === 'desktop' ? 'secondary' : 'outline'} size="sm" className="h-8 px-2 text-xs" title="Desktop View">
											<Monitor size={14} />
										</Button>
										<Button onClick={() => setPreviewMode('mobile')} variant={previewMode === 'mobile' ? 'secondary' : 'outline'} size="sm" className="h-8 px-2 text-xs" title="Mobile View">
											<Smartphone size={14} />
										</Button>
										{/* Separator */}
										<div className="w-px h-5 bg-border mx-1"></div>
										{/* Refresh Button */}
										<Button onClick={refreshPreview} variant="outline" size="sm" className="h-8 px-2 text-xs" title="Refresh Preview">
											<RefreshCw size={14} />
										</Button>
										{/* Open in New Tab Button */}
										<Button onClick={openPreviewInNewTab} variant="outline" size="sm" className="h-8 px-2 text-xs" title="Open in New Tab">
											<ExternalLink size={14} />
										</Button>
									</>
								)}
								{previewCodeTab === 'code' && (
									<Button onClick={fetchFiles} variant="outline" size="sm" className="h-8 px-2 text-xs" disabled={isFetchingFiles} title="Refresh File List">
										<RefreshCw size={14} className={cn(isFetchingFiles && "animate-spin")} />
									</Button>
								)}
							</div>
						</div>
						
						{/* Preview Content */}
						<TabsContent value="preview" className="flex-grow overflow-auto m-0 p-4 bg-muted/40 dark:bg-background/30">
							<div className={cn(
								"mx-auto transition-all duration-300 ease-in-out bg-white dark:bg-gray-100", // Added background to iframe wrapper
								previewMode === 'desktop'
									? "w-full h-full border border-border" // Simple border for desktop
									: "w-[375px] h-[667px] max-w-full max-h-full overflow-hidden rounded-lg shadow-lg border-4 border-gray-700 dark:border-gray-600" // Mobile frame
							)}>
								<iframe
									ref={iframeRef}
									src={previewUrl}
									title={`${website.name} Preview`}
									className="w-full h-full border-0" // Iframe itself has no border
									sandbox="allow-scripts allow-same-origin" // Keep sandbox for security
								/>
							</div>
						</TabsContent>
						
						{/* Code Content */}
						<TabsContent value="code" className="flex-grow overflow-hidden m-0 p-0"> {/* Remove padding */}
							{isFetchingFiles ? (
								<div className="h-full flex items-center justify-center text-muted-foreground">Loading files...</div>
							) : (
								<CodeViewer
									files={files}
									websiteId={website.id}
									onSaveSuccess={handleFileSaveSuccess}
									onSaveError={handleFileSaveError}
								/>
							)}
						</TabsContent>
					</Tabs>
				</div>
			</div>
		</AuthenticatedLayout>
	);
}
