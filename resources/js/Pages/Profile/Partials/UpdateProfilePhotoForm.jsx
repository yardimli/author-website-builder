import { useState, useRef } from 'react';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton'; // For remove button
import { useForm, usePage, router } from '@inertiajs/react'; // Import router for DELETE
import { Transition } from '@headlessui/react';
import { Avatar, AvatarFallback, AvatarImage } from "@/Components/ui/avatar"; // Use Shadcn Avatar
import { Button } from "@/Components/ui/button"; // Use Shadcn Button
import { Input } from "@/Components/ui/input"; // Use Shadcn Input
import { User, Trash2 } from 'lucide-react'; // Icons

export default function UpdateProfilePhotoForm({ user, status, className = '' }) {
	const photoInput = useRef(null);
	const [photoPreview, setPhotoPreview] = useState(null);
	
	const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm({
		photo: null,
	});
	
	const handlePhotoChange = (e) => {
		const file = e.target.files[0];
		if (!file) return;
		
		setData('photo', file);
		
		const reader = new FileReader();
		reader.onload = (e) => {
			setPhotoPreview(e.target.result);
		};
		reader.readAsDataURL(file);
	};
	
	const submit = (e) => {
		e.preventDefault();
		post(route('profile.photo.update'), {
			forceFormData: true,
			preserveScroll: true,
			onSuccess: () => {
				reset();
				setPhotoPreview(null);
			},
			onError: () => {
				if (errors.photo) {
					reset('photo');
					photoInput.current.value = null; // Clear file input visually
				}
			},
		});
	};
	
	const removePhoto = () => {
		if (confirm('Are you sure you want to remove your profile photo?')) {
			router.delete(route('profile.photo.delete'), {
				preserveScroll: true,
				onSuccess: () => {
					setPhotoPreview(null); // Clear preview if successful
				}
			});
		}
	};
	
	const clearPhotoSelection = () => {
		reset('photo');
		setPhotoPreview(null);
		if (photoInput.current) {
			photoInput.current.value = null;
		}
	}
	
	return (
		<section className={className}>
			<header>
				<h2 className="text-lg font-medium text-foreground">Profile Photo</h2>
				<p className="mt-1 text-sm text-muted-foreground">
					Update your profile photo. Recommended size: 200x200px. Max 2MB.
				</p>
			</header>
			
			<form onSubmit={submit} className="mt-6 space-y-6">
				<div className="flex items-center space-x-4">
					<Avatar className="h-20 w-20 rounded-md border bg-muted overflow-hidden">
						<AvatarImage
							src={photoPreview || user.profile_photo_url || undefined}
							alt={user.name}
							className="object-contain w-full h-full"
						/>
						<AvatarFallback className="flex items-center justify-center w-full h-full">
							<User className="h-10 w-10 text-muted-foreground" />
						</AvatarFallback>
					</Avatar>
					
					<div className="flex-grow space-y-2">
						<InputLabel htmlFor="photo" value="New Photo" className="sr-only" />
						<Input
							id="photo"
							type="file"
							className="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20"
							onChange={handlePhotoChange}
							ref={photoInput}
							accept="image/*"
						/>
						<InputError message={errors.photo} className="mt-2" />
						{photoPreview && (
							<Button type="button" variant="ghost" size="sm" onClick={clearPhotoSelection} className="text-xs">
								Clear Selection
							</Button>
						)}
					</div>
					
					{user.profile_photo_path && (
						<Button
							type="button"
							variant="destructive"
							size="icon"
							onClick={removePhoto}
							title="Remove current photo"
							disabled={processing}
						>
							<Trash2 className="h-4 w-4" />
						</Button>
					)}
				</div>
				
				
				<div className="flex items-center gap-4">
					<Button type="submit" disabled={processing || !data.photo}>Save Photo</Button>
					
					<Transition
						show={recentlySuccessful && status === 'profile-photo-updated'}
						enter="transition ease-in-out"
						enterFrom="opacity-0"
						leave="transition ease-in-out"
						leaveTo="opacity-0"
					>
						<p className="text-sm text-muted-foreground">Saved.</p>
					</Transition>
					<Transition
						show={status === 'profile-photo-deleted'}
						enter="transition ease-in-out"
						enterFrom="opacity-0"
						leave="transition ease-in-out"
						leaveTo="opacity-0"
					>
						<p className="text-sm text-muted-foreground">Photo removed.</p>
					</Transition>
				</div>
			</form>
		</section>
	);
}
