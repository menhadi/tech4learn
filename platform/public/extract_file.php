<?php
header('Content-Type: application/json');
error_reporting(0);

$result = ['success' => false, 'text' => '', 'char_count' => 0, 'error' => '', 'method' => '', 'time' => '', 'language' => ''];

$startTime = microtime(true);

$defaultLanguage = [
    'code' => 'eng',
    'name' => 'English',
    'google' => 'en',
    'ocrspace' => 'eng',
    'tesseract' => 'eng',
];
$langConfig = ['languages' => [$defaultLanguage]];
$langConfigPath = firstExistingPath(array_filter([
    getenv('OCR_LANGUAGE_CONFIG') ?: null,
    dirname(__DIR__) . '/lang_config.json',
    dirname(dirname(__DIR__)) . '/lang_config.json',
]));
if ($langConfigPath) {
    $configuredLanguages = json_decode((string) file_get_contents($langConfigPath), true);
    if (is_array($configuredLanguages) && !empty($configuredLanguages['languages'])) {
        $langConfig = $configuredLanguages;
    }
}
$availableLangs = ['eng' => $defaultLanguage];
foreach ($langConfig['languages'] as $language) {
    if (!empty($language['code'])) {
        $availableLangs[$language['code']] = array_merge($defaultLanguage, $language);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tempFile = $file['tmp_name'];
    $text = '';
    
    $lang = $_POST['lang'] ?? $_GET['lang'] ?? 'eng';
    $langCode = explode('+', $lang)[0];
    
    if (!isset($availableLangs[$langCode])) {
        $langCode = 'eng';
    }
    $langInfo = $availableLangs[$langCode];
    $result['language'] = $langInfo['name'];
    
    if ($ext === 'txt') {
        $text = file_get_contents($tempFile);
        $result['method'] = 'text';
    }
    elseif ($ext === 'pdf') {
        $text = shell_exec('pdftotext ' . escapeshellarg($tempFile) . ' - 2>/dev/null');
        $result['method'] = 'pdf';
    }
    elseif ($ext === 'docx') {
        $text = shell_exec('python3 /usr/local/bin/extract_docx.py ' . escapeshellarg($tempFile) . ' 2>/dev/null');
        if (empty($text)) {
            $text = shell_exec('catdoc ' . escapeshellarg($tempFile) . ' 2>/dev/null');
        }
        $result['method'] = 'docx';
    }
    elseif ($ext === 'doc') {
        $text = shell_exec('antiword ' . escapeshellarg($tempFile) . ' 2>/dev/null');
        $result['method'] = 'doc';
    }
    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        $processedImage = fastPreprocess($tempFile);
        $text = '';
        
        $googleKeyFile = firstExistingPath(array_filter([
            getenv('GOOGLE_VISION_KEY_FILE') ?: null,
            dirname(__DIR__) . '/google-vision-key.json',
            dirname(dirname(__DIR__)) . '/google-vision-key.json',
        ]));
        if ($googleKeyFile && file_exists($googleKeyFile)) {
            $visionResult = googleVisionLang($processedImage, $googleKeyFile, $langInfo);
            if ($visionResult['success'] && strlen(trim($visionResult['text'])) > 10) {
                $text = $visionResult['text'];
                $result['method'] = 'google_vision';
            }
        }
        
        if (empty($text)) {
            $ocrResult = ocrSpaceLang($processedImage, $langInfo);
            if ($ocrResult['success'] && strlen(trim($ocrResult['text'])) > 10) {
                $text = $ocrResult['text'];
                $result['method'] = 'ocr_space';
            }
        }
        
        if (empty($text)) {
            $tessText = shell_exec('tesseract ' . escapeshellarg($processedImage) . ' stdout -l ' . escapeshellarg((string) $langInfo['tesseract']) . ' --psm 6 2>/dev/null');
            if (!empty($tessText) && strlen(trim($tessText)) > 10) {
                $text = $tessText;
                $result['method'] = 'tesseract';
            }
        }
        
        if ($processedImage !== $tempFile && file_exists($processedImage)) {
            unlink($processedImage);
        }
        
        if (empty($text)) {
            $result['error'] = 'Could not extract text from image';
        }
    } else {
        $result['error'] = 'Unsupported file type: ' . $ext;
    }
    
    if (!empty($text)) {
        $text = smartClean($text);
        $result['success'] = true;
        $result['text'] = $text;
        $result['char_count'] = strlen($text);
    } elseif (empty($result['error'])) {
        $result['error'] = 'Could not extract text from file';
    }
}

$result['time'] = round((microtime(true) - $startTime), 2) . 's';
echo json_encode($result);
exit;

function googleVisionLang($imagePath, $keyPath, $langInfo) {
    try {
        $keyData = json_decode(file_get_contents($keyPath), true);
        if (!$keyData || !isset($keyData['client_email'], $keyData['private_key'])) {
            return ['success' => false, 'text' => ''];
        }
        
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $claim = json_encode([
            'iss' => $keyData['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 600,
            'iat' => time()
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Claim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));
        
        openssl_sign($base64Header . '.' . $base64Claim, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $base64Header . '.' . $base64Claim . '.' . $base64Signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $tokenResponse = curl_exec($ch);
        curl_close($ch);
        $tokenData = json_decode($tokenResponse, true);
        
        if (empty($tokenData['access_token'])) {
            return ['success' => false, 'text' => ''];
        }
        
        $imageData = base64_encode(file_get_contents($imagePath));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://vision.googleapis.com/v1/images:annotate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'requests' => [[
                'image' => ['content' => $imageData],
                'features' => [['type' => 'TEXT_DETECTION']],
                'imageContext' => ['languageHints' => [$langInfo['google']]]
            ]]
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['responses'][0]['textAnnotations'][0]['description'])) {
                return ['success' => true, 'text' => $data['responses'][0]['textAnnotations'][0]['description']];
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    return ['success' => false, 'text' => ''];
}

function ocrSpaceLang($imagePath, $langInfo) {
    $apiKey = getenv('OCR_SPACE_API_KEY') ?: '';
    if ($apiKey === '') { return ['success' => false, 'text' => '']; }
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'apikey' => $apiKey,
            'file' => new CURLFile($imagePath),
            'language' => $langInfo['ocrspace'],
            'isOverlayRequired' => false,
            'detectOrientation' => true
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        
        if ($data && !$data['IsErroredOnProcessing']) {
            return ['success' => true, 'text' => $data['ParsedResults'][0]['ParsedText'] ?? ''];
        }
    } catch (Exception $e) {
        // Silent fail
    }
    return ['success' => false, 'text' => ''];
}

function firstExistingPath(array $paths) {
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && file_exists($path)) {
            return $path;
        }
    }

    return null;
}

function fastPreprocess($imagePath) {
    $outputPath = sys_get_temp_dir() . '/fast_' . bin2hex(random_bytes(8)) . '.png';
    $command = 'convert ' . escapeshellarg($imagePath)
        . ' -resize 1500x1500 -auto-level -contrast -contrast -colorspace Gray -deskew 40% '
        . escapeshellarg($outputPath) . ' 2>/dev/null';
    shell_exec($command);
    
    if (file_exists($outputPath) && filesize($outputPath) > 0) {
        return $outputPath;
    }
    return $imagePath;
}

function smartClean($text) {
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\n+/', "\n", $text);
    $text = trim($text);
    return $text;
}
?>
