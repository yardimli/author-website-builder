import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox"; // Import Checkbox
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator"; // Import Separator
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog"; // For delete confirmation
import { useToast } from "@/hooks/use-toast";
import { PlusCircle, Edit, Trash2, X, Image as ImageIcon, Loader2, Wand2 } from 'lucide-react';
import axios from 'axios';

// --- Single Book Edit/Add Form Component ---
const BookForm = ({ book, onSave, onCancel, isNew = false }) => {
	const { toast } = useToast();
	const { data, setData, post, processing, errors, reset, recentlySuccessful, progress } = useForm({
		title: book?.title || '',
		subtitle: book?.subtitle || '',
		hook: book?.hook || '',
		about: book?.about || '',
		extract: book?.extract || '',
		amazon_link: book?.amazon_link || '',
		other_link: book?.other_link || '',
		published_at: book?.published_at ? new Date(book.published_at).toISOString().split('T')[0] : '',
		is_series: !!book?.series_name,
		series_name: book?.series_name || '',
		series_number: book?.series_number || '',
		cover_image: null,
		remove_cover_image: false,
	});
	
	const [coverPreview, setCoverPreview] = useState(book?.cover_image_url || null);
	const [aiHookLoading, setAiHookLoading] = useState(false);
	const [aiAboutLoading, setAiAboutLoading] = useState(false);
	
	const handleCoverChange = (e) => {
		const file = e.target.files[0];
		if (!file) {
			setData('cover_image', null);
			setCoverPreview(book?.cover_image_url || null); // Revert preview if file cleared
			return;
		};
		setData('cover_image', file);
		const reader = new FileReader();
		reader.onload = (e) => {
			setCoverPreview(e.target.result);
		};
		reader.readAsDataURL(file);
	};
	
	const clearCoverSelection = () => {
		setData('cover_image', null);
		setCoverPreview(book?.cover_image_url || null); // Revert to original or null
		const fileInput = document.getElementById(`cover_image_${book?.id || 'new'}`);
		if (fileInput) fileInput.value = null;
	}
	
	const handleRemoveCoverToggle = (checked) => {
		setData('remove_cover_image', checked);
		if (checked) {
			setCoverPreview(null); // Clear preview when marking for removal
		} else {
			setCoverPreview(book?.cover_image_url || null); // Restore preview if unchecking
		}
	}
	
	const submit = (e) => {
		e.preventDefault();
		const options = {
			preserveScroll: true,
			onSuccess: () => { reset(); onSave(); },
			onError: (err) => {
				console.error("Book save error:", err);
				const hasFieldErrors = Object.keys(err).length > 0;
				toast({ variant: "destructive", title: "Save Failed", description: hasFieldErrors ? "Please check the form for errors." : "An unexpected error occurred." });
			},
			forceFormData: true,
		};
		
		if (isNew) {
			post(route('profile.books.store'), options);
		} else {
			// Use post with _method: 'put' for updates
			post(route('profile.books.update', { book: book.id }), {
				...options,
				_method: 'put',
			});
		}
	};
	
	const generateAiPlaceholder = async (fieldType) => {
		const isLoadingSetter = fieldType === 'hook' ? setAiHookLoading : setAiAboutLoading;
		const routeName = fieldType === 'hook' ? 'profile.books.generate.hook' : 'profile.books.generate.about';
		const currentTitle = data.title;
		const currentSubtitle = data.subtitle;
		
		if (!currentTitle) {
			toast({ variant: "destructive", title: "Missing Info", description: "Please enter a Title before generating AI content." });
			return;
		}
		
		isLoadingSetter(true);
		try {
			const response = await axios.post(route(routeName), {
				title: currentTitle,
				subtitle: currentSubtitle,
			});
			setData(fieldType, response.data.generated_text);
			toast({ title: `AI ${fieldType === 'hook' ? 'Hook' : 'About'} Ready`, description: "Review the generated text and save." });
		} catch (error) {
			console.error(`AI ${fieldType} generation error:`, error);
			const errorMsg = error.response?.data?.error || `Could not generate AI ${fieldType}.`;
			toast({ variant: "destructive", title: "AI Error", description: errorMsg });
		} finally {
			isLoadingSetter(false);
		}
	};
	
	return (
		<form onSubmit={submit} className="space-y-4">
			{/* Cover Image */}
			<div className="flex items-start space-x-4">
				{coverPreview ? (
					<img src={coverPreview} alt="Cover preview" className="w-24 h-36 object-cover rounded border" />
				) : (
					<div className="w-24 h-36 bg-muted rounded border flex items-center justify-center">
						<ImageIcon className="w-8 h-8 text-muted-foreground" />
					</div>
				)}
				<div className="flex-grow space-y-2">
					<InputLabel htmlFor={`cover_image_${book?.id || 'new'}`} value="Cover Image (Optional)" />
					<Input
						id={`cover_image_${book?.id || 'new'}`}
						type="file"
						className="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20"
						onChange={handleCoverChange}
						accept="image/*"
						disabled={processing}
					/>
					{progress && (
						<div className="w-full bg-muted rounded-full h-2.5 dark:bg-gray-700 my-2">
							<div className="bg-blue-600 h-2.5 rounded-full" style={{ width: `${progress.percentage}%` }}></div>
						</div>
					)}
					<InputError message={errors.cover_image} className="mt-1" />
					{data.cover_image && ( // Show clear button only if a *new* file is selected
						<Button type="button" variant="ghost" size="sm" onClick={clearCoverSelection} className="text-xs">Clear Selection</Button>
					)}
					{!isNew && book?.cover_image_url && !data.cover_image && ( // Show remove checkbox only if editing, there's an existing image, and no new image is selected
						<div className="flex items-center space-x-2 pt-2">
							<Checkbox
								id={`remove_cover_${book.id}`}
								checked={data.remove_cover_image}
								onCheckedChange={handleRemoveCoverToggle}
								disabled={processing}
							/>
							<label
								htmlFor={`remove_cover_${book.id}`}
								className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
							>
								Remove current cover image
							</label>
						</div>
					)}
				</div>
			</div>
			
			{/* Text Fields in Grid */}
			<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div>
					<InputLabel htmlFor={`title_${book?.id || 'new'}`} value="Title *" />
					<Input id={`title_${book?.id || 'new'}`} value={data.title} onChange={e => setData('title', e.target.value)} required disabled={processing} />
					<InputError message={errors.title} className="mt-1" />
				</div>
				<div>
					<InputLabel htmlFor={`subtitle_${book?.id || 'new'}`} value="Subtitle" />
					<Input id={`subtitle_${book?.id || 'new'}`} value={data.subtitle} onChange={e => setData('subtitle', e.target.value)} disabled={processing} />
					<InputError message={errors.subtitle} className="mt-1" />
				</div>
			</div>
			
			<div>
				<div className="flex justify-between items-center mb-1">
					<InputLabel htmlFor={`hook_${book?.id || 'new'}`} value="Hook / Tagline" />
					<Button
						type="button"
						variant="ghost"
						size="sm"
						onClick={() => generateAiPlaceholder('hook')}
						disabled={aiHookLoading || processing || !data.title}
						title="Generate hook using AI (requires Title)"
						className="text-xs"
					>
						{aiHookLoading ? (
							<Loader2 className="mr-1 h-3 w-3 animate-spin" />
						) : (
							<Wand2 className="mr-1 h-3 w-3" />
						)}
						AI
					</Button>
				</div>
				<Textarea id={`hook_${book?.id || 'new'}`} value={data.hook} onChange={e => setData('hook', e.target.value)} rows={2} disabled={processing || aiHookLoading} />
				<InputError message={errors.hook} className="mt-1" />
			</div>
			
			<div>
				<div className="flex justify-between items-center mb-1">
					<InputLabel htmlFor={`about_${book?.id || 'new'}`} value="About the Book" />
					<Button
						type="button"
						variant="ghost"
						size="sm"
						onClick={() => generateAiPlaceholder('about')}
						disabled={aiAboutLoading || processing || !data.title}
						title="Generate 'About the Book' using AI (requires Title)"
						className="text-xs"
					>
						{aiAboutLoading ? (
							<Loader2 className="mr-1 h-3 w-3 animate-spin" />
						) : (
							<Wand2 className="mr-1 h-3 w-3" />
						)}
						AI
					</Button>
				</div>
				<Textarea id={`about_${book?.id || 'new'}`} value={data.about} onChange={e => setData('about', e.target.value)} rows={4} disabled={processing || aiAboutLoading} />
				<InputError message={errors.about} className="mt-1" />
			</div>
			
			<div>
				<InputLabel htmlFor={`extract_${book?.id || 'new'}`} value="Longer Extract (e.g., First Chapter)" />
				<Textarea id={`extract_${book?.id || 'new'}`} value={data.extract} onChange={e => setData('extract', e.target.value)} rows={8} disabled={processing} />
				<InputError message={errors.extract} className="mt-1" />
			</div>
			
			{/* Links and Date */}
			<div className="grid grid-cols-1 md:grid-cols-3 gap-4">
				<div>
					<InputLabel htmlFor={`amazon_link_${book?.id || 'new'}`} value="Amazon Link" />
					<Input type="text" id={`amazon_link_${book?.id || 'new'}`} value={data.amazon_link} onChange={e => setData('amazon_link', e.target.value)} placeholder="https://" disabled={processing} />
					<InputError message={errors.amazon_link} className="mt-1" />
				</div>
				<div>
					<InputLabel htmlFor={`other_link_${book?.id || 'new'}`} value="Other Link (e.g., Website)" />
					<Input type="text" id={`other_link_${book?.id || 'new'}`} value={data.other_link} onChange={e => setData('other_link', e.target.value)} placeholder="https://" disabled={processing} />
					<InputError message={errors.other_link} className="mt-1" />
				</div>
				<div>
					<InputLabel htmlFor={`published_at_${book?.id || 'new'}`} value="Publishing Date" />
					<Input type="date" id={`published_at_${book?.id || 'new'}`} value={data.published_at} onChange={e => setData('published_at', e.target.value)} disabled={processing} />
					<InputError message={errors.published_at} className="mt-1" />
				</div>
			</div>
			
			{/* Series Info */}
			<div className="space-y-4 rounded-md border p-4">
				<div className="flex items-center space-x-2">
					<Checkbox
						id={`is_series_${book?.id || 'new'}`}
						checked={data.is_series}
						onCheckedChange={(checked) => setData('is_series', checked)}
						disabled={processing}
					/>
					<label
						htmlFor={`is_series_${book?.id || 'new'}`}
						className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
					>
						Part of a series?
					</label>
				</div>
				{data.is_series && (
					<div className="grid grid-cols-1 md:grid-cols-2 gap-4 pl-6">
						<div>
							<InputLabel htmlFor={`series_name_${book?.id || 'new'}`} value="Series Name *" />
							<Input id={`series_name_${book?.id || 'new'}`} value={data.series_name} onChange={e => setData('series_name', e.target.value)} required={data.is_series} disabled={processing} />
							<InputError message={errors.series_name} className="mt-1" />
						</div>
						<div>
							<InputLabel htmlFor={`series_number_${book?.id || 'new'}`} value="Book Number *" />
							<Input type="number" min="1" id={`series_number_${book?.id || 'new'}`} value={data.series_number} onChange={e => setData('series_number', e.target.value)} required={data.is_series} disabled={processing} />
							<InputError message={errors.series_number} className="mt-1" />
						</div>
					</div>
				)}
			</div>
			
			{/* Actions */}
			<div className="flex justify-end items-center gap-4 pt-4">
				<Button type="button" variant="outline" onClick={onCancel} disabled={processing}>
					Cancel
				</Button>
				<Button type="submit" disabled={processing}>
					{processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
					{isNew ? 'Add Book' : 'Save Changes'}
				</Button>
			</div>
		</form>
	);
}


// --- Main Component ---
export default function ManageBooksForm({ books = [], status, className = '' }) {
	const [editingBookId, setEditingBookId] = useState(null);
	const [showAddForm, setShowAddForm] = useState(false);
	const { toast } = useToast();
	
	const handleEdit = (bookId) => {
		setShowAddForm(false); // Close add form if open
		setEditingBookId(bookId);
	};
	
	const handleCancel = () => {
		setEditingBookId(null);
		setShowAddForm(false);
	};
	
	// This function is called by BookForm on successful save/update
	const handleSave = () => {
		setEditingBookId(null); // Close edit form
		setShowAddForm(false);  // Ensure add form is closed
		// Toast can be triggered here after form is closed
		toast({
			title: status === 'book-created' ? "Book Created" : "Book Updated",
			description: `Book details saved successfully.` // Generic message, specific title is harder without passing data back
		});
		// Inertia automatically refreshes data on redirect, no manual fetch needed
	};
	
	const handleDelete = (book) => {
		router.delete(route('profile.books.destroy', { book: book.id }), {
			preserveScroll: true,
			onSuccess: () => toast({ title: "Book Deleted", description: `"${book.title}" removed.` }),
			onError: () => toast({ variant: "destructive", title: "Error", description: "Failed to delete book." }),
		});
	};
	
	return (
		<section className={className}>
			<header className="flex justify-between items-center mb-6">
				<div>
					{/* Removed title/description as it's now part of the Tab */}
					{/* <h2 className="text-lg font-medium text-foreground">Your Books</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage the books you want to showcase.
                    </p> */}
				</div>
				{!showAddForm && editingBookId === null && (
					<Button onClick={() => { setEditingBookId(null); setShowAddForm(true); }} variant="outline">
						<PlusCircle className="mr-2 h-4 w-4" /> Add New Book
					</Button>
				)}
			</header>
			
			<div className="space-y-6">
				{/* Add New Book Form */}
				{showAddForm && (
					<Card>
						<CardHeader>
							<CardTitle>Add New Book</CardTitle>
						</CardHeader>
						<CardContent>
							{/* Pass the correct callbacks */}
							<BookForm onSave={handleSave} onCancel={handleCancel} isNew={true} />
						</CardContent>
					</Card>
				)}
				
				{/* List Existing Books */}
				{books.map((book) => (
					<Card key={book.id}>
						{editingBookId === book.id ? (
							// --- Edit Form View ---
							<>
								<CardHeader>
									<CardTitle>Editing: {book.title}</CardTitle>
								</CardHeader>
								<CardContent>
									{/* Pass the correct callbacks */}
									<BookForm book={book} onSave={handleSave} onCancel={handleCancel} isNew={false} />
								</CardContent>
							</>
						) : (
							// --- Display View ---
							<>
								<CardHeader className="flex flex-row justify-between items-start space-x-4">
									<div className="flex items-start space-x-4">
										{book.cover_image_url ? (
											<img src={book.cover_image_url} alt={`${book.title} cover`} className="w-16 h-24 object-cover rounded border flex-shrink-0" />
										) : (
											<div className="w-16 h-24 bg-muted rounded border flex items-center justify-center flex-shrink-0">
												<ImageIcon className="w-6 h-6 text-muted-foreground" />
											</div>
										)}
										<div>
											<CardTitle className="text-lg">{book.title}</CardTitle>
											{book.subtitle && <CardDescription>{book.subtitle}</CardDescription>}
											{book.series_name && <CardDescription className="text-xs mt-1">({book.series_name}, Book {book.series_number})</CardDescription>}
											{book.published_at && <CardDescription className="text-xs mt-1">Published: {new Date(book.published_at).toLocaleDateString()}</CardDescription>}
										</div>
									</div>
									<div className="flex flex-col sm:flex-row gap-2 items-center flex-shrink-0">
										<Button variant="outline" size="sm" onClick={() => handleEdit(book.id)}>
											<Edit className="h-4 w-4" />
											<span className="ml-1 hidden sm:inline">Edit</span>
										</Button>
										<AlertDialog>
											<AlertDialogTrigger asChild>
												<Button variant="destructive" size="sm">
													<Trash2 className="h-4 w-4" />
													<span className="ml-1 hidden sm:inline">Delete</span>
												</Button>
											</AlertDialogTrigger>
											<AlertDialogContent>
												<AlertDialogHeader>
													<AlertDialogTitle>Are you sure?</AlertDialogTitle>
													<AlertDialogDescription>
														This action cannot be undone. This will permanently delete the book "{book.title}" and its cover image.
													</AlertDialogDescription>
												</AlertDialogHeader>
												<AlertDialogFooter>
													<AlertDialogCancel>Cancel</AlertDialogCancel>
													<AlertDialogAction onClick={() => handleDelete(book)} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
														Yes, delete book
													</AlertDialogAction>
												</AlertDialogFooter>
											</AlertDialogContent>
										</AlertDialog>
									</div>
								</CardHeader>
								{/* Optional: display hook/about in collapsed view */}
								{/* <CardContent>
                                    <p className="text-sm text-muted-foreground">{book.hook || book.about?.substring(0, 150) + '...'}</p>
                                </CardContent> */}
							</>
						)}
					</Card>
				))}
				{books.length === 0 && !showAddForm && (
					<p className="text-center text-muted-foreground py-4">You haven't added any books yet.</p>
				)}
			</div>
		</section>
	);
}
