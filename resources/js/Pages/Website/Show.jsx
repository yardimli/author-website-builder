import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { SendHorizonal, RefreshCw } from 'lucide-react'; // Added RefreshCw
import { cn } from "@/lib/utils"; // Import the cn utility

// --- CodeViewer Component (Keep as is or enhance) ---
const CodeViewer = ({ files }) => {
	const [selectedFile, setSelectedFile] = useState(null);
	
	useEffect(() => {
		if (files && files.length > 0 && !selectedFile) {
			setSelectedFile(files.find(f => f.filename === 'index.html' && f.folder === '/') || files[0]);
		} else if (files && selectedFile && !files.find(f => f.id === selectedFile.id)) {
			// If the selected file is no longer in the list (e.g., after update), select the first one again
			setSelectedFile(files.find(f => f.filename === 'index.html' && f.folder === '/') || files[0] || null);
		}
	}, [files, selectedFile]);
	
	if (!files || files.length === 0) {
		return <div className="p-4 text-muted-foreground h-full flex items-center justify-center">No code files generated yet.</div>;
	}
	
	const editorContentHeightMobile = "h-[calc(100vh-72px-83px-58px-58px)]";
	const editorContentHeightDesktop = "md:h-[calc(100vh-72px-83px-66px)]";
	
	return (
		// Ensure this outer div takes full height of its container (TabsContent)
		<div className="flex h-full border dark:border-gray-700 rounded-md overflow-hidden">
			{/* File List */}
			<div className="w-1/3 md:w-1/4 border-r dark:border-gray-700 bg-muted/30">
				<ul className="p-2 space-y-1">
					{files.map((file) => (
						<li key={file.id}>
							<button
								onClick={() => setSelectedFile(file)}
								className={cn(
									"w-full text-left px-2 py-1 rounded text-sm truncate", // Added truncate
									selectedFile?.id === file.id
										? 'bg-primary/10 dark:bg-primary/20 text-primary font-semibold' // Use theme colors
										: 'hover:bg-muted dark:hover:bg-muted/50' // Use theme colors
								)}
							>
								<span className="block truncate">{file.folder !== '/' ? `${file.folder.substring(1)}/` : ''}{file.filename}</span> {/* Nicer folder display */}
								<span className="text-xs text-muted-foreground">(v{file.version})</span>
							</button>
						</li>
					))}
				</ul>
			</div>
			
			{/* Code Content */}
			<div className={cn("w-2/3 md:w-3/4 flex-1 overflow-auto", editorContentHeightMobile, editorContentHeightDesktop)}>
				{selectedFile ? (
					// ***** CHANGE HERE *****
					// Use whitespace-pre for horizontal scrolling, remove break-words and h-full
					<pre className="text-sm whitespace-pre p-4">
                        <code>{selectedFile.content}</code>
                    </pre>
				) : (
					<div className="p-4 text-muted-foreground h-full flex items-center justify-center">Select a file to view its content.</div>
				)}
			</div>
		</div>
	);
};

// --- End CodeViewer Component ---

export default function WebsiteShow({ auth, website, chatMessages: initialChatMessages }) {
	const [messages, setMessages] = useState(initialChatMessages || []);
	const [currentMessage, setCurrentMessage] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [isFetchingFiles, setIsFetchingFiles] = useState(true); // Loading state for files
	const [files, setFiles] = useState([]);
	const iframeRef = useRef(null);
	const chatEndRef = useRef(null);
	const previewUrl = route('website.preview.serve', { website: website.id, path: '' });
	
	// State for mobile view ('chat' or 'preview')
	const [mobileView, setMobileView] = useState('chat');
	// State for preview/code tab within the right panel (or mobile preview view)
	const [previewCodeTab, setPreviewCodeTab] = useState('preview');
	
	const fetchFiles = async () => {
		setIsFetchingFiles(true);
		try {
			const response = await axios.get(route('api.websites.files.index', { website: website.id }));
			setFiles(response.data.files || []);
		} catch (error) {
			console.error("Error fetching files:", error);
			// TODO: Add user feedback for file fetch error
		} finally {
			setIsFetchingFiles(false);
		}
	};
	
	useEffect(() => {
		fetchFiles();
	}, [website.id]);
	
	useEffect(() => {
		chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
	}, [messages]);
	
	const handleSendMessage = async (e) => {
		e.preventDefault();
		if (!currentMessage.trim() || isLoading) return;
		
		const userMsgContent = currentMessage;
		const userMsg = { role: 'user', content: userMsgContent, created_at: new Date().toISOString(), id: `temp-${Date.now()}` };
		
		setMessages(prev => [...prev, userMsg]);
		setCurrentMessage('');
		setIsLoading(true);
		
		try {
			const response = await axios.post(route('websites.chat.store', { website: website.id }), {
				message: userMsgContent,
			});
			
			// Replace temp user message with actual one if needed (optional)
			// setMessages(prev => prev.map(m => m.id === userMsg.id ? response.data.userMessage : m));
			setMessages(prev => [...prev.filter(m => m.id !== userMsg.id), response.data.userMessage, response.data.assistantMessage]);
			
			
			if (response.data.files_updated) {
				refreshPreview(); // Refresh preview iframe
				await fetchFiles(); // Refetch files for code view
			}
		} catch (error) {
			console.error("Error sending message:", error);
			setMessages(prev => [...prev, {
				role: 'assistant',
				content: `Sorry, I encountered an error processing your request. ${error?.response?.data?.error || error.message}`,
				created_at: new Date().toISOString(),
				id: `error-${Date.now()}`
			}]);
			// Optionally remove the user message that caused the error
			// setMessages(prev => prev.filter(m => m.id !== userMsg.id));
		} finally {
			setIsLoading(false);
		}
	};
	
	const refreshPreview = () => {
		if (iframeRef.current) {
			// More reliable reload
			iframeRef.current.src = 'about:blank'; // Clear first
			setTimeout(() => {
				iframeRef.current.src = previewUrl + '?t=' + Date.now();
			}, 50); // Small delay
		}
	}
	
	// Calculate height dynamically, accounting for header and potential mobile tabs
	// Approx heights: Header=65px, MobileTabs=41px
	const mainContentHeightMobile = "h-[calc(100vh-73px-83px-46px)]";
	const mainContentHeightDesktop = "md:h-[calc(100vh-73px-83px)]";
	
	return (
		<AuthenticatedLayout
			user={auth.user}
			header={<h2 className="font-semibold text-xl text-foreground leading-tight truncate">Editor: {website.name}</h2>}
		>
			<Head title={`Editing ${website.name}`} />
			
			{/* Mobile Tab Selector - Only visible on small screens */}
			<div className="md:hidden border-b border-border bg-background top-[65px] z-10"> {/* Make sticky */}
				<Tabs value={mobileView} onValueChange={setMobileView} className="w-full">
					<TabsList className="grid w-full grid-cols-2 rounded-none h-10"> {/* Adjust height/styling */}
						<TabsTrigger value="chat" className="rounded-none">Chat</TabsTrigger>
						<TabsTrigger value="preview" className="rounded-none">Preview/Code</TabsTrigger>
					</TabsList>
				</Tabs>
			</div>
			
			
			{/* Main Content Area - Flex container for panels */}
			{/* Applies mobile height, then overrides with desktop height */}
			<div className={cn("flex flex-col md:flex-row", mainContentHeightMobile, mainContentHeightDesktop)}>
				
				{/* Left Panel: Chat */}
				<div className={cn(
					"flex flex-col w-full border-r border-border bg-background  overflow-hidden", // Base styles
					"md:w-1/3", // Desktop width
					mobileView === 'chat' ? 'flex' : 'hidden', // Mobile visibility
					"md:flex" // Desktop visibility override (ensures it's flex on desktop)
				)}>
					{/* Chat Messages Area - Takes remaining space */}
					<div className="flex-grow p-4 space-y-4 overflow-y-auto">
						{messages.map((msg, index) => (
							<div key={msg.id || index} className={`flex w-full mb-3 ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
								<div className={`max-w-[85%] p-3 rounded-lg shadow-sm ${msg.role === 'user'
									? 'bg-primary text-primary-foreground'
									: 'bg-muted text-foreground' // Use theme colors
								}`}>
									<p className="text-sm whitespace-pre-wrap break-words">{msg.content}</p>
									{/* Optional: Timestamp */}
									 <p className="text-xs opacity-70 mt-1 text-right">{new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
								</div>
							</div>
						))}
						<div ref={chatEndRef} /> {/* Element to scroll to */}
					</div>
					
					{/* Chat Input Form - Fixed at the bottom */}
					<form onSubmit={handleSendMessage} className="p-3 border-t border-border flex items-end gap-2 bg-background"> {/* Use items-end */}
						<Textarea
							value={currentMessage}
							onChange={(e) => setCurrentMessage(e.target.value)}
							placeholder="Ask AuthorReview to build..."
							className="flex-grow resize-none text-sm" // Ensure text size consistency
							rows={1} // Start with 1 row
							maxRows={5} // Allow expansion up to 5 rows (requires extra logic or a library for auto-resize)
							disabled={isLoading}
							onKeyDown={(e) => {
								if (e.key === 'Enter' && !e.shiftKey) {
									e.preventDefault();
									handleSendMessage(e);
								}
							}}
						/>
						<Button type="submit" disabled={isLoading || !currentMessage.trim()} size="icon" className="shrink-0"> {/* Prevent button shrinking */}
							{isLoading
								? <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-foreground"></div>
								: <SendHorizonal size={18} />
							}
						</Button>
					</form>
				</div>
				
				{/* Right Panel: Preview/Code */}
				<div className={cn(
					"flex flex-col w-full bg-muted/20 h-full", // Base styles, slightly different bg
					"md:w-2/3", // Desktop width
					mobileView === 'preview' ? 'flex' : 'hidden', // Mobile visibility
					"md:flex" // Desktop visibility override
				)}>
					<Tabs value={previewCodeTab} onValueChange={setPreviewCodeTab} className="flex flex-col flex-grow">
						<div className="flex justify-between items-center p-2 border-b border-border bg-background">
							<TabsList className="h-8"> {/* Smaller tabs */}
								<TabsTrigger value="preview" className="text-xs px-2 py-1 h-full">Preview</TabsTrigger>
								<TabsTrigger value="code" className="text-xs px-2 py-1 h-full">Code</TabsTrigger>
							</TabsList>
							{previewCodeTab === 'preview' && (
								<Button onClick={refreshPreview} variant="outline" size="sm" className="h-8 px-2 text-xs">
									<RefreshCw size={14} className="mr-1"/> Refresh
								</Button>
							)}
							{previewCodeTab === 'code' && (
								<Button onClick={fetchFiles} variant="outline" size="sm" className="h-8 px-2 text-xs" disabled={isFetchingFiles}>
									<RefreshCw size={14} className={cn("mr-1", isFetchingFiles && "animate-spin")}/> Refresh
								</Button>
							)}
						</div>
						
						{/* Content area takes remaining space */}
						<TabsContent value="preview" className="flex-grow overflow-auto m-0 p-1 bg-white dark:bg-black"> {/* Added padding and bg */}
							<iframe
								ref={iframeRef}
								src={previewUrl}
								title={`${website.name} Preview`}
								className="w-full h-full border-0 bg-white"
								sandbox="allow-scripts allow-same-origin" // Security: Adjust sandbox as needed
							/>
						</TabsContent>
						<TabsContent value="code" className="flex-grow overflow-hidden m-0 p-1">
							{isFetchingFiles ? (
								<div className="h-full flex items-center justify-center text-muted-foreground">Loading files...</div>
							) : (
								<CodeViewer files={files} />
							)}
						</TabsContent>
					</Tabs>
				</div>
			</div>
		</AuthenticatedLayout>
	);
}
