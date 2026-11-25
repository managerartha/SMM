<?php

namespace App\Domain\Drive;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * OAuth Google untuk akun Gmail perusahaan, scope drive.file
 * (aplikasi hanya bisa mengakses berkas/folder yang ia buat sendiri).
 * Refresh token disimpan terenkripsi (APP_KEY) di storage/app/google/token.json — di luar public & Git.
 */
class GoogleOAuth
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function __construct(private string $clientId, private string $clientSecret, private string $tokenPath) {}

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function hasToken(): bool
    {
        return is_file($this->tokenPath);
    }

    public function authUrl(string $redirectUri, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE.' openid email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $res = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
        if (! $res->successful() || ! $res->json('refresh_token')) {
            throw new DriveException('Gagal menukar kode otorisasi Google: '.$res->body());
        }

        $email = null;
        if ($idToken = $res->json('id_token')) {
            $parts = explode('.', $idToken);
            $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
            $email = $payload['email'] ?? null;
        }

        $this->saveToken(['refresh_token' => $res->json('refresh_token'), 'email' => $email, 'connected_at' => now()->toIso8601String()]);
        Cache::put('smm.google.access_token', $res->json('access_token'), now()->addSeconds(max(60, (int) $res->json('expires_in') - 120)));

        return ['email' => $email];
    }

    public function accessToken(): string
    {
        return Cache::remember('smm.google.access_token', now()->addMinutes(50), function () {
            $token = $this->readToken();
            if (! $token) {
                throw new DriveException('Google Drive belum dihubungkan.');
            }
            $res = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $token['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);
            if (! $res->successful()) {
                throw new DriveException('Token Google Drive tidak bisa diperbarui (mungkin dicabut). Hubungkan ulang di Admin → Google Drive. Detail: '.$res->body());
            }

            return $res->json('access_token');
        });
    }

    public function connectedEmail(): ?string
    {
        return $this->readToken()['email'] ?? null;
    }

    public function forgetAccessToken(): void
    {
        Cache::forget('smm.google.access_token');
    }

    public function disconnect(): void
    {
        $this->forgetAccessToken();
        @unlink($this->tokenPath);
    }

    private function saveToken(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->tokenPath), 0700);
        File::put($this->tokenPath, Crypt::encryptString(json_encode($data)));
        @chmod($this->tokenPath, 0600);
    }

    private function readToken(): ?array
    {
        if (! is_file($this->tokenPath)) {
            return null;
        }

        return json_decode(Crypt::decryptString(File::get($this->tokenPath)), true);
    }
}
