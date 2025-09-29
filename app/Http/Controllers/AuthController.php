<?php

	namespace App\Http\Controllers;

	use App\Models\User;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Hash;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Str;

	/**
	 * NEW: This controller handles authentication processes, including the
	 * secure single sign-on from the main BookCoverZone website.
	 */
	class AuthController extends Controller
	{
		/**
		 * Handle an incoming auto-login request from the main website.
		 *
		 * This method decrypts a secure payload, validates it, finds or creates
		 * a corresponding user account, and logs the user in.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function handleAutoLogin(Request $request)
		{
			// --- CONFIGURATION ---
			// NOTE: The secret key must be set in your .env file as AUTO_LOGIN_SECRET_KEY
			// and must be identical to the key on the bookcoverzone site.
			$secretKey = env('AUTO_LOGIN_SECRET_KEY');
			$cipher = "aes-256-cbc";
			$linkDuration = 60; // Link is valid for 60 seconds to prevent replay attacks.

			// --- 1. VALIDATE INCOMING REQUEST ---
			if (!$request->has('payload')) {
				Log::warning('Auto-login failed: Missing payload.');
				return redirect()->route('login')->with('error', 'Invalid auto-login link (code 1).');
			}

			if (empty($secretKey)) {
				Log::error('Auto-login failed: AUTO_LOGIN_SECRET_KEY is not configured in the Laravel app.');
				return redirect()->route('login')->with('error', 'Auto-login is not configured correctly (code 2).');
			}

			// --- 2. DECRYPT PAYLOAD ---
			try {
				$payload = $request->query('payload');
				$decodedPayload = base64_decode($payload);

				$ivlen = openssl_cipher_iv_length($cipher);
				$iv = substr($decodedPayload, 0, $ivlen);
				$ciphertext = substr($decodedPayload, $ivlen);

				$decryptedJson = openssl_decrypt($ciphertext, $cipher, $secretKey, OPENSSL_RAW_DATA, $iv);

				if ($decryptedJson === false) {
					throw new \Exception('OpenSSL decryption failed. The secret keys may not match.');
				}

				$payloadData = json_decode($decryptedJson, true);

				if (json_last_error() !== JSON_ERROR_NONE || !isset($payloadData['user_id'], $payloadData['timestamp'])) {
					throw new \Exception('Invalid JSON structure in payload.');
				}
			} catch (\Exception $e) {
				Log::warning('Auto-login failed during decryption: ' . $e->getMessage(), ['payload' => $request->query('payload')]);
				return redirect()->route('login')->with('error', 'The auto-login link is invalid or corrupted (code 3).');
			}

			// --- 3. VALIDATE TIMESTAMP ---
			$timestamp = $payloadData['timestamp'];
			if ((time() - $timestamp) > $linkDuration) {
				Log::warning('Auto-login failed: Link expired.', ['payload_data' => $payloadData]);
				return redirect()->route('login')->with('error', 'The auto-login link has expired. Please try again.');
			}

			// --- 4. FETCH USER FROM SOURCE DB & SYNC ---
			try {
				$bczUserId = (int)$payloadData['user_id'];

				// NOTE: To connect to the 'simpleshop' database, you must add a new database
				// connection named 'mysql_bookcoverzone' in your 'config/database.php' file
				// and add its credentials (DB_HOST_BCZ, etc.) to your .env file.
				$bczUser = DB::connection('mysql_bookcoverzone')->table('users')->where('id', $bczUserId)->first();

				if (!$bczUser) {
					Log::error("Auto-login failed: User with ID {$bczUserId} not found in bookcoverzone database.");
					return redirect()->route('login')->with('error', 'Could not find the original user account (code 4).');
				}

				// Find or create the user in this application's database.
				$laravelUser = User::where('bookcoverzone_user_id', $bczUser->id)
					->orWhere('email', $bczUser->email)
					->first();

				if ($laravelUser) {
					// User exists, update their info and ensure the ID is linked.
					$laravelUser->name = $bczUser->display_name ?: $bczUser->username;
					$laravelUser->bookcoverzone_user_id = $bczUser->id; // Ensure ID is linked for future lookups
					$laravelUser->save();
					Log::info("Auto-login: Found and updated existing user.", ['laravel_user_id' => $laravelUser->id, 'bcz_user_id' => $bczUser->id]);
				} else {
					// User does not exist, create a new one.
					$laravelUser = User::create([
						'name' => $bczUser->display_name ?: $bczUser->username,
						'email' => $bczUser->email,
						'password' => Hash::make(Str::random(40)), // Create a secure, random password
						'email_verified_at' => now(), // Assume email is verified on the source site
						'bookcoverzone_user_id' => $bczUser->id,
					]);
					Log::info("Auto-login: Created new user.", ['laravel_user_id' => $laravelUser->id, 'bcz_user_id' => $bczUser->id]);
				}

				// --- 5. LOG THE USER IN ---
				Auth::login($laravelUser, true); // Log in and "remember" the user.

				return redirect()->route('dashboard');

			} catch (\Exception $e) {
				Log::error('Auto-login failed during database sync: ' . $e->getMessage());
				// This could be a DB connection issue or a programming error.
				return redirect()->route('login')->with('error', 'An error occurred while syncing your account (code 5).');
			}
		}
	}
