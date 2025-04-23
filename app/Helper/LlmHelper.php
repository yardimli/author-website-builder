<?php

	namespace App\Helper;

	use App\Models\Lesson;
	use App\Models\Question;
	use App\Models\GeneratedImage;
	use App\Models\UserAnswer;
	use Carbon\Carbon;
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Http\UploadedFile;
	use Illuminate\Support\Facades\Auth;

	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;

	use Ahc\Json\Fixer;
	use Illuminate\Support\Str;

	use Google\Cloud\TextToSpeech\V1\AudioConfig;
	use Google\Cloud\TextToSpeech\V1\AudioEncoding;
	use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;
	use Google\Cloud\TextToSpeech\V1\SynthesisInput;
	use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
	use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
	use Exception;
	use Intervention\Image\ImageManager;
	use Normalizer;


	class LlmHelper
	{
		public static function checkLLMsJson()
		{
			// Ensure Storage facade is correctly used
			$llmsJsonPath = storage_path('app/public/llms.json'); // Store in storage/app/public

			// Create public directory if it doesn't exist
			if (!File::exists(storage_path('app/public'))) {
				Storage::disk('public')->makeDirectory('/');
			}


			if (!File::exists($llmsJsonPath) || Carbon::now()->diffInDays(Carbon::createFromTimestamp(File::lastModified($llmsJsonPath))) > 1) {
				try {
					$client = new Client(['timeout' => 30]); // Add timeout
					$response = $client->get('https://openrouter.ai/api/v1/models');
					$data = json_decode($response->getBody(), true);

					if (isset($data['data'])) {
						File::put($llmsJsonPath, json_encode($data['data'], JSON_PRETTY_PRINT)); // Make it readable
					} else {
						Log::warning('Failed to fetch or parse LLMs from OpenRouter.');
						// Fallback: Check if an older file exists, otherwise return empty
						return File::exists($llmsJsonPath) ? json_decode(File::get($llmsJsonPath), true) : [];
					}
				} catch (\Exception $e) {
					Log::error('Error fetching LLMs from OpenRouter: ' . $e->getMessage());
					// Fallback: Check if an older file exists, otherwise return empty
					return File::exists($llmsJsonPath) ? json_decode(File::get($llmsJsonPath), true) : [];
				}
			}

			$llms = json_decode(File::get($llmsJsonPath), true);
			if (!is_array($llms)) { // Handle case where file is corrupted
				Log::error('Failed to decode llms.json');
				return [];
			}


			usort($llms, function ($a, $b) {
				$nameA = $a['name'] ?? '';
				$nameB = $b['name'] ?? '';
				return strcmp($nameA, $nameB);
			});

			// Return array values to reset keys
			return array_values($llms);
		}

		public static function call_llm($llm, $system_prompt, $chat_messages)
		{
			set_time_limit(300);
			session_write_close();

			$llm_base_url = env('OPEN_ROUTER_BASE', 'https://openrouter.ai/api/v1/chat/completions');
			$llm_api_key = senv('OPEN_ROUTER_KEY');
			$llm_model = $llm ?? '';
			if ($llm_model === '') {
				$llm_model = env('DEFAULT_LLM');
			}

			if (empty($llm_api_key)) {
				Log::error("OpenRouter API Key is not configured.");
				return ['content' => 'Error: API key not configured', 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			$all_messages = [];
			$all_messages[] = ['role' => 'system', 'content' => $system_prompt];
			$all_messages = array_merge($all_messages, $chat_messages);

			if (empty($all_messages)) {
				Log::warning("LLM call attempted with no messages.");
				return ['content' => 'Error: No messages provided', 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			$temperature = 0.7; // Slightly lower temp for more predictable JSON
			$max_tokens = 8192; // Adjust based on model/needs

			$data = [
				'model' => $llm_model,
				'messages' => $all_messages,
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
				'stream' => false,
			];

			Log::info("LLM Request to {$llm_base_url} ({$llm_model})");
			Log::debug("LLM Request Data: ", $data);

			$attempt = 0;
			$content = null;
			$prompt_tokens = 0;
			$completion_tokens = 0;
			$last_error = null;

			try {
				$client = new Client(['timeout' => 180.0]);

				$headers = [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $llm_api_key,
					'HTTP-Referer' => env('APP_URL', 'http://localhost'),
					'X-Title' => env('APP_NAME', 'Laravel'),
				];

				$response = $client->post($llm_base_url, [
					'headers' => $headers,
					'json' => $data,
				]);

				$responseBody = $response->getBody()->getContents();
				Log::info("LLM Response Status: " . $response->getStatusCode());
				Log::debug("LLM Raw Response Body: " . $responseBody);

				$complete_rst = json_decode($responseBody, true);

				if (json_last_error() !== JSON_ERROR_NONE) {
					Log::error("Failed to decode LLM JSON response: " . json_last_error_msg());
					Log::error("Raw response causing decoding error: " . $responseBody);
					$last_error = "Failed to decode LLM response.";

					return ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
				}

				// Check for API errors in the response structure
				if (isset($complete_rst['error'])) {
					$error_message = $complete_rst['error']['message'] ?? json_encode($complete_rst['error']);
					Log::error("LLM API Error: " . $error_message);
					$last_error = "LLM API Error: " . $error_message;

					return ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
				}

				// Extract content and usage based on common structures
				if (isset($complete_rst['choices'][0]['message']['content'])) { // OpenAI, Mistral, etc.
					$content = $complete_rst['choices'][0]['message']['content'];
					$prompt_tokens = $complete_rst['usage']['prompt_tokens'] ?? 0;
					$completion_tokens = $complete_rst['usage']['completion_tokens'] ?? 0;
				} elseif (isset($complete_rst['content'][0]['text'])) { // Anthropic
					$content = $complete_rst['content'][0]['text'];
					$prompt_tokens = $complete_rst['usage']['input_tokens'] ?? $complete_rst['usage']['prompt_tokens'] ?? 0; // Anthropic uses input_tokens
					$completion_tokens = $complete_rst['usage']['output_tokens'] ?? $complete_rst['usage']['completion_tokens'] ?? 0; // Anthropic uses output_tokens
				} elseif (isset($complete_rst['candidates'][0]['content']['parts'][0]['text'])) { // Google Gemini
					$content = $complete_rst['candidates'][0]['content']['parts'][0]['text'];
					// Google usage might be elsewhere or not provided by OpenRouter consistently
					$prompt_tokens = $complete_rst['usageMetadata']['promptTokenCount'] ?? 0;
					$completion_tokens = $complete_rst['usageMetadata']['candidatesTokenCount'] ?? 0;
				} else {
					Log::error("Could not find content in LLM response structure.");
					Log::debug("Full response structure: ", $complete_rst);
					$last_error = "Could not find content in LLM response.";

					return ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
				}

			} catch (\GuzzleHttp\Exception\RequestException $e) {
				$statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
				$errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
				Log::error("Guzzle HTTP Request Exception during LLM call (Attempt {$attempt}): Status {$statusCode} - " . $errorBody);
				$last_error = "HTTP Error {$statusCode}";

				if (($statusCode >= 400 && $statusCode < 500 && $statusCode != 429)) {
					return ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
				}
			} catch (\Exception $e) {
				Log::error("General Exception during LLM call (Attempt {$attempt}): " . $e->getMessage());
				$last_error = "General Error: " . $e->getMessage();
				return ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			if ($content === null) {
				Log::error("LLM call failed after {$max_retries} retries. Last error: {$last_error}");
				return ['content' => "Error: " . ($last_error ?: 'LLM call failed after retries.'), 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			return ['content' => $content, 'prompt_tokens' => $prompt_tokens, 'completion_tokens' => $completion_tokens];
		}

		// --- End of LlmHelper class ---
	}
