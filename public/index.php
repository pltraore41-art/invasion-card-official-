<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/routes.php';

try {
    handle_request();
} catch (Throwable $exception) {
    error_log((string) $exception);
    render_page(
        'error',
        'Erreur',
        ['message' => 'Une erreur est survenue. Vérifiez la configuration ou les journaux du serveur.'],
        500
    );
}
