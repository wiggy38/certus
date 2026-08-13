<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Certus API',
    version: '1.0.0',
    description: 'Documentation OpenAPI de l\'API Certus pour la gestion des licences Experto.',
    contact: new OA\Contact(email: 'support@expertosoft.com')
)]
#[OA\Server(url: '/', description: 'Serveur principal')]

#[OA\Get(
    path: '/api/v1/status',
    tags: ['Sante'],
    summary: 'Etat de l\'API',
    responses: [
        new OA\Response(
            response: 200,
            description: 'OK',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'statut', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'app', type: 'string', example: 'Certus'),
                            new OA\Property(property: 'version', type: 'string', example: 'v1'),
                            new OA\Property(property: 'time', type: 'string', format: 'date-time')
                        ]
                    ),
                    new OA\Property(property: 'meta', type: 'array', items: new OA\Items(type: 'object'))
                ]
            )
        )
    ]
)]
#[OA\Post(
    path: '/api/v1/licences',
    tags: ['Licence'],
    summary: 'Émet une nouvelle licence',
    security: [['X-API-Key' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['org_id', 'type_licence', 'nb_postes', 'nb_sites', 'nb_projets', 'date_expiration'],
            properties: [
                new OA\Property(property: 'org_id', type: 'string', example: 'ORG-0001'),
                new OA\Property(property: 'type_licence', type: 'string', example: '3'),
                new OA\Property(property: 'nb_postes', type: 'integer', example: 10),
                new OA\Property(property: 'nb_sites', type: 'integer', example: 2),
                new OA\Property(property: 'nb_projets', type: 'integer', example: 5),
                new OA\Property(property: 'date_expiration', type: 'string', format: 'date', example: '2026-12-31'),
                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Licence pour le site principal')
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Licence créée avec succès',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'statut', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'licence_id', type: 'string', example: 'LIC-2025-000123'),
                            new OA\Property(property: 'cle', type: 'string', example: 'ABCD-1234-EF56-7890-KLMN'),
                            new OA\Property(property: 'anti_rejeu', type: 'string', example: 'AR-ABC123')
                        ]
                    ),
                    new OA\Property(property: 'meta', type: 'object')
                ]
            )
        ),
        new OA\Response(response: 404, description: 'Organisation introuvable'),
        new OA\Response(response: 422, description: 'Validation échouée')
    ]
)]
#[OA\Get(
    path: '/api/v1/licences/{licence_id}',
    tags: ['Licence'],
    summary: 'Récupère le détail d\'une licence',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Licence trouvée'),
        new OA\Response(response: 404, description: 'Licence introuvable')
    ]
)]
#[OA\Post(
    path: '/api/v1/licences/{licence_id}/revoquer',
    tags: ['Licence'],
    summary: 'Révoque une licence',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'motif', type: 'string', nullable: true, example: 'Fraud détectée')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Révocation effectuée'),
        new OA\Response(response: 404, description: 'Licence introuvable'),
        new OA\Response(response: 409, description: 'Licence déjà révoquée')
    ]
)]
#[OA\Post(
    path: '/api/v1/licences/activer',
    tags: ['Activation'],
    summary: 'Active une licence sur une machine cliente',
    security: [['X-API-Key' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['cle', 'fingerprint'],
            properties: [
                new OA\Property(property: 'cle', type: 'string', example: 'ABCD-1234-EF56-7890-KLMN'),
                new OA\Property(property: 'fingerprint', type: 'string', example: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'),
                new OA\Property(property: 'version_app', type: 'string', nullable: true, example: '2.4.1')
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Activation réussie',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'statut', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'licence_id', type: 'string', example: 'LIC-2025-000123'),
                            new OA\Property(property: 'org_nom', type: 'string', example: 'Experto SAS'),
                            new OA\Property(property: 'type_licence', type: 'string', example: 'PRO'),
                            new OA\Property(property: 'modules', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'token_local', type: 'string', example: 'eyJhbGciOiJIUzI1NiJ9...')
                        ]
                    ),
                    new OA\Property(property: 'meta', type: 'object')
                ]
            )
        ),
        new OA\Response(response: 400, description: 'Clé invalide ou corrompue'),
        new OA\Response(response: 403, description: 'Licence bloquée, révoquée ou signal suspect'),
        new OA\Response(response: 404, description: 'Licence inconnue'),
        new OA\Response(response: 422, description: 'Validation échouée')
    ]
)]
#[OA\Get(
    path: '/api/v1/licences/{licence_id}/activations',
    tags: ['Activation'],
    summary: 'Liste les activations d\'une licence',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste des activations'),
        new OA\Response(response: 404, description: 'Licence introuvable')
    ]
)]
#[OA\Patch(
    path: '/api/v1/licences/{licence_id}/activations/{activation_id}',
    tags: ['Activation'],
    summary: 'Désactive une activation',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'activation_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['statut'],
            properties: [
                new OA\Property(property: 'statut', type: 'string', example: 'DESACTIVEE'),
                new OA\Property(property: 'motif', type: 'string', nullable: true, example: 'Désactivation manuelle')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Activation désactivée'),
        new OA\Response(response: 404, description: 'Activation ou licence introuvable'),
        new OA\Response(response: 409, description: 'Activation non active')
    ]
)]
#[OA\Post(
    path: '/api/v1/organisations',
    tags: ['Organisation'],
    summary: 'Crée une organisation',
    security: [['X-API-Key' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['nom', 'email_contact', 'pays'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Experto SAS'),
                new OA\Property(property: 'email_contact', type: 'string', format: 'email', example: 'contact@experto.fr'),
                new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '+33123456789'),
                new OA\Property(property: 'adresse', type: 'string', nullable: true, example: '12 rue du Test'),
                new OA\Property(property: 'pays', type: 'string', example: 'FR')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Organisation créée'),
        new OA\Response(response: 422, description: 'Validation échouée')
    ]
)]
#[OA\Get(
    path: '/api/v1/organisations/{org_id}',
    tags: ['Organisation'],
    summary: 'Récupère une organisation',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'org_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Organisation trouvée'),
        new OA\Response(response: 404, description: 'Organisation introuvable')
    ]
)]
#[OA\Post(
    path: '/api/v1/blacklist/fingerprints',
    tags: ['Blacklist'],
    summary: 'Ajoute un fingerprint en liste noire',
    security: [['X-API-Key' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['fingerprint', 'licence_id', 'signal'],
            properties: [
                new OA\Property(property: 'fingerprint', type: 'string', example: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'),
                new OA\Property(property: 'licence_id', type: 'string', example: 'LIC-2025-000123'),
                new OA\Property(property: 'signal', type: 'integer', example: 4),
                new OA\Property(property: 'motif', type: 'string', nullable: true, example: 'Suspicion de piratage')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Fingerprint blacklisté'),
        new OA\Response(response: 422, description: 'Validation échouée')
    ]
)]
#[OA\Get(
    path: '/api/v1/blacklist/fingerprints',
    tags: ['Blacklist'],
    summary: 'Liste les fingerprints blacklistés',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'signal', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'depuis', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limite', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste des fingerprints'),
        new OA\Response(response: 422, description: 'Paramètres invalides')
    ]
)]
#[OA\Get(
    path: '/api/v1/licences/{licence_id}/audit',
    tags: ['Audit'],
    summary: 'Retourne l\'audit d\'une licence',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Audit de la licence'),
        new OA\Response(response: 404, description: 'Licence introuvable')
    ]
)]
#[OA\Get(
    path: '/api/v1/licences/{licence_id}/historique-activations',
    tags: ['Activation'],
    summary: 'Historique des activations d\'une licence',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limite', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Historique récupéré'),
        new OA\Response(response: 404, description: 'Licence introuvable')
    ]
)]
#[OA\Get(
    path: '/api/v1/monitoring/alertes',
    tags: ['Monitoring'],
    summary: 'Liste les alertes de sécurité',
    security: [['X-API-Key' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Alertes retournées'),
        new OA\Response(response: 401, description: 'Non autorisé')
    ]
)]
#[OA\Patch(
    path: '/api/v1/licences/{licence_id}/reset-alertes',
    tags: ['Monitoring'],
    summary: 'Remet à zéro les alertes d\'une licence',
    security: [['X-API-Key' => []]],
    parameters: [
        new OA\Parameter(name: 'licence_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['motif'],
            properties: [
                new OA\Property(property: 'motif', type: 'string', example: 'Validation manuelle')
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Alertes remises à zéro'),
        new OA\Response(response: 404, description: 'Licence introuvable'),
        new OA\Response(response: 422, description: 'Validation échouée')
    ]
)]
class ApiDocumentation
{
}
