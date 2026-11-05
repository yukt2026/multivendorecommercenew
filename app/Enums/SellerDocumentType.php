<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerDocumentType: string
{
    case PAN_CARD = 'pan_card';
    case GST_CERTIFICATE = 'gst_certificate';
    case FSSAI_LICENSE = 'fssai_license';
    case BANK_PROOF = 'bank_proof';
    case ADDRESS_PROOF = 'address_proof';
    case BUSINESS_REGISTRATION = 'business_registration';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PAN_CARD => 'PAN Card',
            self::GST_CERTIFICATE => 'GST Certificate',
            self::FSSAI_LICENSE => 'FSSAI License',
            self::BANK_PROOF => 'Bank Proof / Cancelled Cheque',
            self::ADDRESS_PROOF => 'Business Address Proof',
            self::BUSINESS_REGISTRATION => 'Certificate of Incorporation / Trade License',
            self::OTHER => 'Other Supporting Document',
        };
    }
}
