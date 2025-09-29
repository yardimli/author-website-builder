<?php

	namespace App\Helper;

	use Illuminate\Support\Facades\Log;

	/**
	 * Class CodeSanitizerHelper
	 *
	 * Provides methods to sanitize code snippets, particularly PHP,
	 * to prevent security vulnerabilities in a sandboxed environment.
	 * @package App\Helper
	 */
	class CodeSanitizerHelper
	{
		/**
		 * A list of forbidden PHP functions that could compromise security.
		 * This includes direct filesystem manipulation, command execution, and unsafe code evaluation.
		 * Note: `include` and `require` are handled separately to allow for safe, local includes.
		 *
		 * @var array
		 */
		private static array $forbiddenFunctions = [
			// Filesystem Read/Write/Delete/Manipulation Functions
			'file_get_contents', 'file_put_contents', 'file', 'readfile',
			'fopen', 'fwrite', 'fputs', 'fread', 'fclose', 'fpassthru',
			'unlink', 'delete', 'copy', 'rename', 'move_uploaded_file',
			'mkdir', 'rmdir', 'touch', 'chmod', 'chown', 'chgrp',
			'scandir', 'glob', 'opendir', 'readdir', 'closedir', 'rewinddir',
			'tempnam', 'tmpfile',
			'parse_ini_file', 'is_writable', 'is_readable', 'file_exists',

			// Command Execution Functions
			'exec', 'shell_exec', 'passthru', 'system',
			'popen', 'proc_open', 'pcntl_exec',

			// Unsafe Code Evaluation/Execution Functions
			'eval', 'assert', 'create_function',

			// Other Potentially Dangerous Information/Process Control Functions
			'phpinfo', 'posix_kill', 'posix_mkfifo', 'posix_setpgid', 'posix_setsid', 'posix_setuid',
		];

		/**
		 * Sanitizes a string of PHP code to prevent directory traversal and the use of dangerous functions.
		 *
		 * @param string $code The PHP code to be sanitized.
		 * @return array An associative array with a 'success' boolean and a 'message' string.
		 */
		public static function sanitizePhp(string $code): array
		{
			// 1. Check for directory traversal in include/require statements.
			// This regex looks for include/require followed by a path that starts with '../' (parent directory)
			// or '/' (absolute path), which would break out of the sandbox.
			if (preg_match('/(include|require)(_once)?\s*\(?\s*[\'"](\.\.|\/)/i', $code, $matches)) {
				$message = "Security error: include/require statements cannot access parent or absolute directories. Path cannot start with '{$matches[3]}'.";
				Log::warning($message, ['code_snippet' => substr($code, 0, 250)]);
				return ['success' => false, 'message' => $message];
			}

			// 2. Check for the use of any forbidden functions from the list.
			// This regex looks for the function names as whole words followed by an opening parenthesis
			// to avoid false positives on variable or class names.
			$forbiddenFunctionsPattern = '/\b(' . implode('|', self::$forbiddenFunctions) . ')\s*\(/i';
			if (preg_match($forbiddenFunctionsPattern, $code, $matches)) {
				$message = "Security error: Usage of the forbidden PHP function '{$matches[1]}' is not allowed.";
				Log::warning($message, ['code_snippet' => substr($code, 0, 250)]);
				return ['success' => false, 'message' => $message];
			}

			// If no issues are found, the code is considered clean.
			return ['success' => true, 'message' => 'PHP code is clean.'];
		}
	}
