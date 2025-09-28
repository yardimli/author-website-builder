import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react'; // Import usePage
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"; // Import Alert
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select"; // Import Select
import { Checkbox } from "@/Components/ui/checkbox"; // Import Checkbox
import { Label } from "@/Components/ui/label"; // Import Label
import { Terminal } from "lucide-react"; // Icon for Alert

export default function Dashboard({
                                      auth: authProp, // Rename prop to avoid conflict with usePage().props.auth
                                      websites = [],
                                      hasWebsites,
                                      userBooks = [], // Receive books
                                      prerequisitesMet, // Receive check result
                                      profileComplete,
                                      hasBooks
                                  }) {
    // Get fresh auth info including potentially updated user details
    const { auth } = usePage().props;
    
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        primary_book_id: '', // Add primary book ID
        featured_book_ids: [], // Add featured book IDs (array)
    });
    
    const handleCreateWebsite = (e) => {
        e.preventDefault();
        post(route('websites.store'), {
            preserveScroll: true, // Keep scroll position
            preserveState: true, // Keep component state on validation errors
            onSuccess: () => {
                reset(); // Reset all fields in useForm state
            },
            onError: (err) => {
                console.error("Form errors:", err);
                // Optionally show a toast message for general errors
            }
        });
    };
    
    // Handle checkbox changes for featured books
    const handleFeaturedBookChange = (checked, bookId) => {
        setData(prevData => {
            const currentFeatured = prevData.featured_book_ids || [];
            if (checked) {
                // Add bookId if not already present
                return {
                    ...prevData,
                    featured_book_ids: [...new Set([...currentFeatured, bookId])]
                };
            } else {
                // Remove bookId
                return {
                    ...prevData,
                    featured_book_ids: currentFeatured.filter(id => id !== bookId)
                };
            }
        });
    };
    
    // Filter out the primary book from the featured book options
    const availableFeaturedBooks = userBooks.filter(book => book.id !== parseInt(data.primary_book_id));
    
    return (
      <AuthenticatedLayout
        user={authProp.user} // Use the prop passed initially for layout
        header={<h2 className="font-semibold text-xl text-foreground leading-tight">Your Websites</h2>}
      >
          <Head title="Your Websites" />
          
          <div className="py-12">
              <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                  <div className="bg-card overflow-hidden shadow-sm sm:rounded-lg">
                      <div className="p-6 text-card-foreground">
                          
                          {/* Prerequisite Check */}
                          {!prerequisitesMet && (
                            <Alert variant="destructive" className="mb-6">
                                <Terminal className="h-4 w-4" />
                                <AlertTitle>Action Required</AlertTitle>
                                <AlertDescription>
                                    Before creating a website, please ensure you have:
                                    <ul className="list-disc pl-5 mt-2">
                                        {!profileComplete && (
                                          <li>Completed your profile (name, bio, and profile photo). <Link href={route('profile.edit')} className="font-semibold underline">Go to Profile</Link></li>
                                        )}
                                        {!hasBooks && (
                                          <li>Added at least one book to your profile. <Link href={route('profile.edit')} className="font-semibold underline">Go to Profile</Link></li>
                                        )}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                          )}
                          
                          {/* New Website Form - Show only if prerequisites are met */}
                          {prerequisitesMet && (
                            <Card className="mb-6 border-border">
                                <CardHeader>
                                    <CardTitle>Create a New Website</CardTitle>
                                    <CardDescription>Configure your new project.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleCreateWebsite} className="space-y-6">
                                        {/* Website Name */}
                                        <div>
                                            <Label htmlFor="name">Website Name *</Label>
                                            <Input
                                              type="text"
                                              id="name"
                                              value={data.name}
                                              onChange={(e) => setData('name', e.target.value)}
                                              placeholder="My Awesome Author Site"
                                              className="mt-1"
                                              disabled={processing}
                                              required
                                            />
                                            {errors.name && <p className="text-destructive text-sm mt-1">{errors.name}</p>}
                                        </div>
                                        
                                        {/* Primary Book Selection */}
                                        <div>
                                            <Label htmlFor="primary_book_id">Primary Book *</Label>
                                            <Select
                                              value={data.primary_book_id}
                                              onValueChange={(value) => setData('primary_book_id', value)}
                                              disabled={processing}
                                              required
                                            >
                                                <SelectTrigger id="primary_book_id" className="w-full mt-1">
                                                    <SelectValue placeholder="Select the main book to feature" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {userBooks.map((book) => (
                                                      <SelectItem key={book.id} value={book.id.toString()}>
                                                          {book.title} {book.series_name ? `(${book.series_name} #${book.series_number})` : ''}
                                                      </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.primary_book_id && <p className="text-destructive text-sm mt-1">{errors.primary_book_id}</p>}
                                        </div>
                                        
                                        {/* Featured Books Selection */}
                                        {availableFeaturedBooks.length > 0 && (
                                          <div className="space-y-2">
                                              <Label>Additional Books (Optional)</Label>
                                              <CardDescription>Select other books to showcase.</CardDescription>
                                              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 pt-2">
                                                  {availableFeaturedBooks.map((book) => (
                                                    <div key={book.id} className="flex items-center space-x-2 p-2 border rounded-md">
                                                        <Checkbox
                                                          id={`featured_book_${book.id}`}
                                                          checked={data.featured_book_ids.includes(book.id)}
                                                          onCheckedChange={(checked) => handleFeaturedBookChange(checked, book.id)}
                                                          disabled={processing}
                                                        />
                                                        <Label
                                                          htmlFor={`featured_book_${book.id}`}
                                                          className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 flex-grow cursor-pointer"
                                                        >
                                                            {book.title} {book.series_name ? `(${book.series_name} #${book.series_number})` : ''}
                                                        </Label>
                                                    </div>
                                                  ))}
                                              </div>
                                              {errors.featured_book_ids && <p className="text-destructive text-sm mt-1">{errors.featured_book_ids}</p>}
                                              {errors['featured_book_ids.*'] && <p className="text-destructive text-sm mt-1">{errors['featured_book_ids.*']}</p>}
                                          </div>
                                        )}
                                        
                                        {/* Submit Button */}
                                        <div className="flex justify-end">
                                            <Button type="submit" disabled={processing || !data.name || !data.primary_book_id}>
                                                {processing ? 'Creating...' : 'Create Website'}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                          )}
                          
                          {/* Website List */}
                          {!hasWebsites && websites.length === 0 && prerequisitesMet && (
                            <p className="text-center text-muted-foreground mt-4">
                                You haven't created any websites yet. Use the form above to start!
                            </p>
                          )}
                          
                          {websites.length > 0 && (
                            <div>
                                <h3 className="text-lg font-medium mb-4">Your Existing Websites</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {websites.map((website) => (
                                      <Card key={website.id} className="border-border">
                                          <CardHeader>
                                              <CardTitle className="hover:text-primary">
                                                  <Link href={route('websites.show', website.id)}>
                                                      {website.name}
                                                  </Link>
                                              </CardTitle>
                                              <CardDescription>
                                                  Created: {new Date(website.created_at).toLocaleDateString()}
                                              </CardDescription>
                                          </CardHeader>
                                          <CardContent>
                                              <Link href={route('websites.show', website.id)}>
                                                  <Button variant="outline" size="sm">Open Editor</Button>
                                              </Link>
                                          </CardContent>
                                      </Card>
                                    ))}
                                </div>
                            </div>
                          )}
                      </div>
                  </div>
              </div>
          </div>
      </AuthenticatedLayout>
    );
}
