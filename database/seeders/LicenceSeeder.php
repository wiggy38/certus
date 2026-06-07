<?php

namespace Database\Seeders;

use App\Models\Licence;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

/**
 * Seeder de données de test pour les licences Experto.
 *
 * Crée une organisation de test (ExpertoSoft Demo) si elle n'existe pas,
 * puis insère 3 licences représentatives des cas d'usage courants :
 *
 *   LIC-0000000001 → STANDARD  ACTIVE    (licence principale, valide 1 an)
 *   LIC-0000000002 → STARTER   EXPIREE   (ancienne licence non renouvelée)
 *   LIC-0000000003 → EVAL      ACTIVE    (évaluation 30 jours, expire bientôt)
 */
class LicenceSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------------------------------------------
        // Organisation de référence pour les licences de test
        // ----------------------------------------------------------------
        $org = Organisation::firstOrCreate(
            ['email_contact' => 'demo@expertosoft.com'],
            [
                'nom'      => 'ExpertoSoft Demo',
                'pays'     => 'BF',
                'cree_par' => 'seeder',
                'cree_le'  => now(),
            ],
        );

        $this->command->info("Organisation de test : {$org->org_id} — {$org->nom}");

        // ----------------------------------------------------------------
        // Licence 1 : STANDARD ACTIVE — licence principale valide
        // ----------------------------------------------------------------
        $l1 = Licence::create([
            'org_id'          => $org->org_id,
            'type_licence'    => Licence::TYPE_STANDARD,
            'nb_postes'       => 5,
            'nb_sites'        => 3,
            'nb_projets'      => 10,
            'date_emission'   => now()->subMonths(3)->toDateString(),
            'date_expiration' => now()->addMonths(9)->toDateString(),
            'version_format'  => 1,
            'cle_hash_sha256' => hash('sha256', 'demo-standard-' . $org->org_id . '-2026'),
            'crc_g5'          => 'A1B2C',
            'statut'          => Licence::STATUT_ACTIVE,
            'nb_activations'  => 2,
            'tentatives_suspectes' => 0,
            'notes'           => 'Licence STANDARD de démonstration — 5 postes, 3 sites, 10 projets.',
            'cree_par'        => 'seeder',
        ]);

        $this->command->info(
            "  [{$l1->licence_id}] {$l1->type_libelle} — {$l1->statut} — expire le {$l1->date_expiration->toDateString()}"
        );

        // ----------------------------------------------------------------
        // Licence 2 : STARTER EXPIREE — ancienne licence non renouvelée
        // ----------------------------------------------------------------
        $l2 = Licence::create([
            'org_id'          => $org->org_id,
            'type_licence'    => Licence::TYPE_STARTER,
            'nb_postes'       => 1,
            'nb_sites'        => 1,
            'nb_projets'      => 3,
            'date_emission'   => now()->subYears(2)->toDateString(),
            'date_expiration' => now()->subMonths(3)->toDateString(),
            'version_format'  => 1,
            'cle_hash_sha256' => hash('sha256', 'demo-starter-' . $org->org_id . '-2024'),
            'crc_g5'          => 'Z9Y8X',
            'statut'          => Licence::STATUT_EXPIREE,
            'nb_activations'  => 1,
            'tentatives_suspectes' => 0,
            'notes'           => 'Ancienne licence STARTER — expirée il y a 3 mois, non renouvelée.',
            'cree_par'        => 'seeder',
        ]);

        $this->command->info(
            "  [{$l2->licence_id}] {$l2->type_libelle} — {$l2->statut} — expirée le {$l2->date_expiration->toDateString()}"
        );

        // ----------------------------------------------------------------
        // Licence 3 : EVAL ACTIVE — période d'évaluation, expire dans 30 jours
        // ----------------------------------------------------------------
        $l3 = Licence::create([
            'org_id'          => $org->org_id,
            'type_licence'    => Licence::TYPE_EVAL,
            'nb_postes'       => 2,
            'nb_sites'        => 1,
            'nb_projets'      => 1,
            'date_emission'   => now()->toDateString(),
            'date_expiration' => now()->addDays(30)->toDateString(),
            'version_format'  => 1,
            'cle_hash_sha256' => hash('sha256', 'demo-eval-' . $org->org_id . '-' . now()->timestamp),
            'crc_g5'          => 'EV4L0',
            'statut'          => Licence::STATUT_ACTIVE,
            'nb_activations'  => 0,
            'tentatives_suspectes' => 0,
            'notes'           => 'Licence EVAL 30 jours — conversion STANDARD prévue si satisfaisant.',
            'cree_par'        => 'seeder',
        ]);

        $this->command->info(
            "  [{$l3->licence_id}] {$l3->type_libelle} — {$l3->statut} — expire le {$l3->date_expiration->toDateString()} ({$l3->joursRestants()} jours)"
        );

        $this->command->info('LicenceSeeder terminé — 3 licences créées.');
    }
}
