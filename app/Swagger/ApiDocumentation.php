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
class ApiDocumentation
{
}
