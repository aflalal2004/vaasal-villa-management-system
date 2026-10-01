<?php

namespace LockBridge;

/** Minimal HTTPS client for the Vaasal Villa HMS Lock Bridge API (outbound connections only). */
class ApiClient
{
    public function __construct(private string $baseUrl, private string $token, private bool $verifyTls = true) {}

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(rtrim($this->baseUrl, '/').'/'.ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$this->token, 'Accept: application/json', 'Content-Type: application/json'],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('Network error: '.$err);
        }
        $json = json_decode($raw, true) ?? [];
        if ($status >= 400) {
            throw new \RuntimeException('HTTP '.$status.': '.($json['message'] ?? substr($raw, 0, 200)));
        }
        return $json;
    }
}
