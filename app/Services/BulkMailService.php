<?php

namespace App\Services;

use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\MpdfException;

class BulkMailService
{
    /**
     * @throws MpdfException
     * @throws \Throwable
     */
    public function generate(
        BulkMailCampaign  $campaign,
        BulkMailRecipient $recipient
    ): string {
        $html        = $campaign->renderBody($recipient);
        $subject     = $campaign->renderSubject($recipient);
        $sender      = $campaign->sender_config;
        $sentAt      = $recipient->sent_at ?? now();
        $cc          = array_merge($campaign->cc_emails ?? [], $recipient->cc_emails ?? []);
        $bcc         = $campaign->bcc_emails ?? [];
        $attachments = $campaign->attachment_path ?? [];

        // Detect if content is Arabic/RTL
        $isRtl = self::containsArabic($subject . $html);

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_top'     => 25,
            'margin_bottom'  => 20,
            'margin_left'    => 15,
            'margin_right'   => 15,
            'direction'      => $isRtl ? 'rtl' : 'ltr',
            'autoScriptToLang'   => true,
            'autoLangToFont'     => true,
            'autoArabic'         => true,
            'tempDir'        => storage_path('app/mpdf-tmp'),
        ]);

        // Allow remote images (for logo/signature images from storage)
        $mpdf->imageVars            = [];

        // Set document metadata
        $mpdf->SetTitle($subject);
        $mpdf->SetAuthor($sender['name']);


        // Header (printed on every page like Outlook)
        $mpdf->SetHTMLHeader('
            <table dir="ltr" width="100%" style="font-size:8pt;color:#555;border-bottom:1px solid #ccc;padding-bottom:4px;">
                <tr>
                    <td>' . \Carbon\Carbon::parse($sentAt)->format('d/m/Y, H:i') . '</td>
                    <td align="right">Sent – ' . htmlspecialchars($sender['name']) . ' – Outlook</td>
                </tr>
            </table>
        ');

        // Footer
        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:7.5pt;color:#888;border-top:1px solid #ddd;padding-top:3px;">
                <tr>
                    <td>' . request()->url() . '</td>
                    <td align="right">{PAGENO} / {nbpg}</td>
                </tr>
            </table>
        ');

        $renderedHtml = view('mails.bulk-mail-pdf', compact(
            'html', 'subject', 'sender', 'recipient',
            'sentAt', 'cc', 'bcc', 'attachments', 'campaign', 'isRtl'
        ))->render();

        $mpdf->WriteHTML($renderedHtml);
        $sanitize = fn(string $name): string => preg_replace('/[\s\\/:"*?<>|]+/', '_', $name);
        $storagePath = "bulk-mail-pdfs/{$sanitize($campaign->name)}/{$sanitize($recipient->name)}.pdf";
        $fullPath    = storage_path('app/public/' . $storagePath);

        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

        return $storagePath;
    }

    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }
}
