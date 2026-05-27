<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$title = 'Bulk WhatsApp Invites';
ensure_course_detail_columns();
ensure_whatsapp_settings_table();

function bulk_invite_phone_from_row(array $row): string
{
    foreach (['phone', 'mobile', 'whatsapp', 'number', 'contact'] as $key) {
        if (!empty($row[$key])) {
            return trim((string) $row[$key]);
        }
    }

    return trim((string) ($row[1] ?? $row[0] ?? ''));
}

function bulk_invite_name_from_row(array $row): string
{
    foreach (['name', 'full name', 'student name', 'contact name'] as $key) {
        if (!empty($row[$key])) {
            return trim((string) $row[$key]);
        }
    }

    return trim((string) ($row[0] ?? ''));
}

function bulk_invite_assoc_rows(array $rows): array
{
    if (!$rows) {
        return [];
    }

    $firstRow = array_map(static fn ($value): string => strtolower(trim((string) $value)), $rows[0]);
    $hasHeader = count(array_intersect($firstRow, ['name', 'full name', 'student name', 'contact name', 'phone', 'mobile', 'whatsapp', 'number', 'contact'])) > 0;
    $headers = $hasHeader ? $firstRow : [];
    $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;
    $contacts = [];

    foreach ($dataRows as $row) {
        $assoc = [];
        foreach ($row as $index => $value) {
            $key = $headers[$index] ?? $index;
            $assoc[$key] = trim((string) $value);
        }

        $phone = bulk_invite_phone_from_row($assoc);
        if ($phone === '') {
            continue;
        }

        $contacts[] = [
            'name' => bulk_invite_name_from_row($assoc),
            'phone' => $phone,
        ];
    }

    return $contacts;
}

function bulk_invite_parse_text(string $text): array
{
    $rows = [];
    foreach (preg_split('/\R+/', trim($text)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $delimiter = str_contains($line, "\t") ? "\t" : ',';
        $rows[] = array_map('trim', str_getcsv($line, $delimiter));
    }

    return bulk_invite_assoc_rows($rows);
}

function bulk_invite_parse_csv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return bulk_invite_assoc_rows($rows);
}

function bulk_invite_xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $value = (string) ($cell->v ?? '');
    $type = (string) ($cell['t'] ?? '');

    if ($type === 's') {
        return $sharedStrings[(int) $value] ?? '';
    }

    if ($type === 'inlineStr') {
        return trim((string) ($cell->is->t ?? ''));
    }

    return trim($value);
}

function bulk_invite_parse_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('XLSX import needs the PHP Zip extension. Export the Excel sheet as CSV if ZipArchive is not enabled.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not open the Excel file.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        if ($shared) {
            foreach ($shared->si as $item) {
                $parts = [];
                if (isset($item->t)) {
                    $parts[] = (string) $item->t;
                }
                foreach ($item->r as $run) {
                    $parts[] = (string) ($run->t ?? '');
                }
                $sharedStrings[] = trim(implode('', $parts));
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Could not find the first worksheet in the Excel file.');
    }

    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet) {
        return [];
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $sheetRow) {
        $row = [];
        foreach ($sheetRow->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
            $index = 0;
            for ($i = 0, $length = strlen((string) $letters); $i < $length; $i++) {
                $index = ($index * 26) + (ord($letters[$i]) - 64);
            }
            $row[max(0, $index - 1)] = bulk_invite_xlsx_cell_value($cell, $sharedStrings);
        }
        if ($row) {
            ksort($row);
            $rows[] = array_values($row);
        }
    }

    return bulk_invite_assoc_rows($rows);
}

function bulk_invite_uploaded_contacts(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Contact file upload failed.');
    }

    $path = (string) ($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    return match ($extension) {
        'csv', 'txt' => bulk_invite_parse_csv($path),
        'xlsx' => bulk_invite_parse_xlsx($path),
        default => throw new RuntimeException('Please upload a CSV, TXT, or XLSX contact file.'),
    };
}

function bulk_invite_unique_contacts(array $contacts): array
{
    $unique = [];
    $seen = [];

    foreach ($contacts as $contact) {
        $normalized = normalize_whatsapp_number((string) $contact['phone']);
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }

        $seen[$normalized] = true;
        $unique[] = [
            'name' => trim((string) $contact['name']),
            'phone' => $normalized,
        ];
    }

    return $unique;
}

$settings = whatsapp_settings();
$courses = active_courses(100);
$results = [];
$previewContacts = [];
$selectedCourseId = (int) ($_POST['course_id'] ?? ($courses[0]['id'] ?? 0));
$pastedContacts = (string) ($_POST['contacts'] ?? '');
$inviteDescription = trim((string) ($_POST['invite_description'] ?? ''));
$inviteDuration = trim((string) ($_POST['invite_duration'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $courseStmt = db()->prepare('SELECT * FROM courses WHERE id = ?');
        $courseStmt->execute([$selectedCourseId]);
        $course = $courseStmt->fetch();

        if (!$course) {
            throw new RuntimeException('Please choose a valid program.');
        }

        if ($inviteDescription === '') {
            $inviteDescription = trim((string) ($course['short_description'] ?? ''));
        }

        if ($inviteDuration === '') {
            $inviteDuration = trim((string) ($course['duration'] ?? ''));
        }

        $contacts = array_merge(
            bulk_invite_parse_text($pastedContacts),
            bulk_invite_uploaded_contacts($_FILES['contacts_file'] ?? [])
        );
        $contacts = bulk_invite_unique_contacts($contacts);

        if (!$contacts) {
            throw new RuntimeException('Add contacts by pasting phone numbers or uploading a CSV/XLSX file.');
        }

        if (($_POST['action'] ?? '') === 'preview') {
            $previewContacts = array_slice($contacts, 0, 200);
            flash('success', count($contacts) . ' unique contacts are ready for this invite.');
        } else {
            foreach ($contacts as $contact) {
                $ok = send_course_invite_whatsapp($contact['phone'], $contact['name'], $course, $inviteDescription, $inviteDuration);
                $results[] = [
                    'name' => $contact['name'],
                    'phone' => $contact['phone'],
                    'status' => $ok ? 'Sent' : 'Failed',
                    'message' => $ok ? 'Delivered to Meta API' : ($_SESSION['whatsapp_send_error'] ?? 'Unable to send'),
                ];
            }

            $sent = count(array_filter($results, static fn (array $row): bool => $row['status'] === 'Sent'));
            flash($sent > 0 ? 'success' : 'error', $sent . ' of ' . count($results) . ' WhatsApp invites sent.');
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
    }
}

require __DIR__ . '/_admin_header.php';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Bulk WhatsApp Course Invites</h1>
    <p>Import contacts and send an approved Meta WhatsApp template for a new program invite.</p>
</section>

<section class="admin-grid">
    <form method="post" enctype="multipart/form-data" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h2>Invite Contacts</h2>
        <fieldset>
            <legend>Program</legend>
            <label>New course to invite
                <select name="course_id" id="bulk-course-id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Message description
                <textarea name="invite_description" id="invite-description" rows="4" placeholder="Example: Mastering Data Analytics and Business Cases with practical dashboards, KPI thinking, and real-world problem solving."><?= e($inviteDescription) ?></textarea>
            </label>
            <label>Duration text
                <input name="invite_duration" id="invite-duration" value="<?= e($inviteDuration) ?>" placeholder="Example: 6 weeks">
            </label>
        </fieldset>

        <fieldset>
            <legend>Contacts</legend>
            <label>Paste contacts
                <textarea name="contacts" rows="8" placeholder="name,phone&#10;Ravi,919876543210&#10;Sneha,919900112233"><?= e($pastedContacts) ?></textarea>
            </label>
            <label>Import from Excel / CSV
                <input type="file" name="contacts_file" accept=".csv,.txt,.xlsx">
            </label>
            <small>Use columns named name and phone/mobile/whatsapp. XLSX import needs PHP ZipArchive; CSV works everywhere.</small>
        </fieldset>

        <div class="materials-form-actions">
            <button class="button secondary" type="submit" name="action" value="preview">Preview Contacts</button>
            <button class="button primary" type="submit" name="action" value="send" data-confirm="Send WhatsApp invite to all imported contacts?">Send Bulk Invites</button>
        </div>
    </form>

    <aside class="detail-aside">
        <h2>Template Status</h2>
        <div class="material-item">
            <strong>Course invite template</strong>
            <p><?= e($settings['course_invite_template_name'] ?: 'Not configured') ?></p>
        </div>
        <div class="material-item">
            <strong>Template variables</strong>
            <p>Use 5 body parameters in Meta: contact name, program title, description, duration, program URL.</p>
        </div>
        <div class="material-item">
            <strong>Consent</strong>
            <p>Send only to contacts who gave permission to receive WhatsApp updates from your academy.</p>
        </div>
        <div class="material-item">
            <strong>Setup</strong>
            <p><a href="whatsapp.php">Open WhatsApp settings</a> to save the approved invite template name.</p>
        </div>
    </aside>
</section>

<?php if ($previewContacts): ?>
    <section class="section">
        <div class="table-wrap">
            <table>
                <thead><tr><th>S.No</th><th>Name</th><th>WhatsApp</th></tr></thead>
                <tbody>
                    <?php foreach ($previewContacts as $index => $contact): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($contact['name'] ?: '-') ?></td>
                            <td><?= e($contact['phone']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($results): ?>
    <section class="section">
        <div class="table-wrap">
            <table>
                <thead><tr><th>S.No</th><th>Name</th><th>WhatsApp</th><th>Status</th><th>Message</th></tr></thead>
                <tbody>
                    <?php foreach ($results as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($row['name'] ?: '-') ?></td>
                            <td><?= e($row['phone']) ?></td>
                            <td><?= e($row['status']) ?></td>
                            <td><?= e($row['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<script>
(() => {
    const select = document.getElementById('bulk-course-id');
    const description = document.getElementById('invite-description');
    const duration = document.getElementById('invite-duration');
    const courses = <?= json_encode(array_map(static fn (array $course): array => [
        'id' => (int) $course['id'],
        'description' => (string) ($course['short_description'] ?? ''),
        'duration' => (string) ($course['duration'] ?? ''),
    ], $courses), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    if (!select || !description || !duration) {
        return;
    }

    const syncCourseDefaults = () => {
        const course = courses.find((item) => String(item.id) === select.value);
        if (!course) {
            return;
        }

        description.value = course.description;
        duration.value = course.duration;
    };

    select.addEventListener('change', syncCourseDefaults);

    if (description.value.trim() === '' && duration.value.trim() === '') {
        syncCourseDefaults();
    }
})();
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>
