<?php
class AzureConfig {
    private $api_base = "Api Base adresi";
    private $api_key = "Api Key";
    private $api_version = "Api Versiyon";
    private $deployment_name = "Hangi openai aracı?";

    public function getEndpoint() {
        return $this->api_base . "openai/deployments/" . $this->deployment_name . "/chat/completions?api-version=" . $this->api_version;
    }

    public function makeRequest($messages) {
        $data = [
            'messages' => $messages,
            'max_tokens' => 800,
            'temperature' => 0.7
        ];

        $ch = curl_init($this->getEndpoint());
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . $this->api_key
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => true
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("API Error (HTTP $httpCode): " . $response);
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg() . "\nRaw response: " . $response);
        }

        return $decoded;
    }
}
?>