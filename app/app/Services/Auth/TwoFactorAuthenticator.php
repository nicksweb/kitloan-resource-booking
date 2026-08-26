<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) enrolment and verification for local admin accounts, plus
 * one-time recovery codes. Pure PHP — no imagick/gd; the QR is rendered as
 * inline SVG.
 */
class TwoFactorAuthenticator
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** otpauth:// URI for manual entry into an authenticator app. */
    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            $user->email,
            $secret,
        );
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->otpauthUri($user, $secret));
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        return $code !== '' && $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * @return list<string> plaintext codes (show once); store the hashes.
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** @param list<string> $plaintext */
    public function hashRecoveryCodes(array $plaintext): array
    {
        return array_map(fn (string $c) => Hash::make($c), $plaintext);
    }

    /**
     * Consume one recovery code if it matches. Returns true and rewrites the
     * user's stored (hashed) codes without the used one; false if no match.
     */
    public function consumeRecoveryCode(User $user, string $candidate): bool
    {
        $candidate = trim($candidate);
        $stored = $user->two_factor_recovery_codes ?? [];

        foreach ($stored as $i => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($stored[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($stored)])->save();

                return true;
            }
        }

        return false;
    }
}
