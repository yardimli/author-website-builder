// resources/js/Pages/Profile/Edit.jsx

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import UpdateProfilePhotoForm from './Partials/UpdateProfilePhotoForm';
import UpdateBioForm from './Partials/UpdateBioForm';
import ManageBooksForm from './Partials/ManageBooksForm';
import { Head } from '@inertiajs/react'; // Removed usePage as it's not needed for user here
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

// Add 'user' to the destructured props here
export default function Edit({ auth, mustVerifyEmail, status, books, user }) {
    // Remove these lines - we now get 'user' directly from props
    // const { props } = usePage();
    // const user = props.auth.user;
    
    return (
      <AuthenticatedLayout
        user={auth.user} // Keep using auth.user for the layout itself
      >
          <Head title="Profile Settings" />
          
          <div className="py-12">
              <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                  <Tabs defaultValue="profile" className="w-full">
                      <TabsList className="grid w-full grid-cols-4 mb-6">
                          <TabsTrigger value="profile">Profile</TabsTrigger>
                          <TabsTrigger value="books">Books</TabsTrigger>
                          <TabsTrigger value="security">Security</TabsTrigger>
                          <TabsTrigger value="account">Account</TabsTrigger>
                      </TabsList>
                      
                      {/* Profile Tab Content */}
                      <TabsContent value="profile" className="space-y-6">
                          <div className="p-4 sm:p-8 bg-card shadow sm:rounded-lg">
                              {/* Pass the correct 'user' prop down */}
                              <UpdateProfilePhotoForm user={user} status={status} className="max-w-xl" />
                          </div>
                          <div className="p-4 sm:p-8 bg-card shadow sm:rounded-lg">
                              {/* Pass the correct 'user' prop down */}
                              <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                                className="max-w-xl"
                                user={user} // Pass the user object here
                              />
                          </div>
                          <div className="p-4 sm:p-8 bg-card shadow sm:rounded-lg">
                              {/* Pass the correct 'user' prop down */}
                              <UpdateBioForm user={user} status={status} className="max-w-xl" />
                          </div>
                      </TabsContent>
                      
                      {/* Books Tab Content */}
                      <TabsContent value="books">
                          <div className="p-4 sm:p-8 bg-card shadow sm:rounded-lg">
                              {/* This component receives 'books' directly, which is fine */}
                              <ManageBooksForm books={books} status={status} className="max-w-4xl" />
                          </div>
                      </TabsContent>
                      
                      {/* Security Tab Content */}
                      <TabsContent value="security">
                          <div className="p-4 sm:p-8 bg-card shadow sm:rounded-lg">
                              <UpdatePasswordForm className="max-w-xl" />
                          </div>
                      </TabsContent>
                      
                      {/* Account Tab Content */}
                      <TabsContent value="account">
                          <div className="p-4 sm:p-8 bg-card shadow sm:rounded-lg">
                              <DeleteUserForm className="max-w-xl" />
                          </div>
                      </TabsContent>
                  </Tabs>
              </div>
          </div>
      </AuthenticatedLayout>
    );
}
