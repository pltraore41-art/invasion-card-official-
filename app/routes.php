<?php
declare(strict_types=1);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;

function handle_request(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = rawurldecode(is_string($rawPath) ? $rawPath : '/');
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    if ($method === 'POST') {
        validate_csrf();
    }

    if ($path === '/' && in_array($method, ['GET', 'HEAD'], true)) {
        render_page('index', BRAND_NAME, ['stats' => registration_stats()]);
        return;
    }

    if ($path === '/inscriptions' && in_array($method, ['GET', 'HEAD'], true)) {
        list_registrations();
        return;
    }

    if ($path === '/inscriptions/nouvelle' && in_array($method, ['GET', 'POST'], true)) {
        new_registration();
        return;
    }

    if (preg_match('~^/inscriptions/([^/]+)/ticket\.(?:png|svg)$~', $path, $matches)) {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            method_not_allowed();
            return;
        }
        registration_ticket($matches[1]);
        return;
    }

    if (preg_match('~^/inscriptions/([^/]+)$~', $path, $matches)
        && in_array($method, ['GET', 'HEAD'], true)) {
        registration_detail($matches[1]);
        return;
    }

    if ($path === '/participation' && in_array($method, ['GET', 'POST'], true)) {
        participation();
        return;
    }

    if ($path === '/admin' && in_array($method, ['GET', 'HEAD'], true)) {
        admin_dashboard();
        return;
    }

    if ($path === '/admin/export.csv' && in_array($method, ['GET', 'HEAD'], true)) {
        export_csv();
        return;
    }

    if ($path === '/admin/login' && in_array($method, ['GET', 'POST'], true)) {
        admin_login();
        return;
    }

    if ($path === '/admin/logout' && in_array($method, ['GET', 'HEAD'], true)) {
        unset($_SESSION['admin_authenticated']);
        flash('Déconnexion réussie.', 'success');
        redirect_to('/admin/login');
    }

    if (preg_match('~^/admin/([^/]+)/delete$~', $path, $matches)) {
        if ($method !== 'POST') {
            method_not_allowed();
            return;
        }
        delete_registration($matches[1]);
        return;
    }

    if (preg_match('~^/admin/([^/]+)$~', $path, $matches)
        && in_array($method, ['GET', 'POST'], true)) {
        admin_registration($matches[1]);
        return;
    }

    if (preg_match('~^/demandes(?:/.*)?$~', $path)) {
        if ($path === '/demandes/nouvelle') {
            new_registration();
            return;
        }
        if (preg_match('~^/demandes/([^/]+)$~', $path, $matches)) {
            redirect_to('/inscriptions/' . rawurlencode($matches[1]));
        }
        redirect_to('/inscriptions');
    }

    if (in_array($method, ['GET', 'HEAD'], true)) {
        render_page('error', 'Page introuvable', ['message' => 'La page demandée n’existe pas.'], 404);
        return;
    }
    method_not_allowed();
}

function method_not_allowed(): void
{
    header('Allow: GET, POST');
    render_page('error', 'Méthode non autorisée', ['message' => 'Cette méthode ne peut pas être utilisée pour cette page.'], 405);
}

function list_registrations(): void
{
    $attendance = query_string('presence');
    $city = query_string('ville');
    $search = query_string('q');
    $intention = query_string('intention');
    $beverage = query_string('boisson');
    $conditions = [];
    $parameters = [];

    if (isset(PRESENCE_FILTERS[$attendance])) {
        $conditions[] = 'attendance = :attendance';
        $parameters['attendance'] = $attendance;
    }
    if ($city !== '') {
        $conditions[] = 'LOWER(city) LIKE :city';
        $parameters['city'] = '%' . mb_strtolower($city, 'UTF-8') . '%';
    }
    if ($search !== '') {
        $conditions[] = '(LOWER(first_name) LIKE :search_first OR LOWER(last_name) LIKE :search_last)';
        $parameters['search_first'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $parameters['search_last'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
    }
    if (isset(INTENTION_CHOICES[$intention])) {
        $conditions[] = 'intention = :intention';
        $parameters['intention'] = $intention;
    } else {
        $intention = '';
    }
    if (isset(BEVERAGE_CHOICES[$beverage])) {
        $conditions[] = 'beverages = :beverage';
        $parameters['beverage'] = $beverage;
    } else {
        $beverage = '';
    }

    $sql = 'SELECT * FROM event_registrations';
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY created_at DESC';
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $registrations = array_map('registration_view', $statement->fetchAll());

    render_page('list', 'Inscriptions', [
        'registrations' => $registrations,
        'cityFilter' => $city,
        'search' => $search,
        'currentPresence' => $attendance,
        'intentionFilter' => $intention,
        'beverageFilter' => $beverage,
    ]);
}

function new_registration(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $connection = null;
        try {
            $attendance = post_string('attendance');
            if (!isset(PRESENCE_CHOICES[$attendance])) {
                throw new InvalidArgumentException('Merci de sélectionner votre réponse concernant votre présence.');
            }

            $companions = 0;
            if ($attendance === 'present') {
                $companions = integer_value(post_string('companions_count'), 'Nombre de personnes qui vous accompagnent');
                if ($companions === null) {
                    throw new InvalidArgumentException('Merci de préciser combien de personnes vous accompagneront.');
                }
                if ($companions < 0) {
                    throw new InvalidArgumentException('Le nombre de personnes qui vous accompagnent ne peut pas être négatif.');
                }
            }

            $beverages = post_string('beverages');
            if (!isset(BEVERAGE_CHOICES[$beverages])) {
                throw new InvalidArgumentException('Merci de choisir une préférence de boissons.');
            }
            $knowledge = post_string('cardigan_knowledge');
            if (!isset(KNOWLEDGE_CHOICES[$knowledge])) {
                throw new InvalidArgumentException('Merci d’indiquer votre connaissance du cardigan.');
            }
            $intention = post_string('intention');
            if (!isset(INTENTION_CHOICES[$intention])) {
                throw new InvalidArgumentException('Merci de sélectionner votre objectif principal.');
            }

            $firstName = post_string('first_name');
            $lastName = post_string('last_name');
            $city = post_string('city');
            $age = integer_value(post_string('age'), 'Âge');
            if ($age !== null && $age < 0) {
                throw new InvalidArgumentException('L’âge ne peut pas être négatif.');
            }
            if ($firstName === '' || $lastName === '' || $city === '') {
                throw new InvalidArgumentException('Veuillez remplir tous les champs obligatoires.');
            }

            $connection = db();
            $connection->beginTransaction();
            $reference = next_reference($connection);
            $now = gmdate('Y-m-d H:i:s');
            $statement = $connection->prepare(
                'INSERT INTO event_registrations (
                    reference, first_name, last_name, age, city, attendance, companions_count,
                    beverages, cardigan_knowledge, intention, desired_size, preferred_color,
                    budget, comment, created_at, updated_at
                ) VALUES (
                    :reference, :first_name, :last_name, :age, :city, :attendance, :companions_count,
                    :beverages, :cardigan_knowledge, :intention, :desired_size, :preferred_color,
                    :budget, :comment, :created_at, :updated_at
                )'
            );
            $statement->execute([
                'reference' => $reference,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'age' => $age,
                'city' => $city,
                'attendance' => $attendance,
                'companions_count' => $companions,
                'beverages' => $beverages,
                'cardigan_knowledge' => $knowledge,
                'intention' => $intention,
                'desired_size' => post_string('desired_size') ?: null,
                'preferred_color' => post_string('preferred_color') ?: null,
                'budget' => post_string('budget') ?: null,
                'comment' => post_string('comment') ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $connection->commit();
            flash('Merci ! Votre inscription a bien été enregistrée. Référence : ' . $reference);
            redirect_to('/inscriptions/' . rawurlencode($reference));
        } catch (InvalidArgumentException $exception) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
            flash($exception->getMessage(), 'error');
        } catch (Throwable $exception) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    render_page('form', 'Inscription');
}

function registration_detail(string $reference): void
{
    $row = find_registration_by_reference($reference);
    if ($row === null) {
        render_page('error', 'Inscription introuvable', ['message' => 'Cette inscription n’existe pas.'], 404);
        return;
    }
    render_page('detail', 'Invitation ' . $reference, ['registration' => registration_view($row)]);
}

function registration_ticket(string $reference): void
{
    $row = find_registration_by_reference($reference, true);
    if ($row === null) {
        render_page('error', 'Inscription introuvable', ['message' => 'Cette inscription n’existe pas.'], 404);
        return;
    }
    if (!class_exists(QRCode::class)) {
        throw new RuntimeException('Installez les dépendances PHP avec « composer install » pour générer les billets QR.');
    }

    $registration = registration_view($row);
    $payload = implode("\n", [
        BRAND_NAME,
        'Référence : ' . $registration['reference'],
        'Nom : ' . $registration['full_name'],
        'Événement : ' . EVENT_DATE,
    ]);
    $options = new QROptions();
    $options->outputType = QROutputInterface::MARKUP_SVG;
    $options->outputBase64 = false;
    $options->svgAddXmlHeader = false;
    $options->eccLevel = QRCode::ECC_H;
    $options->quietzoneSize = 4;
    $options->scale = 8;
    $qrSvg = (new QRCode($options))->render($payload);
    $logoPath = ROOT_PATH . '/public/static/invasion-logo.png';
    $logo = file_get_contents($logoPath);
    if ($logo === false) {
        throw new RuntimeException('Impossible de lire le logo utilisé pour le billet.');
    }

    $qrData = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
    $logoData = 'data:image/png;base64,' . base64_encode($logo);
    $download = query_string('download') === '1';
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="ticket-' . rawurlencode($reference) . '.svg"');
    header('X-Content-Type-Options: nosniff');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    ?>
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="1120" viewBox="0 0 900 1120" role="img" aria-label="Billet d’accès de <?= e($registration['full_name']) ?>">
      <rect width="900" height="1120" fill="#050505"/>
      <rect x="24" y="24" width="852" height="1072" rx="28" fill="none" stroke="#f5f5f5" stroke-width="3"/>
      <image x="205" y="62" width="490" height="170" href="<?= e($logoData) ?>" preserveAspectRatio="xMidYMid meet"/>
      <g fill="#fff" text-anchor="middle" font-family="Arial, Helvetica, sans-serif">
        <text x="450" y="305" font-size="42">BILLET D'ACCÈS</text>
        <text x="450" y="355" font-size="23" fill="#c6c6c6">INVITATION OFFICIELLE</text>
        <path d="M90 385h720" stroke="#555" stroke-width="2"/>
        <text x="450" y="450" font-size="30"><?= e($registration['full_name']) ?></text>
        <text x="450" y="500" font-size="23" fill="#d0d0d0"><?= e($registration['reference']) ?></text>
        <text x="450" y="545" font-size="23" fill="#c6c6c6"><?= e(EVENT_DATE) ?></text>
        <text x="450" y="585" font-size="23"><?= e($registration['presence_label']) ?></text>
      </g>
      <image x="240" y="615" width="420" height="420" href="<?= e($qrData) ?>"/>
      <rect x="367" y="778" width="166" height="94" rx="12" fill="#fff"/>
      <image x="387" y="798" width="126" height="54" href="<?= e($logoData) ?>" preserveAspectRatio="xMidYMid meet" style="filter: brightness(0)"/>
      <text x="450" y="1065" fill="#c6c6c6" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="23">PRESENTEZ CE QR CODE A L'ENTREE</text>
    </svg>
    <?php
    exit;
}

function participation(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reference = mb_strtolower(post_string('reference'), 'UTF-8');
        $lastName = mb_strtolower(post_string('last_name'), 'UTF-8');
        $row = find_registration_by_reference($reference);

        if ($row === null || $lastName === ''
            || mb_strtolower(trim($row['last_name']), 'UTF-8') !== $lastName) {
            flash('Référence ou nom invalide. Vérifiez vos informations.', 'error');
            render_page('participation', 'Participation');
            return;
        }

        $attendance = post_string('attendance');
        if (!isset(PRESENCE_CHOICES[$attendance])) {
            flash('Merci de sélectionner votre réponse concernant votre présence.', 'error');
            render_page('participation', 'Participation');
            return;
        }

        $companions = 0;
        if ($attendance === 'present') {
            try {
                $companions = integer_value(post_string('companions_count'), 'Nombre d’accompagnants');
            } catch (InvalidArgumentException $exception) {
                flash($exception->getMessage(), 'error');
                render_page('participation', 'Participation');
                return;
            }
            if ($companions === null || $companions < 0) {
                flash('Le nombre d’accompagnants doit être supérieur ou égal à zéro.', 'error');
                render_page('participation', 'Participation');
                return;
            }
        }

        $statement = db()->prepare(
            'UPDATE event_registrations
             SET attendance = :attendance, companions_count = :companions, updated_at = :updated_at
             WHERE reference = :reference'
        );
        $statement->execute([
            'attendance' => $attendance,
            'companions' => $companions,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'reference' => $row['reference'],
        ]);
        flash('Votre réponse a bien été enregistrée. Merci !');
        redirect_to('/participation');
    }

    render_page('participation', 'Participation');
}

function admin_dashboard(): void
{
    require_admin();
    $rows = db()->query('SELECT * FROM event_registrations ORDER BY created_at DESC')->fetchAll();
    render_page('admin', 'Administration', [
        'stats' => registration_stats(),
        'registrations' => array_map('registration_view', $rows),
    ]);
}

function export_csv(): void
{
    require_admin();
    $rows = db()->query('SELECT * FROM event_registrations ORDER BY created_at DESC')->fetchAll();
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('Impossible de préparer le fichier CSV.');
    }
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, [
        'reference', 'nom', 'prenom', 'age', 'ville', 'presence', 'accompagnants',
        'boissons', 'connaissance', 'objectif', 'taille', 'couleur', 'budget',
        'commentaire', 'created_at',
    ], ',', '"', '\\');
    foreach ($rows as $row) {
        $registration = registration_view($row);
        fputcsv($stream, [
            $registration['reference'],
            $registration['last_name'],
            $registration['first_name'],
            $registration['age'] ?? '',
            $registration['city'] ?? '',
            $registration['presence_label'],
            $registration['companions_count'] ?? 0,
            $registration['beverage_label'],
            $registration['knowledge_label'],
            $registration['intention_label'],
            $registration['desired_size'] ?? '',
            $registration['preferred_color'] ?? '',
            $registration['budget'] ?? '',
            $registration['comment'] ?? '',
            $registration['created_at'] === null
                ? ''
                : (new DateTimeImmutable($registration['created_at'], new DateTimeZone('UTC')))
                    ->format('Y-m-d\TH:i:s'),
        ], ',', '"', '\\');
    }
    rewind($stream);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inscriptions-cardigan.csv"');
    fpassthru($stream);
    fclose($stream);
}

function admin_registration(string $reference): void
{
    require_admin();
    $row = find_registration_by_reference($reference);
    if ($row === null) {
        render_page('error', 'Inscription introuvable', ['message' => 'Cette inscription n’existe pas.'], 404);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $companions = integer_value(
                post_string('companions_count'),
                'Nombre de personnes qui accompagnent le participant'
            );
            if ($companions === null || $companions < 0) {
                throw new InvalidArgumentException(
                    'Le nombre d’accompagnants doit être un nombre positif ou égal à zéro.'
                );
            }
            $age = integer_value(post_string('age'), 'Âge');
            if ($age !== null && $age < 0) {
                throw new InvalidArgumentException('L’âge ne peut pas être négatif.');
            }
        } catch (InvalidArgumentException $exception) {
            flash($exception->getMessage(), 'error');
            render_page('admin_detail', 'Fiche participant', ['registration' => registration_view($row)]);
            return;
        }

        $updated = [
            'first_name' => post_string('first_name') ?: $row['first_name'],
            'last_name' => post_string('last_name') ?: $row['last_name'],
            'age' => $age ?? $row['age'],
            'city' => post_string('city') ?: $row['city'],
            'attendance' => isset(PRESENCE_CHOICES[post_string('attendance')])
                ? post_string('attendance')
                : $row['attendance'],
            'companions_count' => $companions,
            'beverages' => isset(BEVERAGE_CHOICES[post_string('beverages')])
                ? post_string('beverages')
                : $row['beverages'],
            'cardigan_knowledge' => isset(KNOWLEDGE_CHOICES[post_string('cardigan_knowledge')])
                ? post_string('cardigan_knowledge')
                : $row['cardigan_knowledge'],
            'intention' => isset(INTENTION_CHOICES[post_string('intention')])
                ? post_string('intention')
                : $row['intention'],
            'desired_size' => post_string('desired_size') ?: $row['desired_size'],
            'preferred_color' => post_string('preferred_color') ?: $row['preferred_color'],
            'budget' => post_string('budget') ?: $row['budget'],
            'comment' => post_string('comment') ?: $row['comment'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'reference' => $reference,
        ];
        $statement = db()->prepare(
            'UPDATE event_registrations SET
                first_name = :first_name, last_name = :last_name, age = :age, city = :city,
                attendance = :attendance, companions_count = :companions_count,
                beverages = :beverages, cardigan_knowledge = :cardigan_knowledge,
                intention = :intention, desired_size = :desired_size,
                preferred_color = :preferred_color, budget = :budget, comment = :comment,
                updated_at = :updated_at
             WHERE reference = :reference'
        );
        $statement->execute($updated);
        flash('Inscription mise à jour.');
        redirect_to('/admin/' . rawurlencode($reference));
    }

    render_page('admin_detail', 'Fiche participant', ['registration' => registration_view($row)]);
}

function delete_registration(string $reference): void
{
    require_admin();
    $statement = db()->prepare('DELETE FROM event_registrations WHERE reference = :reference');
    $statement->execute(['reference' => $reference]);
    if ($statement->rowCount() === 0) {
        render_page('error', 'Inscription introuvable', ['message' => 'Cette inscription n’existe pas.'], 404);
        return;
    }
    flash('L’inscription a bien été supprimée.');
    redirect_to('/admin');
}

function admin_login(): void
{
    $adminPassword = env_value('ADMIN_PASSWORD', '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = post_raw_string('password');
        if ($adminPassword !== '' && hash_equals($adminPassword, $password)) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['admin_authenticated'] = true;
            csrf_token();
            flash('Connexion réussie.');
            redirect_to('/admin');
        }
        if ($adminPassword !== '') {
            flash('Mot de passe incorrect.', 'error');
        }
    }
    render_page('admin_login', 'Connexion admin', ['adminConfigured' => $adminPassword !== '']);
}
