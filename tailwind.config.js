/** @type {import('tailwindcss').Config} */
export default {
	content: [
		"./resources/**/*.blade.php",
		"./resources/**/*.js",
	],
	theme: {
		extend: {},
	},
	plugins: [
		require('@tailwindcss/forms'), // MODIFIED: Added the forms plugin
		require('daisyui'),
	],
	// daisyUI config
	daisyui: {
		themes: ["light", "dark"], // enable light and dark themes
		darkTheme: "dark", // set dark theme as the default for prefers-color-scheme
		styled: true,
		base: true,
		utils: true,
		logs: true,
		rtl: false,
	},
}
