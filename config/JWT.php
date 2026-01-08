<?php
class JWT {
    private $secret;
    private $algo;

    public function __construct($secret, $algo = 'HS256') {
        $this->secret = $secret;
        $this->algo = $algo;
    }

    public function encode($payload) {
        $header = ['alg' => $this->algo, 'typ' => 'JWT'];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + TOKEN_EXPIRY;
        
        $header_encoded = $this->base64UrlEncode(json_encode($header));
        $payload_encoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $this->secret, true);
        $signature_encoded = $this->base64UrlEncode($signature);
        
        return "$header_encoded.$payload_encoded.$signature_encoded";
    }

    public function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        },
        
        [$header_encoded, $payload_encoded, $signature_encoded] = $parts;
        
        $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $this->secret, true);
        $signature_expected = $this->base64UrlEncode($signature);
        
        if (!hash_equals($signature_encoded, $signature_expected)) {
            return false;
        }
        
        $payload = json_decode($this->base64UrlDecode($payload_encoded), true);
        
        if ($payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }

    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', strlen($data) % 4));
    }
}

function verifyToken() {
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $parts = explode(' ', $headers['Authorization']);
        $token = $parts[1] ?? null;
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit();
    }
    
    $jwt = new JWT(JWT_SECRET);
    $decoded = $jwt->decode($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit();
    }
    
    return $decoded;
}
?>
```

