import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link } from '@inertiajs/react';
import { Button } from "@/components/ui/button"; // Import Button
import { Moon, Sun } from 'lucide-react'; // Import Icons
import useTheme from '@/hooks/useTheme'; // Import the custom hook

export default function Authenticated({ user, header, children }) {
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const { theme, setTheme, resolvedTheme } = useTheme();
    
    const toggleTheme = () => {
        // Simple toggle between light and dark for the button action
        setTheme(resolvedTheme === 'light' ? 'dark' : 'light');
    };
    
    return (
      // Add the theme class to the root div if needed, though applying to <html> is standard
      <div className="min-h-screen bg-background"> {/* Use theme variables */}
          <nav className="bg-card border-b border-border"> {/* Use theme variables */}
              <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                  <div className="flex justify-between h-16">
                      <div className="flex">
                          <div className="shrink-0 flex items-center">
                              <Link href="/">
                                  <ApplicationLogo className="block h-9 w-auto fill-current text-foreground" /> {/* Use theme variable */}
                              </Link>
                          </div>
                          
                          <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                              <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                                  Dashboard
                              </NavLink>
                          </div>
                      </div>
                      
                      <div className="hidden sm:flex sm:items-center sm:ms-6">
                          {/* Theme Toggle Button */}
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={toggleTheme}
                            className="me-4 text-foreground hover:text-foreground/80" // Use theme variable & adjust margin
                            aria-label="Toggle theme"
                          >
                              {resolvedTheme === 'dark' ? (
                                <Sun className="h-[1.2rem] w-[1.2rem]" />
                              ) : (
                                <Moon className="h-[1.2rem] w-[1.2rem]" />
                              )}
                          </Button>
                          {/* End Theme Toggle Button */}
                          
                          <div className="ms-3 relative">
                              <Dropdown>
                                  <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                              type="button"
                                              className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-muted-foreground bg-card hover:text-foreground focus:outline-none transition ease-in-out duration-150" // Use theme variables
                                            >
                                                {user.name}
                                                
                                                <svg
                                                  className="ms-2 -me-0.5 h-4 w-4"
                                                  xmlns="http://www.w3.org/2000/svg"
                                                  viewBox="0 0 20 20"
                                                  fill="currentColor"
                                                >
                                                    <path
                                                      fillRule="evenodd"
                                                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                      clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                  </Dropdown.Trigger>
                                  
                                  <Dropdown.Content>
                                      <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                      <Dropdown.Link href={route('logout')} method="post" as="button">
                                          Log Out
                                      </Dropdown.Link>
                                  </Dropdown.Content>
                              </Dropdown>
                          </div>
                      </div>
                      
                      {/* Mobile Menu Button */}
                      <div className="-me-2 flex items-center sm:hidden">
                          {/* Theme Toggle Button (Mobile) - Optional but recommended */}
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={toggleTheme}
                            className="me-2 text-muted-foreground hover:text-foreground" // Adjust styling/margin
                            aria-label="Toggle theme"
                          >
                              {resolvedTheme === 'dark' ? (
                                <Sun className="h-5 w-5" />
                              ) : (
                                <Moon className="h-5 w-5" />
                              )}
                          </Button>
                          {/* End Theme Toggle Button (Mobile) */}
                          
                          <button
                            onClick={() => setShowingNavigationDropdown((previousState) => !previousState)}
                            className="inline-flex items-center justify-center p-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted focus:outline-none focus:bg-accent focus:text-accent-foreground transition duration-150 ease-in-out" // Use theme variables
                          >
                              <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                  <path
                                    className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                  />
                                  <path
                                    className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M6 18L18 6M6 6l12 12"
                                  />
                              </svg>
                          </button>
                      </div>
                  </div>
              </div>
              
              {/* Responsive Navigation Menu */}
              <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
                  <div className="pt-2 pb-3 space-y-1">
                      <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                          Dashboard
                      </ResponsiveNavLink>
                  </div>
                  
                  <div className="pt-4 pb-1 border-t border-border"> {/* Use theme variable */}
                      <div className="px-4">
                          <div className="font-medium text-base text-foreground">{user.name}</div> {/* Use theme variable */}
                          <div className="font-medium text-sm text-muted-foreground">{user.email}</div> {/* Use theme variable */}
                      </div>
                      
                      <div className="mt-3 space-y-1">
                          <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                          <ResponsiveNavLink method="post" href={route('logout')} as="button">
                              Log Out
                          </ResponsiveNavLink>
                      </div>
                  </div>
              </div>
          </nav>
          
          {header && (
            <header className="bg-card shadow"> {/* Use theme variable */}
                <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{header}</div>
            </header>
          )}
          
          <main>{children}</main>
      </div>
    );
}
