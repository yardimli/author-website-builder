import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import { Wand2, Loader2 } from 'lucide-react'; // Icons
import { useState } from 'react';
import axios from 'axios'; // For AI call
import { useToast } from "@/hooks/use-toast"; // Import toast

export default function UpdateBioForm({ user, status, className = '' }) {
	const { toast } = useToast();
	const { data, setData, patch, errors, processing, recentlySuccessful, reset } = useForm({
		bio: user.bio || '',
	});
	const [aiLoading, setAiLoading] = useState(false);
	
	const submitBio = (e) => {
		e.preventDefault();
		patch(route('profile.bio.update'), {
			preserveScroll: true,
			onSuccess: () => toast({ title: "Bio Updated", description: "Your bio has been saved."}),
			onError: () => toast({ variant: "destructive", title: "Error", description: "Failed to save bio."}),
		});
	};
	
	const generateBio = async () => {
		setAiLoading(true);
		try {
			const response = await axios.post(route('profile.bio.generate'), {
				current_bio: data.bio,
			});
			setData('bio', response.data.generated_bio);
			toast({ title: "AI Suggestion Ready", description: "Review the generated bio and save."});
		} catch (error) {
			console.error("AI Bio generation error:", error);
			const errorMsg = error.response?.data?.error || "Could not generate AI suggestion.";
			toast({ variant: "destructive", title: "AI Error", description: errorMsg });
		} finally {
			setAiLoading(false);
		}
	};
	
	return (
		<section className={className}>
			<header>
				<h2 className="text-lg font-medium text-foreground">Author Bio</h2>
				<p className="mt-1 text-sm text-muted-foreground">
					Tell readers a bit about yourself. This will be displayed on your public author page (if applicable).
				</p>
			</header>
			
			<form onSubmit={submitBio} className="mt-6 space-y-6">
				<div>
					<InputLabel htmlFor="bio" value="Your Bio" />
					<Textarea
						id="bio"
						className="mt-1 block w-full min-h-[150px]" // Give it some height
						value={data.bio}
						onChange={(e) => setData('bio', e.target.value)}
						maxLength={5000} // Match backend validation
					/>
					<InputError message={errors.bio} className="mt-2" />
				</div>
				
				<div className="flex items-center justify-between gap-4">
					<div className="flex items-center gap-4">
						<Button type="submit" disabled={processing}>Save Bio</Button>
						<Transition
							show={recentlySuccessful && status === 'profile-bio-updated'}
							enter="transition ease-in-out"
							enterFrom="opacity-0"
							leave="transition ease-in-out"
							leaveTo="opacity-0"
						>
							<p className="text-sm text-muted-foreground">Saved.</p>
						</Transition>
					</div>
					<Button
						type="button"
						variant="outline"
						onClick={generateBio}
						disabled={aiLoading || processing}
						title="Generate placeholder using AI"
					>
						{aiLoading ? (
							<Loader2 className="mr-2 h-4 w-4 animate-spin" />
						) : (
							<Wand2 className="mr-2 h-4 w-4" />
						)}
						AI Placeholder
					</Button>
				</div>
			</form>
		</section>
	);
}
