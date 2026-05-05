<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitingCard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VisitingCardApiController extends Controller
{
    private ?array $visionDebug = null;
    private ?array $translateDebug = null;

    public function extract(Request $request)
    {
        $validated = $request->validate([
            'card_image' => 'required|image|max:5120',
            'ocr_text' => 'nullable|string',
            'original_language' => 'nullable|string|max:50',
        ]);

        $companyId = (int) $request->user()->company_id;
        $imagePath = $request->file('card_image')->store("visiting-cards/{$companyId}", 'public');

        $originalText = trim((string) ($validated['ocr_text'] ?? ''));
        if ($originalText === '') {
            $originalText = $this->extractTextFromVision($request->file('card_image'));
        }
        $detectedLanguage = $validated['original_language'] ?? null;
        if (!$detectedLanguage && preg_match('/[\x{0A80}-\x{0AFF}]/u', $originalText)) {
            $detectedLanguage = 'gu';
        }

        $englishText = $this->translateToEnglish($originalText, $detectedLanguage);
        $parsed = $this->extractFieldsWithContext($englishText, $originalText);

        return response()->json([
            'success' => true,
            'message' => 'Card extracted successfully.',
            'data' => [
                'image_path' => $imagePath,
                'original_language' => $detectedLanguage,
                'original_text' => $originalText ?: null,
                'english_text' => $englishText ?: null,
                'fields' => $parsed,
                'debug' => app()->environment('local') ? [
                    'vision' => $this->visionDebug,
                    'translate' => $this->translateDebug,
                    'has_vision_key' => env('GOOGLE_VISION_API_KEY') ? true : false,
                    'has_translate_key' => env('GOOGLE_TRANSLATE_API_KEY') ? true : false,
                ] : null,
            ],
        ]);
    }

    public function extractBulk(Request $request)
    {
        $validated = $request->validate([
            'card_images' => 'required|array|min:1|max:50',
            'card_images.*' => 'required|image|max:5120',
            'original_language' => 'nullable|string|max:50',
        ]);

        $companyId = (int) $request->user()->company_id;
        $requestedLanguage = $validated['original_language'] ?? null;
        $results = [];

        foreach (($validated['card_images'] ?? []) as $index => $file) {
            $this->visionDebug = null;
            $this->translateDebug = null;

            try {
                $imagePath = $file->store("visiting-cards/{$companyId}", 'public');
                $originalText = $this->extractTextFromVision($file);
                $detectedLanguage = $requestedLanguage;
                if (!$detectedLanguage && preg_match('/[\x{0A80}-\x{0AFF}]/u', (string) $originalText)) {
                    $detectedLanguage = 'gu';
                }

                $englishText = $this->translateToEnglish((string) $originalText, $detectedLanguage);
                $parsed = $this->extractFieldsWithContext((string) $englishText, (string) $originalText);

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'image_path' => $imagePath,
                    'original_language' => $detectedLanguage,
                    'original_text' => $originalText ?: null,
                    'english_text' => $englishText ?: null,
                    'fields' => $parsed,
                    'error' => null,
                    'debug' => app()->environment('local') ? [
                        'vision' => $this->visionDebug,
                        'translate' => $this->translateDebug,
                    ] : null,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'image_path' => null,
                    'original_language' => $requestedLanguage,
                    'original_text' => null,
                    'english_text' => null,
                    'fields' => null,
                    'error' => $e->getMessage(),
                    'debug' => app()->environment('local') ? [
                        'exception' => get_class($e),
                    ] : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk card extraction completed.',
            'count' => count($results),
            'success_count' => count(array_filter($results, fn($r) => !empty($r['success']))),
            'failed_count' => count(array_filter($results, fn($r) => empty($r['success']))),
            'data' => $results,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate([
            'image_path' => 'nullable|string|max:500',
            'name' => 'nullable|string|max:255',
            'mobile_no' => 'nullable|string|max:30',
            'mobile_numbers' => 'nullable|array',
            'mobile_numbers.*' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:191',
            'pincode' => 'nullable|string|max:20',
            'original_language' => 'nullable|string|max:50',
            'original_text' => 'nullable|string',
            'english_text' => 'nullable|string',
            'raw_payload' => 'nullable|array',
        ]);

        $mobileNumbers = $this->normalizeMobileNumbersArray($validated['mobile_numbers'] ?? []);
        if (empty($mobileNumbers) && !empty($validated['mobile_no'])) {
            $mobileNumbers = array_values(array_filter([$this->normalizePhone($validated['mobile_no'])]));
        }

        $card = VisitingCard::create(array_merge($validated, [
            'company_id' => $companyId,
            'uploaded_by' => $request->user()->id,
            'mobile_no' => $mobileNumbers[0] ?? $this->normalizePhone($validated['mobile_no'] ?? null),
            'mobile_numbers' => !empty($mobileNumbers) ? $mobileNumbers : null,
            'pincode' => $this->normalizePincode($validated['pincode'] ?? null),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Visiting card saved successfully.',
            'data' => $card,
        ]);
    }

    public function bulkSave(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate([
            'records' => 'required|array|min:1|max:200',
            'records.*.image_path' => 'nullable|string|max:500',
            'records.*.name' => 'nullable|string|max:255',
            'records.*.mobile_no' => 'nullable|string|max:30',
            'records.*.mobile_numbers' => 'nullable|array',
            'records.*.mobile_numbers.*' => 'nullable|string|max:30',
            'records.*.email' => 'nullable|email|max:191',
            'records.*.address' => 'nullable|string',
            'records.*.city' => 'nullable|string|max:191',
            'records.*.pincode' => 'nullable|string|max:20',
            'records.*.original_language' => 'nullable|string|max:50',
            'records.*.original_text' => 'nullable|string',
            'records.*.english_text' => 'nullable|string',
            'records.*.raw_payload' => 'nullable|array',
        ]);

        $saved = [];
        $failed = [];

        foreach (($validated['records'] ?? []) as $index => $record) {
            try {
                $mobileNumbers = $this->normalizeMobileNumbersArray($record['mobile_numbers'] ?? []);
                if (empty($mobileNumbers) && !empty($record['mobile_no'])) {
                    $mobileNumbers = array_values(array_filter([$this->normalizePhone($record['mobile_no'])]));
                }

                $card = VisitingCard::create([
                    'company_id' => $companyId,
                    'uploaded_by' => $request->user()->id,
                    'image_path' => $record['image_path'] ?? null,
                    'name' => $record['name'] ?? null,
                    'mobile_no' => $mobileNumbers[0] ?? $this->normalizePhone($record['mobile_no'] ?? null),
                    'mobile_numbers' => !empty($mobileNumbers) ? $mobileNumbers : null,
                    'email' => $record['email'] ?? null,
                    'address' => $record['address'] ?? null,
                    'city' => $record['city'] ?? null,
                    'pincode' => $this->normalizePincode($record['pincode'] ?? null),
                    'original_language' => $record['original_language'] ?? null,
                    'original_text' => $record['original_text'] ?? null,
                    'english_text' => $record['english_text'] ?? null,
                    'raw_payload' => $record['raw_payload'] ?? null,
                ]);

                $saved[] = $card;
            } catch (\Throwable $e) {
                $failed[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk save completed.',
            'count' => count($validated['records']),
            'saved_count' => count($saved),
            'failed_count' => count($failed),
            'data' => $saved,
            'failed' => $failed,
        ]);
    }

    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $rows = VisitingCard::query()
            ->where('company_id', $companyId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->search);
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_no', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('pincode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->to_date);
            })
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(function ($row) {
            $row->image_url = $row->image_path ? Storage::disk('public')->url($row->image_path) : null;
            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function dateWiseReport(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', Carbon::now()->format('Y-m-d'));

        $rows = VisitingCard::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->orderBy('created_at')
            ->get();

        $rows->transform(function ($row) {
            $row->image_url = $row->image_path ? Storage::disk('public')->url($row->image_path) : null;
            return $row;
        });

        $summary = $rows->groupBy(fn($row) => $row->created_at->format('Y-m-d'))
            ->map(fn($group, $date) => [
                'date' => $date,
                'total_uploads' => $group->count(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'summary' => $summary,
            'total' => $rows->count(),
            'data' => $rows->values(),
        ]);
    }

    private function translateToEnglish(string $text, ?string $originalLanguage): string
    {
        if ($text === '') {
            return '';
        }

        $apiKey = (string) env('GOOGLE_TRANSLATE_API_KEY', '');
        if ($apiKey === '') {
            return $text;
        }

        try {
            $payload = [
                'q' => $text,
                'target' => (string) env('GOOGLE_TRANSLATE_TARGET', 'en'),
                'format' => 'text',
            ];

            if ($originalLanguage) {
                $payload['source'] = $originalLanguage;
            }

            $response = Http::withOptions(['verify' => false])->timeout(30)
                ->post("https://translation.googleapis.com/language/translate/v2?key={$apiKey}", $payload);

            if (!$response->successful()) {
                $this->translateDebug = [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ];
                return $text;
            }

            $this->translateDebug = [
                'status' => $response->status(),
                'ok' => true,
            ];

            $translated = (string) data_get($response->json(), 'data.translations.0.translatedText', '');
            $translated = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return trim($translated) !== '' ? $translated : $text;
        } catch (\Throwable $e) {
            $this->translateDebug = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ];
            return $text;
        }
    }

    private function extractTextFromVision($uploadedFile): string
    {
        $apiKey = (string) env('GOOGLE_VISION_API_KEY', '');
        if ($apiKey === '') {
            return '';
        }

        try {
            $realPath = $uploadedFile->getRealPath();
            if (!$realPath || !is_file($realPath)) {
                $this->visionDebug = [
                    'exception' => 'RuntimeException',
                    'message' => 'Uploaded file real path not found.',
                ];
                return '';
            }

            $rawImage = file_get_contents($realPath);
            if ($rawImage === false || $rawImage === '') {
                $this->visionDebug = [
                    'exception' => 'RuntimeException',
                    'message' => 'Unable to read uploaded image bytes.',
                ];
                return '';
            }

            $base64Image = base64_encode($rawImage);
            $payload = [
                'requests' => [[
                    'image' => [
                        'content' => $base64Image,
                    ],
                    'features' => [[
                        'type' => 'DOCUMENT_TEXT_DETECTION',
                    ]],
                    'imageContext' => [
                        'languageHints' => ['gu', 'hi', 'en', 'te', 'ta', 'kn', 'ml'],
                    ],
                ]],
            ];

            $response = Http::withOptions(['verify' => false])->timeout(30)
                ->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", $payload);

            if (!$response->successful()) {
                $this->visionDebug = [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ];
                return '';
            }

            $this->visionDebug = [
                'status' => $response->status(),
                'ok' => true,
                'error' => data_get($response->json(), 'responses.0.error'),
            ];

            $text = (string) data_get($response->json(), 'responses.0.fullTextAnnotation.text', '');
            if (trim($text) !== '') {
                return trim($text);
            }

            $annotations = (array) data_get($response->json(), 'responses.0.textAnnotations', []);
            if (!empty($annotations)) {
                return trim((string) data_get($annotations, '0.description', ''));
            }

            return '';
        } catch (\Throwable $e) {
            $this->visionDebug = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ];
            return '';
        }
    }

    private function extractFields(string $englishText): array
    {
        return $this->extractFieldsWithContext($englishText, null);
    }

    private function extractFieldsWithContext(string $englishText, ?string $originalText): array
    {
        $normalizedText = $this->normalizeIndicDigits($englishText);
        $phone = null;
        $phones = [];
        $mobileCandidates = [];
        $needsReview = false;
        $pincode = null;
        $city = null;
        $name = null;
        $email = null;

        // Match all Indian mobile numbers with optional +91 and separators/spaces.
        if (preg_match_all('/(?:\+?91[\s\-]*)?([6-9]\d(?:[\s\-]?\d){8})/', $normalizedText, $matches)) {
            foreach (($matches[0] ?? []) as $raw) {
                $n = $this->normalizePhone($raw);
                if ($n) {
                    $phones[] = $n;
                }
            }
            $phones = array_values(array_unique($phones));
            $phone = $phones[0] ?? null;
        }

        // Gujarati-specific extraction: trust only directly extracted Gujarati mobile lines.
        $hasGujaratiText = $originalText && preg_match('/[\x{0A80}-\x{0AFF}]/u', $originalText);
        if ($hasGujaratiText) {
            $fromGujarati = $this->extractGujaratiMobileCandidates((string) $originalText);
            foreach ($fromGujarati as $candidate) {
                $phones[] = $candidate['number'];
                $mobileCandidates[] = [
                    'number' => $candidate['number'],
                    'source' => 'gujarati_line',
                ];
            }

            $phones = array_values(array_unique(array_filter($phones)));
            if (!empty($phones) && !$phone) {
                $phone = $phones[0];
            }
            $needsReview = false;
        }

        // Match pincode as 6 digits or grouped like "396 445".
        if (preg_match('/\b\d{3}[\s\-]?\d{3}\b/', $normalizedText, $m)) {
            $pincode = $this->normalizePincode($m[0]);
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $englishText, $m)) {
            $email = strtolower(trim($m[0]));
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $englishText))));
        $sourceLines = $originalText
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $originalText))))
            : $lines;
        $name = $this->pickBusinessName($lines, $sourceLines);

        // Try to infer city from line containing pincode like "NAVSARI - 396 445".
        $blockedCityTokens = ['mo', 'mob', 'mobile', 'ph', 'phone', 'tel', 'dist', 'pin', 'no'];
        foreach ($lines as $line) {
            if (preg_match('/([A-Za-z][A-Za-z\s\.]+?)\s*[-,]?\s*\d{3}[\s\-]?\d{3}/', $line, $m)) {
                $candidate = trim($m[1], " \t\n\r\0\x0B-.,");
                $candidateLower = strtolower($candidate);
                if (
                    $candidate !== ''
                    && strlen($candidate) >= 3
                    && !in_array($candidateLower, $blockedCityTokens, true)
                    && !preg_match('/^(mo|mob|mobile|ph|tel)\b/i', $candidate)
                ) {
                    $city = $candidate;
                    break;
                }
            }
        }

        // Fallback: pick known city from any line like "Ahmedabad-1".
        if (!$city) {
            foreach ($lines as $line) {
                if (preg_match('/\b(ahmedabad|surat|rajkot|vadodara|navsari|anand|kheda|nikol|naroda|kapadvanj)\b/i', $line, $m)) {
                    $city = $this->normalizeCityName($m[1]);
                    break;
                }
            }
        }

        // Fallback from "Dist.XYZ" pattern.
        if (!$city) {
            foreach ($lines as $line) {
                if (preg_match('/\bDist\.?\s*[:\-]?\s*([A-Za-z][A-Za-z\s]{2,})/i', $line, $m)) {
                    $city = trim($m[1], " \t\n\r\0\x0B-.,");
                    break;
                }
            }
        }

        // Fallback: derive city from the last address line and fuzzy match known cities.
        if (!$city) {
            $addressLine = null;
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if (preg_match('/\b(ph|phone|mob|mobile|mo)\.?\b/i', $line)) {
                    continue;
                }
                if ($line !== '') {
                    $addressLine = $line;
                    break;
                }
            }

            if ($addressLine) {
                $parts = preg_split('/,/', $addressLine) ?: [];
                $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
                if (!empty($parts)) {
                    $lastPart = end($parts);
                    $lastPart = preg_replace('/[^A-Za-z\s]/', '', (string) $lastPart);
                    $lastPart = trim((string) $lastPart);
                    if ($lastPart !== '') {
                        $city = $this->normalizeCityName($lastPart);
                    }
                }
            }
        }

        // Fallback: if pincode missing but city exists, infer a default city pincode.
        if (!$pincode && $city) {
            $pincode = $this->inferPincodeFromCity($city);
        }

        $address = $this->pickAddress($lines);

        return [
            'name' => $name,
            'mobile_no' => $phone,
            'mobile_numbers' => !empty($phones) ? $phones : null,
            'mobile_numbers_candidates' => !empty($mobileCandidates) ? $mobileCandidates : null,
            'needs_review' => $needsReview,
            'email' => $email,
            'address' => $address,
            'city' => $city,
            'pincode' => $pincode,
        ];
    }

    private function pickBusinessName(array $englishLines, array $sourceLines): ?string
    {
        $ignorePattern = '/^(?:\d+|[A-Za-z]{1,3}|m|i|ii|iii|iv|v)$/i';
        $bizKeywordPattern = '/\b(jewel(?:ler|lers|lery)|ornament(?:s)?|gold|silver)\b/i';
        $gujKeywordPattern = '/(જ્વેલ|ઓર્નામેન્ટ|દાગીના)/u';

        // 1) Prefer English line with jewelry business keywords.
        foreach ($englishLines as $line) {
            if (preg_match($ignorePattern, $line)) {
                continue;
            }
            if (preg_match($bizKeywordPattern, $line)) {
                return $line;
            }
        }

        // 2) Prefer Gujarati source line with business keywords.
        foreach ($sourceLines as $line) {
            if (preg_match('/^\d+$/', $line)) {
                continue;
            }
            if (preg_match($gujKeywordPattern, $line)) {
                return $line;
            }
        }

        // 3) Fallback to first meaningful English line.
        foreach ($englishLines as $line) {
            if (preg_match($ignorePattern, $line)) {
                continue;
            }
            if (strlen($line) < 4) {
                continue;
            }
            return $line;
        }

        return null;
    }

    private function pickAddress(array $englishLines): ?string
    {
        if (empty($englishLines)) {
            return null;
        }

        $addressStartPattern = '/\b(road|rd\.?|street|st\.?|lane|ln\.?|opp|opposite|near|plot|shop|flat|block|sector|area|nagar|city|dist|taluka|village|pincode|pin|chowk|pol|chambers|arcade)\b/i';
        $cityTailPattern = '/\b(ahmedabad|surat|rajkot|vadodara|navsari|anand|kheda|nikol|naroda)\b/i';

        $startIndex = null;
        foreach ($englishLines as $i => $line) {
            if (preg_match('/\b(ph|phone|mob|mobile|mo)\.?\b/i', $line)) {
                continue;
            }
            if (preg_match($addressStartPattern, $line)) {
                $startIndex = $i;
                break;
            }
            // House/office style start like I-102, A/12, 23-B etc.
            if (preg_match('/^[A-Za-z]?\-?\d{1,4}[A-Za-z]?(?:[\/\-][A-Za-z0-9]+)?\b/', $line)) {
                $startIndex = $i;
                break;
            }
            if (preg_match('/\b(du\.?\s*no|no\.?)\s*[:\-]?\s*\d+/i', $line)) {
                $startIndex = $i;
                break;
            }
            if (substr_count($line, ',') >= 2 && preg_match('/\d/', $line)) {
                $startIndex = $i;
                break;
            }
        }

        if ($startIndex === null) {
            return null;
        }

        $parts = [];
        for ($i = $startIndex; $i < count($englishLines); $i++) {
            $line = trim($englishLines[$i]);
            if ($line === '') {
                continue;
            }
            $parts[] = $line;
            if (preg_match('/\b\d{6}\b/', $line) || preg_match($cityTailPattern, $line)) {
                // Usually address completes by city/pincode line.
                if (count($parts) >= 2) {
                    break;
                }
            }
        }

        if (empty($parts)) {
            return null;
        }

        return implode("\n", $parts);
    }

    private function normalizeCityName(string $candidate): string
    {
        $candidateLower = strtolower(trim($candidate));
        $cityMap = [
            'ahmedabad' => ['ahmedabad', 'ahemedabad', 'ahemeabad', 'ahmdabad', 'amdavad', 'ahmmedabad'],
            'surat' => ['surat'],
            'rajkot' => ['rajkot'],
            'vadodara' => ['vadodara', 'baroda'],
            'navsari' => ['navsari'],
            'anand' => ['anand'],
            'kheda' => ['kheda'],
            'nikol' => ['nikol'],
            'naroda' => ['naroda'],
            'kapadvanj' => ['kapadvanj', 'kapdawanj', 'kapadwanj'],
        ];

        foreach ($cityMap as $canonical => $variants) {
            foreach ($variants as $variant) {
                if ($candidateLower === $variant || levenshtein($candidateLower, $variant) <= 2) {
                    return ucfirst($canonical);
                }
            }
        }

        return $candidate;
    }

    private function inferPincodeFromCity(string $city): ?string
    {
        $cityKey = strtolower(trim($city));
        $cityToPincode = [
            'ahmedabad' => '380001',
            'surat' => '395003',
            'rajkot' => '360001',
            'vadodara' => '390001',
            'navsari' => '396445',
            'anand' => '388001',
            'kheda' => '387411',
            'nikol' => '382350',
            'naroda' => '382330',
            'kapadvanj' => '387620',
        ];

        return $cityToPincode[$cityKey] ?? null;
    }

    private function extractGujaratiMobileCandidates(string $originalText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $originalText) ?: [];
        $candidates = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Prefer lines containing Gujarati/English mobile markers.
            if (!preg_match('/(મો|મોબ|mobile|mob|mo\.?)/iu', $line)) {
                continue;
            }

            // Keep Gujarati and ASCII digits with separators only.
            if (preg_match_all('/[\+\d૦૧૨૩૪૫૬૭૮૯\-\s]{10,20}/u', $line, $matches)) {
                foreach ($matches[0] as $chunk) {
                    $normalized = $this->normalizePhone($this->normalizeIndicDigits($chunk));
                    if (!$normalized || strlen($normalized) !== 10 || !preg_match('/^[6-9]/', $normalized)) {
                        continue;
                    }

                    $candidates[] = [
                        'number' => $normalized,
                        'source' => 'gujarati_line',
                    ];
                }
            }
        }

        // Unique by number while preserving first metadata.
        $unique = [];
        foreach ($candidates as $row) {
            $num = $row['number'];
            if (!isset($unique[$num])) {
                $unique[$num] = $row;
            }
        }

        return array_values($unique);
    }

    private function normalizeMobileNumbersArray(array $numbers): array
    {
        $normalized = [];
        foreach ($numbers as $number) {
            $n = $this->normalizePhone(is_string($number) ? $number : null);
            if ($n) {
                $normalized[] = $n;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeIndicDigits(string $text): string
    {
        $map = [
            // Arabic-Indic
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            // Extended Arabic-Indic (Persian/Urdu)
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            // Devanagari (Hindi/Marathi/Nepali)
            '०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4',
            '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9',
            // Bengali/Assamese
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
            // Gurmukhi (Punjabi)
            '੦' => '0', '੧' => '1', '੨' => '2', '੩' => '3', '੪' => '4',
            '੫' => '5', '੬' => '6', '੭' => '7', '੮' => '8', '੯' => '9',
            // Gujarati
            '૦' => '0', '૧' => '1', '૨' => '2', '૩' => '3', '૪' => '4',
            '૫' => '5', '૬' => '6', '૭' => '7', '૮' => '8', '૯' => '9',
            // Odia
            '୦' => '0', '୧' => '1', '୨' => '2', '୩' => '3', '୪' => '4',
            '୫' => '5', '୬' => '6', '୭' => '7', '୮' => '8', '୯' => '9',
            // Tamil
            '௦' => '0', '௧' => '1', '௨' => '2', '௩' => '3', '௪' => '4',
            '௫' => '5', '௬' => '6', '௭' => '7', '௮' => '8', '௯' => '9',
            // Telugu
            '౦' => '0', '౧' => '1', '౨' => '2', '౩' => '3', '౪' => '4',
            '౫' => '5', '౬' => '6', '౭' => '7', '౮' => '8', '౯' => '9',
            // Kannada
            '೦' => '0', '೧' => '1', '೨' => '2', '೩' => '3', '೪' => '4',
            '೫' => '5', '೬' => '6', '೭' => '7', '೮' => '8', '೯' => '9',
            // Malayalam
            '൦' => '0', '൧' => '1', '൨' => '2', '൩' => '3', '൪' => '4',
            '൫' => '5', '൬' => '6', '൭' => '7', '൮' => '8', '൯' => '9',
            // Sinhala
            '෦' => '0', '෧' => '1', '෨' => '2', '෩' => '3', '෪' => '4',
            '෫' => '5', '෬' => '6', '෭' => '7', '෮' => '8', '෯' => '9',
            // Thai
            '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
            '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
            // Lao
            '໐' => '0', '໑' => '1', '໒' => '2', '໓' => '3', '໔' => '4',
            '໕' => '5', '໖' => '6', '໗' => '7', '໘' => '8', '໙' => '9',
            // Myanmar
            '၀' => '0', '၁' => '1', '၂' => '2', '၃' => '3', '၄' => '4',
            '၅' => '5', '၆' => '6', '၇' => '7', '၈' => '8', '၉' => '9',
            // Khmer
            '០' => '0', '១' => '1', '២' => '2', '៣' => '3', '៤' => '4',
            '៥' => '5', '៦' => '6', '៧' => '7', '៨' => '8', '៩' => '9',
        ];

        return strtr($text, $map);
    }

    private function normalizePhone(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return $digits ?: null;
    }

    private function normalizePincode(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        return strlen($digits) >= 6 ? substr($digits, 0, 6) : $digits;
    }
}
