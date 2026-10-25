<?php

namespace App\Helpers;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;

class InvoiceQrHelper
{
    /**
     * Generate a base64-encoded PNG data URI for a QR code.
     */
    public static function generateBase64Png(string $data, int $scale = 5): string
    {
        $options = new QROptions([
            'outputInterface'  => QRGdImagePNG::class,
            'eccLevel'         => EccLevel::M,
            'outputBase64'     => true,
            'scale'            => $scale,
            'addQuietzone'     => true,
            'drawLightModules' => true,
            'quality'          => 6,
            'moduleValues'     => [
                QRMatrix::M_DATA_DARK       => '#000000',
                QRMatrix::M_FINDER_DARK     => '#000000',
                QRMatrix::M_ALIGNMENT_DARK  => '#000000',
                QRMatrix::M_TIMING_DARK     => '#000000',
            ],
        ]);

        $qrcode = new QRCode($options);
        return $qrcode->render($data);
    }

    /**
     * Build the QR data string from provider + order info.
     * Format (ZATCA-inspired):
     *   Seller: {commercial_name}
     *   VAT: {tax_number}
     *   CR: {commercial_register_no}
     *   Invoice: {order_number}
     *   Date: {date}
     *   Total: {total}
     *   VAT Amount: {vat_amount}
     */
    public static function buildInvoiceQrData(
        ?string $commercialName,
        ?string $taxNumber,
        ?string $commercialRegisterNo,
        string  $orderNumber,
        string  $date,
        float   $total,
        float   $vatAmount
    ): string {
        $lines = [];
        $lines[] = 'Seller: ' . ($commercialName ?? 'N/A');
        $lines[] = 'VAT: '    . ($taxNumber ?? 'N/A');
        $lines[] = 'CR: '     . ($commercialRegisterNo ?? 'N/A');
        $lines[] = 'Invoice: '. $orderNumber;
        $lines[] = 'Date: '   . $date;
        $lines[] = 'Total: '  . number_format($total, 2);
        $lines[] = 'VAT: '    . number_format($vatAmount, 2);

        return implode("\n", $lines);
    }
}
