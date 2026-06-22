<?php
/**
 * Design Rescue intake form handler — IGRAPHI LLC
 * Receives form + files, sends HTML brief to admin, sends confirmation to client.
 * Place in the same directory as index.html on Bluehost.
 */

header('Content-Type: application/json; charset=UTF-8');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim((string)$val)), ENT_QUOTES, 'UTF-8');
}
function nl($val) {
    return nl2br(clean($val));
}

// ── Read & validate inputs ────────────────────────────────────────────────
$name          = clean($_POST['name']          ?? '');
$email_raw     = trim($_POST['email']         ?? '');
$email         = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
$organization  = clean($_POST['organization'] ?? '');
$phone         = clean($_POST['phone']        ?? '');
$website       = clean($_POST['website']      ?? '');
$project_title = clean($_POST['project_title']?? '');
$project_type  = clean($_POST['project_type'] ?? '');
$description   = clean($_POST['description']  ?? '');
$goal          = clean($_POST['goal']         ?? '');
$audience      = clean($_POST['audience']     ?? '');
$status        = clean($_POST['status']       ?? '');
$deadline      = clean($_POST['deadline']     ?? '');
$brand         = clean($_POST['brand']        ?? '');
$special_notes = clean($_POST['special_notes']?? '');

$needs = [];
if (!empty($_POST['needs']) && is_array($_POST['needs'])) {
    $needs = array_map('clean', $_POST['needs']);
}
$needs_str = !empty($needs) ? implode(', ', $needs) : 'Not specified';

// Required fields
if (!$name || !$email || !$project_title || !$description || !$deadline) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
    exit;
}

// ── File uploads ──────────────────────────────────────────────────────────
$uploaded_files = [];

if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
    $submission_id = date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8);
    $upload_dir    = __DIR__ . '/uploads/rescue/' . $submission_id . '/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        file_put_contents($upload_dir . '.htaccess', "Options -Indexes\nOrder deny,allow\nDeny from all\nAllow from 127.0.0.1\n");
    }

    $allowed_mime = [
        'application/zip', 'application/x-zip-compressed', 'application/x-zip',
        'image/jpeg', 'image/png', 'image/svg+xml', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];
    $max_bytes = 25 * 1024 * 1024; // 25 MB

    $count = count($_FILES['files']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['files']['size'][$i]  > $max_bytes)      continue;

        $tmp      = $_FILES['files']['tmp_name'][$i];
        $orig     = $_FILES['files']['name'][$i];
        $mime     = mime_content_type($tmp);

        if (!in_array($mime, $allowed_mime)) continue;

        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($orig));
        $safe = substr($safe, 0, 180);

        if (move_uploaded_file($tmp, $upload_dir . $safe)) {
            $kb = round($_FILES['files']['size'][$i] / 1024);
            $uploaded_files[] = [
                'name' => htmlspecialchars($orig, ENT_QUOTES, 'UTF-8'),
                'size' => $kb >= 1024 ? round($kb / 1024, 1) . ' MB' : $kb . ' KB',
                'url'  => 'https://igraphi.com/uploads/rescue/' . $submission_id . '/' . rawurlencode($safe),
            ];
        }
    }
}

// ── Build file list HTML ──────────────────────────────────────────────────
if ($uploaded_files) {
    $files_html = '<ul style="margin:8px 0 0;padding-left:18px;">';
    foreach ($uploaded_files as $f) {
        $files_html .= '<li style="margin-bottom:4px;"><a href="' . $f['url'] . '" style="color:#2563eb;">' . $f['name'] . '</a> <span style="color:#6b7280;">(' . $f['size'] . ')</span></li>';
    }
    $files_html .= '</ul>';
} else {
    $files_html = '<p style="color:#9ca3af;margin:0;">No files uploaded</p>';
}

// ── Email helpers ─────────────────────────────────────────────────────────
function row($label, $value) {
    if (!$value) $value = '<span style="color:#9ca3af;">—</span>';
    return '<tr>
      <td style="padding:10px 16px;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;vertical-align:top;width:170px;border-right:1px solid #e5e7eb;">' . $label . '</td>
      <td style="padding:10px 16px;font-size:14px;color:#111827;vertical-align:top;line-height:1.6;">' . $value . '</td>
    </tr>';
}
function section_head($label) {
    return '<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#ED1C24;margin:0 0 10px;">' . $label . '</p>';
}
function table_wrap($rows) {
    return '<table width="100%" style="border-collapse:collapse;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:24px;">' . $rows . '</table>';
}

$date_str = date('F j, Y \a\t g:i a T');

// ── Admin HTML email ──────────────────────────────────────────────────────
$admin_body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Inter,Arial,sans-serif;margin:0;padding:0;background:#f3f4f6;">
<table width="100%" style="background:#f3f4f6;padding:28px 0;"><tr><td align="center">
<table width="640" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09);">

  <tr><td style="background:#111827;padding:32px 40px;">
    <p style="font-family:Georgia,serif;font-size:22px;color:#fff;margin:0 0 6px;font-weight:400;">Design Rescue — <span style="color:#ED1C24;font-style:italic;">Creative Brief</span></p>
    <p style="color:rgba(255,255,255,.4);font-size:12px;margin:0;letter-spacing:.06em;text-transform:uppercase;">' . $date_str . '</p>
  </td></tr>

  <tr><td style="padding:32px 40px;">

    ' . section_head('01 — Client Information') . '
    ' . table_wrap(
        row('Name', $name) .
        row('Organization', $organization) .
        row('Email', '<a href="mailto:' . $email . '" style="color:#2563eb;">' . $email . '</a>') .
        row('Phone', $phone) .
        row('Website', $website ? '<a href="' . $website . '" style="color:#2563eb;">' . $website . '</a>' : '')
    ) . '

    ' . section_head('02 — Project Overview') . '
    ' . table_wrap(
        row('Project Title', $project_title) .
        row('Type', $project_type) .
        row('Description', nl($description)) .
        row('Main Goal', nl($goal)) .
        row('Audience', nl($audience))
    ) . '

    ' . section_head('03 — Design Needs') . '
    ' . table_wrap(row('Improvements needed', $needs_str)) . '

    ' . section_head('04 — Status &amp; Timeline') . '
    ' . table_wrap(
        row('Current status', $status) .
        row('Deadline', $deadline)
    ) . '

    ' . section_head('05 — Brand Direction') . '
    ' . table_wrap(
        row('Brand / visual notes', nl($brand)) .
        row('Special instructions', nl($special_notes))
    ) . '

    ' . section_head('06 — Uploaded Files') . '
    <div style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;padding:14px 18px;margin-bottom:24px;">' . $files_html . '</div>

    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:18px 22px;">
      <p style="font-size:12px;font-weight:700;color:#991b1b;margin:0 0 8px;text-transform:uppercase;letter-spacing:.06em;">Next Step</p>
      <p style="font-size:14px;color:#1f2937;margin:0 0 12px;line-height:1.6;">Review the brief → confirm scope → send $750 payment link → confirm timeline once paid.</p>
      <a href="mailto:' . $email . '" style="display:inline-block;background:#ED1C24;color:#fff;padding:9px 18px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">Reply to ' . $name . '</a>
    </div>

  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 40px;border-top:1px solid #e5e7eb;">
    <p style="font-size:12px;color:#9ca3af;margin:0;">Submitted via igraphi.com Design Rescue intake &nbsp;·&nbsp; ' . $date_str . '</p>
  </td></tr>

</table>
</td></tr></table>
</body></html>';

// ── Client confirmation email ─────────────────────────────────────────────
$client_body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Inter,Arial,sans-serif;margin:0;padding:0;background:#f3f4f6;">
<table width="100%" style="background:#f3f4f6;padding:28px 0;"><tr><td align="center">
<table width="580" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09);">

  <tr><td style="background:#111827;padding:36px 40px;">
    <p style="font-family:Georgia,serif;font-size:22px;color:#fff;margin:0;font-weight:400;">Your Design Rescue<br><span style="color:#ED1C24;font-style:italic;">request is in.</span></p>
  </td></tr>

  <tr><td style="padding:36px 40px;">
    <p style="font-size:16px;color:#1f2937;line-height:1.7;margin:0 0 18px;">Hi ' . $name . ',</p>
    <p style="font-size:15px;color:#4b5563;line-height:1.8;margin:0 0 20px;">Thank you &#8212; your Design Rescue request has been received. I will review your brief and uploaded materials, then follow up with a project timeline and a secure payment link for the $750 Design Rescue starting fee. Work begins once the scope is confirmed and payment is received.</p>

    <div style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;padding:18px 22px;margin:22px 0;">
      <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#ED1C24;margin:0 0 12px;">Your submission summary</p>
      <table style="border-collapse:collapse;width:100%;">
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;width:130px;">Project</td><td style="padding:5px 0;font-size:13px;color:#111827;font-weight:500;">' . $project_title . '</td></tr>
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;">Type</td><td style="padding:5px 0;font-size:13px;color:#111827;">' . ($project_type ?: '—') . '</td></tr>
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;">Deadline</td><td style="padding:5px 0;font-size:13px;color:#111827;">' . $deadline . '</td></tr>
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;">Files sent</td><td style="padding:5px 0;font-size:13px;color:#111827;">' . count($uploaded_files) . ' file(s)</td></tr>
      </table>
    </div>

    <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 18px;">Typical response time is under 12 hours. I&#8217;ll reach out to <strong style="color:#1f2937;">' . $email . '</strong>.</p>
    <p style="font-size:14px;color:#6b7280;margin:0;">&#8212; Vladimir Herrera<br><span style="color:#9ca3af;">IGRAPHI LLC</span></p>
  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 40px;border-top:1px solid #e5e7eb;">
    <p style="font-size:12px;color:#9ca3af;margin:0;">IGRAPHI LLC &nbsp;&#183;&nbsp; Washington, DC &nbsp;&#183;&nbsp; <a href="https://igraphi.com" style="color:#9ca3af;">igraphi.com</a></p>
  </td></tr>

</table>
</td></tr></table>
</body></html>';

// ── Send emails ───────────────────────────────────────────────────────────
$encode = function($str) { return '=?UTF-8?B?' . base64_encode($str) . '?='; };

$base_headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
]);

// To admin
$admin_headers = $base_headers . "\r\n" .
    'From: IGRAPHI Design Rescue <noreply@igraphi.com>' . "\r\n" .
    'Reply-To: ' . $name . ' <' . $email . '>';

$admin_sent = mail(
    'igraphi@gmail.com',
    $encode('New Design Rescue Request: ' . $project_title),
    $admin_body,
    $admin_headers
);

// To client
$client_headers = $base_headers . "\r\n" .
    'From: Vladimir at IGRAPHI <vherrera@igraphi.com>' . "\r\n" .
    'Reply-To: vherrera@igraphi.com';

mail(
    $email,
    $encode('We received your Design Rescue request'),
    $client_body,
    $client_headers
);

// ── Response ──────────────────────────────────────────────────────────────
if ($admin_sent) {
    echo json_encode([
        'success' => true,
        'files'   => count($uploaded_files),
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Your request could not be delivered. Please email vherrera@igraphi.com directly.',
    ]);
}
