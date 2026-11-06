<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Certified_copy_pdf_renderer
{
    public function renderToFile(array $request, $fee, $temporaryPdf)
    {
        $escape = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $issuedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $sealCandidates = [
            ROOT_DIR . 'public/assets/b131edbc-193e-4b3e-99e9-aa8f1855c9ea.jpg',
            ROOT_DIR . 'public/assets/naujan_seal_premium_bg.jpg',
            ROOT_DIR . 'public/assets/naujan_seal_3d_bg_web.webp',
        ];
        $sealPath = null;
        foreach ($sealCandidates as $candidate) {
            if (is_file($candidate)) {
                $sealPath = $candidate;
                break;
            }
        }
        $sealMime = $sealPath ? $this->imageMimeType($sealPath) : '';
        $seal = $sealPath ? 'data:' . $sealMime . ';base64,' . base64_encode(file_get_contents($sealPath)) : '';
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 13mm 15mm 16mm; } body { font-family: DejaVu Sans, sans-serif; color: #25231e; font-size: 9.5px; line-height: 1.28; }
            .head { border-top:5px solid #c79a1b; border-bottom:2px solid #263b31; padding:8px 0 7px; text-align:center; }
            .gov-line { font-size:7px; letter-spacing:1px; font-weight:bold; color:#263b31; text-transform:uppercase; }
            .seal { width:62px; height:62px; float:left; margin-left:20px; object-fit:contain; } h1 { margin:4px 0 1px; font-size:16px; }
            h2 { margin:0; font-size:14px; letter-spacing:.4px; text-transform:uppercase; } .office { font-size:10px; }
            .meta { margin-top:10px; width:100%; border-collapse:collapse; }
            .meta td { border-bottom:1px solid #ddd6c8; padding:4px 3px; vertical-align:top; } .label { width:31%; color:#5f5747; font-weight:bold; }
            .cert { margin-top:10px; border:2px solid #263b31; padding:9px 12px; } .cert h3 { margin:0 0 4px; text-align:center; font-size:12px; text-transform:uppercase; letter-spacing:.7px; }
            .guide { margin-top:10px; border-top:1px solid #aaa; padding-top:7px; } .guide h3 { margin:0 0 5px; color:#263b31; font-size:11px; text-transform:uppercase; letter-spacing:.6px; }
            .system-process { page-break-before:always; break-before:page; } .system-process h3 { page-break-after:avoid; }
            .guide ol { margin:0; padding-left:18px; } .guide li { margin-bottom:3px; } strong.gold { color:#80620f; } .claim { margin-top:5px; padding:6px 8px; background:#f8f4e8; border-left:3px solid #c79a1b; }
            .sig { margin-top:12px; width:220px; border-top:1px solid #333; text-align:center; padding-top:4px; }
            .footer { position:fixed; bottom:-12mm; left:0; right:0; border-top:1px solid #263b31; padding-top:4px; text-align:center; font-size:7px; color:#666; }
        </style></head><body><div class="head">'
            . ($seal ? '<img class="seal" src="' . $escape($seal) . '" alt="Municipal seal of Naujan">' : '')
            . '<div class="gov-line">Republic of the Philippines</div><h1>Municipality of Naujan</h1><div class="office">Office of the Municipal Secretary</div></div>
            <table class="meta"><tr><td class="label">Request number</td><td>' . $escape($request['request_number']) . '</td></tr>
            <tr><td class="label">Requester</td><td>' . $escape($request['full_name']) . '</td></tr>
            <tr><td class="label">Document requested</td><td>' . $escape($request['document_type']) . '</td></tr>
            <tr><td class="label">Purpose</td><td>' . $escape($request['purpose'] ?? '') . '</td></tr>
            <tr><td class="label">Contact</td><td>' . $escape($request['contact_email'] ?? '') . ' · ' . $escape($request['phone'] ?? '') . '</td></tr>
            <tr><td class="label">Office fee</td><td>' . $escape($fee) . '</td></tr></table>
            <section class="cert"><h3>Certified Copy</h3><p>This is to certify that the request described above has been reviewed and approved by the Municipal Office. This certified copy is issued subject to verification and applicable office requirements.</p>
            <p><strong>Status:</strong> Approved / Copy Certified<br><strong>Date issued:</strong> ' . $escape($issuedAt->format('F j, Y, g:i A')) . ' Philippine Standard Time</p></section>
            <section class="guide"><h3>DocuQuest Request Guide</h3><ol>
            <li>Create an account using <strong class="gold">Create Account</strong> or sign in through Google.</li>
            <li>Review and accept the Data Privacy Act notice for security and protection of your personal information.</li>
            <li>Create a request, then enter the complete <strong>requester information</strong>, requested document, and purpose.</li>
            <li>Use the <strong>Message</strong> button for further concerns and wait for the official response of the Admin.</li>
            <li>After sending your request, refresh and check the requester page for progress notifications.</li>
            <li>Your receipt and the certified copy of the approved document may be sent to your registered email.</li>
            <li>If the Admin sets a balance, pay the required fee through DocuQuest. Prices may vary because of office expenses and other approved reasons; some documents may be issued free when applicable.</li>
            </ol><div class="claim"><strong>Certified-copy payment notice:</strong> The requester cannot receive, view, or download the certified copy until the required municipal payment is confirmed paid. The status may proceed only after payment verification.</div>
            <div class="claim"><strong>Claiming your document:</strong> Once the request is marked <strong>Completed</strong>, proceed to the Municipal Registry and present the completed progress notification. For personal claiming, bring a valid ID. Please follow office requirements and visit only during the indicated schedule.</div></section>
            <section class="guide"><h3>Gabay sa Tagalog at Patakaran sa Serbisyo</h3>
            <ol><li><strong>Isumite ang request</strong> at hintayin ang paunang review ng munisipyo. Makakatanggap ka ng email at in-app notification sa mga update kahit wala pang itinakdang bayad.</li><li><strong>Kapag Processing na</strong>, saka itatakda ng admin ang naaangkop na fee. Makakatanggap ka ng payment notice at maaari kang magbayad nang ligtas sa pamamagitan ng PayMongo.</li><li><strong>I-upload ang bawat requirement nang paisa-isa</strong> sa Request Progress. Susuriin ito ng admin at makakatanggap ka ng notification kapag validated o rejected.</li><li>Kung rejected ang file, <strong>mag-upload ng kapalit na file</strong> para sa requirement na iyon at hintayin ang panibagong review.</li><li><strong>Kapag bayad na at validated na ang lahat ng requirements</strong>, maaaring aprubahan at i-schedule ng opisina ang release.</li><li>Ang certified copy ay isinasama sa email kapag <strong>Approved, Ready for release,</strong> at <strong>Completed</strong> na ang request. Sa Completed notice, sundin ang iskedyul at dalhin ang valid ID kung personal na kukunin.</li></ol>
            <div><strong>Data Privacy Act of 2012 (R.A. 10173):</strong> Ginagamit lamang ang personal na impormasyon para sa pagproseso, beripikasyon, komunikasyon, pagbabayad, at opisyal na talaan ng munisipyo. Maaaring humingi ng correction o access sa iyong datos ayon sa naaangkop na retention rules.</div>
            <div style="margin-top:4px"><strong>Ease of Doing Business Act (R.A. 11032):</strong> Ang munisipal na opisina ay sumusunod sa standardong hakbang, turnaround, transparent na bayarin, at official feedback process, alinsunod sa requirements ng dokumento.</div></section>
            <section class="guide system-process"><h3>System Process and Naujan Registry Office Coordination</h3>
            <p>DocuQuest is a digital request and coordination channel that supports the Municipality of Naujan and its Registry Office. It helps organize submissions, office review, status updates, payment confirmation, and delivery or claiming instructions.</p>
            <ol>
            <li><strong>Submit and initial review:</strong> The requester submits the document type, purpose, and contact details. Submitted and Under review updates can be emailed and shown in-app before a payment amount is set.</li>
            <li><strong>Processing and payment:</strong> The admin sets any applicable fee once the request reaches Processing. The requester receives an email and in-app notification, then pays securely through PayMongo. Fee waivers remain subject to office rules.</li>
            <li><strong>Requirement review:</strong> The requester uploads one file for each listed requirement. Each upload remains pending until an admin marks it Validated or Rejected. A rejected file must be replaced and reviewed again.</li>
            <li><strong>Approval and release:</strong> Payment must be confirmed and all listed requirements validated before approval or release. The office may set a future release appointment.</li>
            <li><strong>Receive or claim:</strong> The certified copy is attached to Approved, Ready for release, and Completed status emails and is available from the requester account. Follow the completion notice and Registry Office instructions when claiming in person.</li>
            </ol>
            <div class="claim"><strong>Role of the Registry Office:</strong> DocuQuest supports request handling and communication; it does not replace official record checks or the authority of municipal personnel. The Naujan Registry Office and authorized municipal personnel determine record validity, applicable requirements and fees, and whether a document may be issued. An online request or status notice alone is not a guarantee of issuance.</div>
            <p><strong>Sa madaling sabi:</strong> Tumutulong ang DocuQuest sa pagsusumite ng request, pakikipag-ugnayan, pagsubaybay ng status, at pagtanggap ng approved na kopya. Ang Naujan Registry Office pa rin ang nagsusuri ng opisyal na tala at nagtatakda ng requirements, bayarin, at pahintulot sa paglalabas ng dokumento.</p></section>
            <div style="text-align:center;margin-top:10px">' . ($seal ? '<img src="' . $escape($seal) . '" style="width:42px;height:42px" alt="Naujan seal">' : '') . '<br><strong>ORIGINAL COPY ISSUED BY THE MUNICIPAL OFFICE</strong></div>
            <div class="sig">Authorized Municipal Officer</div>
            <div class="footer">Municipality of Naujan · Office of the Municipal Secretary · DocuQuest Request Reference: ' . $escape($request['request_number']) . '<br>Issued digitally. Verify request details through the official DocuQuest account.</div>
        </body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($temporaryPdf, $dompdf->output());
        return filesize($temporaryPdf);
    }

    private function imageMimeType($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $extension === 'webp' ? 'image/webp' : ($extension === 'png' ? 'image/png' : 'image/jpeg');
    }
}
