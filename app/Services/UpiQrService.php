<?php

namespace App\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

class UpiQrService
{
    /**
     * Build a upi://pay deep link and render it as a scannable SVG QR
     * (base64-encoded). $amount is nullable -- omitting it produces a
     * generic QR where the scanning app prompts the payer to enter an
     * amount manually (used for the printable notice-board QR).
     */
    public static function generate(
        string $vpa,
        string $payeeName,
        ?float $amount,
        string $transactionNote,
        string $referenceToken
    ): array {
        $upiUrl = "upi://pay?pa=" . urlencode($vpa)
            . "&pn=" . urlencode($payeeName)
            . ($amount !== null ? "&am=" . number_format($amount, 2, '.', '') : '')
            . "&tn=" . urlencode($transactionNote)
            . "&tr=" . urlencode($referenceToken)
            . "&cu=INR";

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $base64Qr = base64_encode($writer->writeString($upiUrl));

        return [
            'qr_code' => $base64Qr,
            'upi_uri' => $upiUrl,
        ];
    }
}
