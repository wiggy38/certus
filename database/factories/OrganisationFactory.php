<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory pour les tests et le seeding des organisations.
 *
 * org_id et org_index_b36 sont auto-générés par Organisation::boot()
 * lors de l'appel à create() — ne pas les fournir ici.
 *
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    /** Liste des pays d'Afrique de l'Ouest (codes ISO 3166-1 alpha-2). */
    private const PAYS_AOF = ['BF', 'CI', 'SN', 'ML', 'TG', 'BJ', 'NE', 'GN', 'GW', 'MR'];

    public function definition(): array
    {
        return [
            'nom'           => fake()->company(),
            'email_contact' => fake()->unique()->companyEmail(),
            'telephone'     => fake()->numerify('+226 ## ## ## ##'),
            'adresse'       => fake()->address(),
            'pays'          => fake()->randomElement(self::PAYS_AOF),
            'cree_le'       => fake()->dateTimeBetween('-2 years', 'now'),
            'cree_par'      => 'admin',
        ];
    }

    // ----------------------------------------------------------------
    // États
    // ----------------------------------------------------------------

    /**
     * Organisation basée au Burkina Faso (pays par défaut).
     */
    public function burkinabe(): static
    {
        return $this->state(['pays' => 'BF']);
    }

    /**
     * Organisation créée par un utilisateur spécifique.
     */
    public function creePar(string $auteur): static
    {
        return $this->state(['cree_par' => $auteur]);
    }

    /**
     * Organisation créée récemment (dans les 30 derniers jours).
     */
    public function recente(): static
    {
        return $this->state(['cree_le' => fake()->dateTimeBetween('-30 days', 'now')]);
    }
}
