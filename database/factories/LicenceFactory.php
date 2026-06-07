<?php

namespace Database\Factories;

use App\Models\Licence;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory pour les tests et le seeding des licences Experto.
 *
 * licence_id et anti_rejeu sont auto-générés par Licence::boot().
 * cle_hash_sha256 est un SHA-256 unique simulé (≠ vraie clé Experto).
 * crc_g5 est un checksum 5 chars simulé.
 *
 * @extends Factory<Licence>
 */
class LicenceFactory extends Factory
{
    protected $model = Licence::class;

    public function definition(): array
    {
        $emission    = fake()->dateTimeBetween('-2 years', 'now');
        $expiration  = fake()->dateTimeBetween($emission, '+3 years');
        $typeCode    = fake()->randomElement(array_keys(Licence::TYPES));

        return [
            'org_id'               => Organisation::factory(),
            'type_licence'         => $typeCode,
            'nb_postes'            => $this->nbPostesParType($typeCode),
            'nb_sites'             => $this->nbSitesParType($typeCode),
            'nb_projets'           => $this->nbProjetsParType($typeCode),
            'date_emission'        => $emission->format('Y-m-d'),
            'date_expiration'      => $expiration->format('Y-m-d'),
            'version_format'       => 1,
            'cle_hash_sha256'      => hash('sha256', uniqid('lic_', true) . fake()->uuid()),
            'crc_g5'               => strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
            'statut'               => fake()->randomElement(Licence::STATUTS),
            'nb_activations'       => fake()->numberBetween(0, 5),
            'tentatives_suspectes' => fake()->numberBetween(0, 3),
            'notes'                => fake()->optional(0.3)->sentence(),
            'cree_par'             => 'admin',
            'cree_le'              => $emission,
            'modifie_le'           => null,
            'modifie_par'          => null,
        ];
    }

    // ----------------------------------------------------------------
    // États métier
    // ----------------------------------------------------------------

    /** Licence en cours de validité. */
    public function active(): static
    {
        return $this->state([
            'statut'          => Licence::STATUT_ACTIVE,
            'date_emission'   => now()->subMonths(3)->toDateString(),
            'date_expiration' => now()->addYear()->toDateString(),
        ]);
    }

    /** Licence expirée (statut + date dans le passé). */
    public function expiree(): static
    {
        return $this->state([
            'statut'          => Licence::STATUT_EXPIREE,
            'date_emission'   => now()->subYears(2)->toDateString(),
            'date_expiration' => now()->subMonths(3)->toDateString(),
        ]);
    }

    /** Licence suspendue. */
    public function suspendue(): static
    {
        return $this->state(['statut' => Licence::STATUT_SUSPENDUE]);
    }

    /** Licence révoquée. */
    public function revoquee(): static
    {
        return $this->state(['statut' => Licence::STATUT_REVOQUEE]);
    }

    /** Licence d'évaluation (type EVAL, 30 jours). */
    public function eval(): static
    {
        return $this->state([
            'type_licence'    => Licence::TYPE_EVAL,
            'nb_postes'       => 2,
            'nb_sites'        => 1,
            'nb_projets'      => 1,
            'date_emission'   => now()->toDateString(),
            'date_expiration' => now()->addDays(30)->toDateString(),
            'statut'          => Licence::STATUT_ACTIVE,
        ]);
    }

    /** Licence avec activité suspecte. */
    public function suspecte(int $tentatives = 3): static
    {
        return $this->state(['tentatives_suspectes' => $tentatives]);
    }

    /** Associe la licence à une organisation existante par org_id. */
    public function pourOrganisation(string $orgId): static
    {
        return $this->state(['org_id' => $orgId]);
    }

    // ----------------------------------------------------------------
    // Quotas par défaut selon le type
    // ----------------------------------------------------------------

    private function nbPostesParType(string $type): int
    {
        return match ($type) {
            Licence::TYPE_STARTER    => 1,
            Licence::TYPE_STANDARD   => 5,
            Licence::TYPE_PRO        => 20,
            Licence::TYPE_ENTERPRISE => 0,   // 0 = illimité
            Licence::TYPE_EVAL       => 2,
            default                  => 1,
        };
    }

    private function nbSitesParType(string $type): int
    {
        return match ($type) {
            Licence::TYPE_STARTER    => 1,
            Licence::TYPE_STANDARD   => 3,
            Licence::TYPE_PRO        => 10,
            Licence::TYPE_ENTERPRISE => 0,
            Licence::TYPE_EVAL       => 1,
            default                  => 1,
        };
    }

    private function nbProjetsParType(string $type): int
    {
        return match ($type) {
            Licence::TYPE_STARTER    => 3,
            Licence::TYPE_STANDARD   => 10,
            Licence::TYPE_PRO        => 50,
            Licence::TYPE_ENTERPRISE => 0,
            Licence::TYPE_EVAL       => 1,
            default                  => 1,
        };
    }
}
