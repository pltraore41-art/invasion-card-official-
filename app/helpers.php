<?php
declare(strict_types=1);

const PRESENCE_CHOICES = [
    'present' => 'Oui, je confirme ma présence',
    'absent' => 'Non, je ne pourrai pas venir',
    'unsure' => 'Je ne suis pas encore sûr(e)',
];

const BEVERAGE_CHOICES = [
    'alcohol' => 'Boissons alcoolisées',
    'sweet' => 'Boissons sucrées : jus, sodas, etc.',
    'both' => 'Les deux',
    'none' => 'Aucune de ces boissons',
];

const KNOWLEDGE_CHOICES = [
    'known' => 'Oui, je le connais déjà',
    'heard' => 'J’en ai déjà entendu parler',
    'new' => 'Non, je le découvre',
];

const INTENTION_CHOICES = [
    'buy' => 'Je viens avec l’intention d’acheter un cardigan',
    'discover' => 'Je viens découvrir les modèles avant de décider',
    'inform' => 'Je viens me renseigner avant un éventuel achat',
    'accompany' => 'Je viens accompagner un proche',
];

const PRESENCE_FILTERS = [
    'present' => 'Confirmés',
    'unsure' => 'Hésitants',
    'absent' => 'Absents',
];

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_raw_string(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
}

function redirect_to(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function flash(string $message, string $category = 'success'): void
{
    $_SESSION['flashes'][] = ['category' => $category, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $messages;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function validate_csrf(): void
{
    if (!hash_equals(csrf_token(), post_string('_csrf'))) {
        render_page(
            'error',
            'Requête invalide',
            ['message' => 'La page a expiré. Rechargez-la puis réessayez.'],
            400
        );
        exit;
    }
}

function post_string(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function query_string(string $name): string
{
    $value = $_GET[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function integer_value(string $value, string $label): ?int
{
    if ($value === '') {
        return null;
    }
    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if ($parsed === false) {
        throw new InvalidArgumentException('Le champ « ' . $label . ' » doit être un nombre entier.');
    }
    return $parsed;
}

function registration_view(array $row): array
{
    $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    $row['presence_label'] = PRESENCE_CHOICES[$row['attendance']] ?? $row['attendance'];
    $row['companion_label'] = $row['attendance'] !== 'present'
        ? '—'
        : ((int) $row['companions_count'] <= 0
            ? 'Je viens seul(e)'
            : ((int) $row['companions_count'] === 1
                ? 'Je viens accompagné(e) d’une personne'
                : 'Je viens accompagné(e) de plusieurs personnes'));
    $row['beverage_label'] = BEVERAGE_CHOICES[$row['beverages']] ?? $row['beverages'];
    $row['knowledge_label'] = KNOWLEDGE_CHOICES[$row['cardigan_knowledge']] ?? $row['cardigan_knowledge'];
    $row['intention_label'] = INTENTION_CHOICES[$row['intention']] ?? $row['intention'];
    return $row;
}

function render_page(string $view, string $title, array $data = [], int $status = 200): void
{
    http_response_code($status);
    extract($data, EXTR_SKIP);
    $pageTitle = $title;
    ob_start();
    require ROOT_PATH . '/app/views/' . $view . '.php';
    $contentHtml = (string) ob_get_clean();
    $flashes = take_flashes();
    require ROOT_PATH . '/app/views/layout.php';
}

function require_admin(): void
{
    if (empty($_SESSION['admin_authenticated'])) {
        flash('Veuillez vous connecter pour accéder à l’espace administrateur.', 'error');
        redirect_to('/admin/login');
    }
}

function find_registration_by_reference(string $reference, bool $caseInsensitive = false): ?array
{
    $sql = $caseInsensitive
        ? 'SELECT * FROM event_registrations WHERE LOWER(reference) = :reference'
        : 'SELECT * FROM event_registrations WHERE reference = :reference';
    $statement = db()->prepare($sql);
    $statement->execute(['reference' => $caseInsensitive ? mb_strtolower($reference, 'UTF-8') : $reference]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function next_reference(PDO $connection): string
{
    $prefix = 'CARD-' . date('Y') . '-';
    $statement = $connection->prepare(
        'SELECT reference FROM event_registrations WHERE reference LIKE :prefix'
    );
    $statement->execute(['prefix' => $prefix . '%']);
    $highest = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $reference) {
        $suffix = substr((string) $reference, strlen($prefix));
        if (ctype_digit($suffix) && (int) $suffix > $highest) {
            $highest = (int) $suffix;
        }
    }
    return $prefix . str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
}

function registration_stats(): array
{
    $connection = db();
    $rows = $connection->query('SELECT * FROM event_registrations')->fetchAll();
    $stats = [
        'total' => count($rows),
        'present' => 0,
        'unsure' => 0,
        'absent' => 0,
        'expected_attendees' => 0,
        'potential_buyers' => 0,
        'discoverers' => 0,
        'beverage_counts' => array_fill_keys(array_keys(BEVERAGE_CHOICES), 0),
        'city_counts' => [],
        'knowledge_counts' => array_fill_keys(array_keys(KNOWLEDGE_CHOICES), 0),
    ];

    foreach ($rows as $row) {
        if (isset($stats[$row['attendance']])) {
            $stats[$row['attendance']]++;
        }
        if ($row['attendance'] === 'present') {
            $stats['expected_attendees'] += 1 + (int) ($row['companions_count'] ?? 0);
        }
        if ($row['intention'] === 'buy') {
            $stats['potential_buyers']++;
        }
        if ($row['intention'] === 'discover') {
            $stats['discoverers']++;
        }
        if (isset($stats['beverage_counts'][$row['beverages']])) {
            $stats['beverage_counts'][$row['beverages']]++;
        }
        if (isset($stats['knowledge_counts'][$row['cardigan_knowledge']])) {
            $stats['knowledge_counts'][$row['cardigan_knowledge']]++;
        }
        $city = (string) ($row['city'] ?? '');
        if ($city !== '') {
            $stats['city_counts'][$city] = ($stats['city_counts'][$city] ?? 0) + 1;
        }
    }
    ksort($stats['city_counts'], SORT_NATURAL | SORT_FLAG_CASE);
    return $stats;
}

function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(env_value('APP_TIMEZONE', 'Europe/Paris')))
        ->format('d/m/Y à H:i');
}
