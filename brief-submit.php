<?php
/**
 * Design Rescue creative brief handler — IGRAPHI LLC
 * Handles both Path A (guided 9-step) and Path B (upload existing brief).
 * Sends structured HTML brief to admin + confirmation to client.
 * Place in the same directory as index.html on Bluehost.
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim((string)$val)), ENT_QUOTES, 'UTF-8');
}
function nl($val) {
    return nl2br(clean($val));
}
function row($label, $value) {
    if (!$value) $value = '<span style="color:#9ca3af;">—</span>';
    return '<tr>
      <td style="padding:10px 16px;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;vertical-align:top;width:190px;border-right:1px solid #e5e7eb;">' . $label . '</td>
      <td style="padding:10px 16px;font-size:14px;color:#111827;vertical-align:top;line-height:1.65;">' . $value . '</td>
    </tr>';
}
function section_head($label) {
    return '<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#0d6b56;margin:0 0 10px;">' . $label . '</p>';
}
function table_wrap($rows) {
    return '<table width="100%" style="border-collapse:collapse;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:24px;">' . $rows . '</table>';
}

// ── Common required fields ────────────────────────────────────────────────────
$form_path   = clean($_POST['form_path']   ?? 'guided'); // 'guided' | 'upload'
$name        = clean($_POST['name']        ?? '');
$email_raw   = trim($_POST['email']        ?? '');
$email       = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
$org         = clean($_POST['organization'] ?? '');
$title       = clean($_POST['projectTitle'] ?? '');

if (!$name || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name and email are required.']);
    exit;
}

// ── Path A (guided) fields ────────────────────────────────────────────────────
$proj_type   = clean($_POST['projectType']        ?? '');
$desc        = clean($_POST['projectDescription'] ?? '');
$audience    = clean($_POST['primaryAudience']    ?? '');
$channel     = clean($_POST['contextAndChannel']  ?? '');
$goals       = clean($_POST['successDefinition']  ?? '');
$key_msg     = clean($_POST['keyMessage']         ?? '');
$visual_dir  = clean($_POST['visualDirection']    ?? '');
$refs        = clean($_POST['referenceLinks']     ?? '');
$timeline    = clean($_POST['timeline']           ?? '');
$budget      = clean($_POST['budget']             ?? '');
$deliverables = clean($_POST['deliverables']      ?? '');
$dl_other    = clean($_POST['deliverablesOther']  ?? '');
$proc_path   = clean($_POST['procurementPath']    ?? '');
$notes       = clean($_POST['finalNotes'] ?? clean($_POST['additionalNotes'] ?? ''));

// ── File uploads ──────────────────────────────────────────────────────────────
$uploaded_files = [];

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
$max_bytes = 25 * 1024 * 1024;

// Collect file keys: file_1…file_N (Path A) or brief_1…brief_N (Path B)
$file_keys = array_filter(array_keys($_FILES ?? []), function($k) {
    return strpos($k, 'file_') === 0 || strpos($k, 'brief_') === 0;
});

if (!empty($file_keys)) {
    $submission_id = date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8);
    $upload_dir    = __DIR__ . '/uploads/rescue/' . $submission_id . '/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        file_put_contents(
            $upload_dir . '.htaccess',
            "Options -Indexes\nOrder deny,allow\nDeny from all\nAllow from 127.0.0.1\n"
        );
    }

    foreach ($file_keys as $key) {
        $f = $_FILES[$key];
        if ($f['error'] !== UPLOAD_ERR_OK) continue;
        if ($f['size']  >  $max_bytes)      continue;
        $mime = mime_content_type($f['tmp_name']);
        if (!in_array($mime, $allowed_mime)) continue;
        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($f['name']));
        $safe = substr($safe, 0, 180);
        if (move_uploaded_file($f['tmp_name'], $upload_dir . $safe)) {
            $kb = round($f['size'] / 1024);
            $uploaded_files[] = [
                'name' => htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'),
                'size' => $kb >= 1024 ? round($kb / 1024, 1) . ' MB' : $kb . ' KB',
                'url'  => 'https://igraphi.com/uploads/rescue/' . $submission_id . '/' . rawurlencode($safe),
            ];
        }
    }
}

// ── Build file list HTML ──────────────────────────────────────────────────────
if ($uploaded_files) {
    $files_html = '<ul style="margin:8px 0 0;padding-left:18px;">';
    foreach ($uploaded_files as $f) {
        $files_html .= '<li style="margin-bottom:5px;"><a href="' . $f['url'] . '" style="color:#2563eb;">' . $f['name'] . '</a> <span style="color:#6b7280;">(' . $f['size'] . ')</span></li>';
    }
    $files_html .= '</ul>';
} else {
    $files_html = '<p style="color:#9ca3af;margin:0;">No files uploaded</p>';
}

$date_str = date('F j, Y \a\t g:i a T');
$path_label = $form_path === 'upload' ? 'Quick Upload' : 'Guided Brief (9 steps)';

// ── Admin email ───────────────────────────────────────────────────────────────
ob_start(); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;margin:0;padding:0;background:#f3f4f6;">
<table width="100%" style="background:#f3f4f6;padding:28px 0;"><tr><td align="center">
<table width="640" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09);">

  <tr><td style="background:#1a1a1a;padding:32px 40px;">
    <p style="font-family:Georgia,serif;font-size:22px;color:#fff;margin:0 0 6px;font-weight:400;">Design Rescue &mdash; <span style="color:#06d6a0;font-style:italic;">Creative Brief</span></p>
    <p style="color:rgba(255,255,255,.4);font-size:12px;margin:0;letter-spacing:.06em;text-transform:uppercase;"><?php echo $date_str; ?> &nbsp;&middot;&nbsp; <?php echo $path_label; ?></p>
  </td></tr>

  <tr><td style="padding:32px 40px;">

    <?php echo section_head('Client'); ?>
    <?php echo table_wrap(
        row('Name', $name) .
        row('Organization', $org) .
        row('Email', '<a href="mailto:' . $email . '" style="color:#2563eb;">' . $email . '</a>')
    ); ?>

    <?php if ($form_path === 'guided'): ?>

    <?php echo section_head('Project'); ?>
    <?php echo table_wrap(
        row('Title', $title) .
        row('Type', $proj_type) .
        row('Description', nl($desc)) .
        row('Primary Audience', nl($audience)) .
        row('Context / Channel', nl($channel))
    ); ?>

    <?php echo section_head('Goals & Message'); ?>
    <?php echo table_wrap(
        row('Success looks like', nl($goals)) .
        row('Key message', nl($key_msg))
    ); ?>

    <?php echo section_head('Visual Direction'); ?>
    <?php echo table_wrap(
        row('Visual direction', $visual_dir) .
        row('Reference links', $refs ? nl2br(clean($refs)) : '')
    ); ?>

    <?php echo section_head('Scope & Timeline'); ?>
    <?php echo table_wrap(
        row('Timeline', $timeline) .
        row('Budget range', $budget) .
        row('Deliverables', $deliverables) .
        row('Other deliverables', nl($dl_other)) .
        row('Procurement path', $proc_path)
    ); ?>

    <?php if ($notes): ?>
    <?php echo section_head('Final Notes'); ?>
    <?php echo table_wrap(row('Notes', nl($notes))); ?>
    <?php endif; ?>

    <?php else: ?>

    <?php echo section_head('Project'); ?>
    <?php echo table_wrap(
        row('Title', $title) .
        row('Procurement path', $proc_path)
    ); ?>

    <?php if ($notes): ?>
    <?php echo section_head('Additional Notes'); ?>
    <?php echo table_wrap(row('Notes', nl($notes))); ?>
    <?php endif; ?>

    <?php endif; ?>

    <?php echo section_head('Uploaded Files'); ?>
    <div style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;padding:14px 18px;margin-bottom:24px;"><?php echo $files_html; ?></div>

    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:24px 28px;">
      <p style="font-size:12px;font-weight:700;color:#15803d;margin:0 0 14px;text-transform:uppercase;letter-spacing:.06em;">Next Step</p>
      <p style="font-size:14px;color:#1f2937;margin:0 0 12px;line-height:1.7;">Thank you for submitting your Design Rescue request.</p>
      <p style="font-size:14px;color:#1f2937;margin:0 0 12px;line-height:1.7;">I will personally review your brief, uploaded files, deadline, and project needs before confirming the final scope. After reviewing the materials, I will follow up with:</p>
      <ul style="margin:0 0 14px;padding-left:20px;font-size:14px;color:#1f2937;line-height:1.9;">
        <li>A recommended timeline</li>
        <li>Any questions needed to clarify the project</li>
        <li>Confirmation that the project fits within the Design Rescue scope</li>
        <li>A secure payment link for the $750 fixed project fee</li>
      </ul>
      <p style="font-size:14px;color:#1f2937;margin:0 0 12px;line-height:1.7;">Design Rescue work begins once the scope is confirmed and payment is received.</p>
      <p style="font-size:14px;color:#1f2937;margin:0 0 20px;line-height:1.7;">If the request is larger than one focused Design Rescue project, I will let you know before any work begins and recommend the best next step.</p>
      <p style="font-size:14px;color:#1f2937;margin:0 0 20px;line-height:1.7;">Thank you for trusting IGRAPHI with your project. I&rsquo;ll be in touch soon.</p>
      <a href="mailto:<?php echo $email; ?>" style="display:inline-block;background:#06d6a0;color:#fff;padding:9px 18px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">Reply to <?php echo $name; ?></a>
    </div>

  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 40px;border-top:1px solid #e5e7eb;">
    <p style="font-size:12px;color:#9ca3af;margin:0;">Submitted via igraphi.com Design Rescue brief builder &nbsp;&middot;&nbsp; <?php echo $date_str; ?></p>
  </td></tr>

</table>
</td></tr></table>
</body></html>
<?php
$admin_body = ob_get_clean();

// ── Client confirmation email ─────────────────────────────────────────────────
ob_start(); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;margin:0;padding:0;background:#f3f4f6;">
<table width="100%" style="background:#f3f4f6;padding:28px 0;"><tr><td align="center">
<table width="580" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09);">

  <tr><td style="background:#1a1a1a;padding:36px 40px;">
    <p style="font-family:Georgia,serif;font-size:22px;color:#fff;margin:0;font-weight:400;">Your Design Rescue<br><span style="color:#06d6a0;font-style:italic;">request is in.</span></p>
  </td></tr>

  <tr><td style="padding:36px 40px;">
    <p style="font-size:16px;color:#1f2937;line-height:1.7;margin:0 0 18px;">Hi <?php echo $name; ?>,</p>
    <p style="font-size:15px;color:#4b5563;line-height:1.85;margin:0 0 20px;">Thank you for submitting your Design Rescue request.</p>

    <div style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;padding:18px 22px;margin:0 0 24px;">
      <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#06d6a0;margin:0 0 12px;">Your submission</p>
      <table style="border-collapse:collapse;width:100%;">
        <?php if ($title): ?>
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;width:120px;">Project</td><td style="padding:5px 0;font-size:13px;color:#111827;font-weight:500;"><?php echo $title; ?></td></tr>
        <?php endif; ?>
        <?php if ($proj_type): ?>
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;">Type</td><td style="padding:5px 0;font-size:13px;color:#111827;"><?php echo $proj_type; ?></td></tr>
        <?php endif; ?>
        <tr><td style="padding:5px 0;font-size:13px;color:#6b7280;">Files sent</td><td style="padding:5px 0;font-size:13px;color:#111827;"><?php echo count($uploaded_files); ?> file(s)</td></tr>
      </table>
    </div>

    <div style="border-top:1px solid #e5e7eb;padding-top:24px;margin-bottom:24px;">
      <p style="font-size:12px;font-weight:700;color:#06d6a0;margin:0 0 14px;text-transform:uppercase;letter-spacing:.08em;">Next Step</p>
      <p style="font-size:14px;color:#374151;line-height:1.8;margin:0 0 12px;">I will personally review your brief, uploaded files, deadline, and project needs before confirming the final scope. After reviewing the materials, I will follow up with:</p>
      <ul style="margin:0 0 14px;padding-left:20px;font-size:14px;color:#374151;line-height:1.9;">
        <li>A recommended timeline</li>
        <li>Any questions needed to clarify the project</li>
        <li>Confirmation that the project fits within the Design Rescue scope</li>
        <li>A secure payment link for the $750 fixed project fee</li>
      </ul>
      <p style="font-size:14px;color:#374151;line-height:1.8;margin:0 0 12px;">Design Rescue work begins once the scope is confirmed and payment is received.</p>
      <p style="font-size:14px;color:#374151;line-height:1.8;margin:0 0 12px;">If the request is larger than one focused Design Rescue project, I will let you know before any work begins and recommend the best next step.</p>
      <p style="font-size:14px;color:#374151;line-height:1.8;margin:0;">Thank you for trusting IGRAPHI with your project. I&rsquo;ll be in touch soon.</p>
    </div>

    <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 18px;">Typical response time is under 12 hours. I&#8217;ll reach out to <strong style="color:#1f2937;"><?php echo $email; ?></strong>.</p>
    <p style="font-size:14px;color:#6b7280;margin:0;">&#8212; Vladimir Herrera<br><span style="color:#9ca3af;">IGRAPHI LLC</span></p>
  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 40px;border-top:1px solid #e5e7eb;">
    <p style="font-size:12px;color:#9ca3af;margin:0;">IGRAPHI LLC &nbsp;&#183;&nbsp; Washington, DC &nbsp;&#183;&nbsp; <a href="https://igraphi.com" style="color:#9ca3af;">igraphi.com</a></p>
  </td></tr>

</table>
</td></tr></table>
</body></html>
<?php
$client_body = ob_get_clean();

// ── Send emails ───────────────────────────────────────────────────────────────
$encode = function($str) { return '=?UTF-8?B?' . base64_encode($str) . '?='; };

$base_headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
]);

$admin_headers = $base_headers . "\r\n" .
    'From: IGRAPHI Design Rescue <noreply@igraphi.com>' . "\r\n" .
    'Reply-To: ' . $name . ' <' . $email . '>';

$admin_sent = mail(
    'igraphi@gmail.com',
    $encode('New Design Rescue Request: ' . ($title ?: 'Untitled Project')),
    $admin_body,
    $admin_headers
);

$client_headers = $base_headers . "\r\n" .
    'From: Vladimir at IGRAPHI <vherrera@igraphi.com>' . "\r\n" .
    'Reply-To: vherrera@igraphi.com';

mail(
    $email,
    $encode('We received your Design Rescue request'),
    $client_body,
    $client_headers
);

// ── Response ──────────────────────────────────────────────────────────────────
if ($admin_sent) {
    echo json_encode(['success' => true, 'files' => count($uploaded_files)]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Your request could not be delivered. Please email vherrera@igraphi.com directly.',
    ]);
}
