import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
// No longer need useState for the form field
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

export default function Dashboard({ auth, websites = [], hasWebsites }) {
    // *** USEFORM MANAGING STATE ***
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', // Initialize the 'name' field within useForm's data
    });
    
    const handleCreateWebsite = (e) => {
        e.preventDefault();
        // No need for manual trim check here if backend validation handles it
        post(route('websites.store'), {
            // No need to pass data explicitly, useForm sends `data`
            preserveState: true,
            onSuccess: () => {
                reset('name'); // Reset the specific field in useForm state
            },
            onError: (err) => {
                console.error("Form errors:", err);
            }
        });
    };
    
    
    return (
      <AuthenticatedLayout
        user={auth.user}
        header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Your Websites</h2>}
      >
          <Head title="Your Websites" />
          
          <div className="py-12">
              <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                  <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                      <div className="p-6 text-gray-900 dark:text-gray-100">
                          
                          {/* New Website Form */}
                          <Card className="mb-6">
                              <CardHeader>
                                  <CardTitle>Create a New Website</CardTitle>
                                  <CardDescription>Give your new project a name to get started.</CardDescription>
                              </CardHeader>
                              <CardContent>
                                  <form onSubmit={handleCreateWebsite} className="flex gap-4 items-center">
                                      <Input
                                        type="text"
                                        // *** BIND TO useForm data ***
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="My Awesome Project"
                                        className="flex-grow"
                                        disabled={processing}
                                        id="name"
                                        name="name"
                                      />
                                      <Button type="submit" disabled={processing || !data.name.trim()}>
                                          {processing ? 'Creating...' : 'New Website'}
                                      </Button>
                                  </form>
                                  {errors.name && <p className="text-red-500 text-sm mt-2">{errors.name}</p>}
                              </CardContent>
                          </Card>
                          
                          
                          {/* Website List */}
                          {!hasWebsites && !websites.length && (
                            <p className="text-center text-gray-500 dark:text-gray-400 mt-4">
                                You haven't created any websites yet. Use the form above to start!
                            </p>
                          )}
                          
                          {websites.length > 0 && (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {websites.map((website) => (
                                  <Card key={website.id}>
                                      <CardHeader>
                                          <CardTitle className="hover:text-indigo-600 dark:hover:text-indigo-400">
                                              <Link href={route('websites.show', website.id)}>
                                                  {website.name}
                                              </Link>
                                          </CardTitle>
                                          <CardDescription>
                                              Created: {new Date(website.created_at).toLocaleDateString()}
                                          </CardDescription>
                                      </CardHeader>
                                      <CardContent>
                                          {/* Add more info or actions if needed */}
                                          <Link href={route('websites.show', website.id)}>
                                              <Button variant="outline" size="sm">Open Editor</Button>
                                          </Link>
                                      </CardContent>
                                  </Card>
                                ))}
                            </div>
                          )}
                      </div>
                  </div>
              </div>
          </div>
      </AuthenticatedLayout>
    );
}
