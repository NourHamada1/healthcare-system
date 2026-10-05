<?php
// includes/ai_service.php

require_once __DIR__ . '/../config/ai_config.php';

class AIService {

    public static function callOpenAI($system_prompt, $user_prompt, $json_mode = true) {
        $url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_prompt . "\n\n" . $user_prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ]
        ];

        if ($json_mode) {
            $data['generationConfig']['responseMimeType'] = 'application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('cURL error in AIService: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200) {
            error_log('Gemini API Error HTTP ' . $http_code . ': ' . $response);
            return null;
        }

        $decoded = json_decode($response, true);

        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $decoded['candidates'][0]['content']['parts'][0]['text'];
            if ($json_mode) {
                return json_decode($content, true);
            }
            return ['text' => $content];
        }

        return null;
    }
}
?>
