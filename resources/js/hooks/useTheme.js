import { useState, useEffect } from 'react';

const useTheme = () => {
	// Initialize state:
	// 1. Check localStorage for 'theme'
	// 2. If not found, default to 'system' (or 'light' if you prefer)
	const [theme, setThemeState] = useState(() => {
		if (typeof window === 'undefined') {
			// Avoid errors during SSR or build time
			return 'light';
		}
		return localStorage.getItem('theme') || 'system';
	});
	
	// Resolved theme state (actual 'light' or 'dark' being applied)
	const [resolvedTheme, setResolvedTheme] = useState('light');
	
	useEffect(() => {
		const root = window.document.documentElement;
		const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');
		
		const applyTheme = (newTheme) => {
			let currentTheme;
			if (newTheme === 'system') {
				currentTheme = systemPrefersDark.matches ? 'dark' : 'light';
			} else {
				currentTheme = newTheme;
			}
			
			root.classList.remove('light', 'dark');
			root.classList.add(currentTheme);
			setResolvedTheme(currentTheme); // Update resolved theme state
		};
		
		// Apply theme initially and when 'theme' state changes
		applyTheme(theme);
		
		// Listener for system preference changes (if theme is 'system')
		const handleSystemChange = (e) => {
			if (theme === 'system') {
				applyTheme('system');
			}
		};
		
		systemPrefersDark.addEventListener('change', handleSystemChange);
		
		// Cleanup listener on component unmount
		return () => {
			systemPrefersDark.removeEventListener('change', handleSystemChange);
		};
	}, [theme]); // Re-run effect when theme preference changes
	
	// Function to update theme preference and save to localStorage
	const setTheme = (newTheme) => {
		if (newTheme === 'light' || newTheme === 'dark' || newTheme === 'system') {
			localStorage.setItem('theme', newTheme);
			setThemeState(newTheme);
		} else {
			// Default cycle or specific toggle logic
			const newPreference = theme === 'light' ? 'dark' : 'light';
			localStorage.setItem('theme', newPreference);
			setThemeState(newPreference);
		}
	};
	
	return { theme, setTheme, resolvedTheme };
};

export default useTheme;
