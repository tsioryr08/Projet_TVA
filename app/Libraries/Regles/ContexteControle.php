<?php

namespace App\Libraries\Regles;

use CodeIgniter\Database\BaseConnection;

/**
 * Données et paramètres légaux d'une exécution (une société, une période).
 * Les lectures en base sont mises en cache pour que 6 règles ne refassent pas 6 fois les mêmes requêtes.
 */
class ContexteControle
{
    /** @var array<int,array<string,mixed>>|null */
    private ?array $ecritures = null;

    /** @var array<int,list<array<string,mixed>>>|null */
    private ?array $lignesParEcriture = null;

    /** @var array<int,array<string,mixed>>|null */
    private ?array $tiers = null;

    /** @var list<array<string,mixed>>|null */
    private ?array $bulletins = null;

    /**
     * @param array<string,mixed> $societe
     * @param array<string,mixed> $periode
     */
    public function __construct(
        public readonly BaseConnection $db,
        public readonly array $societe,
        public readonly array $periode,
    ) {
    }

    /**
     * Valeur d'un paramètre légal (table taux_fiscaux) en vigueur à une date donnée.
     */
    public function taux(string $code, string $date): ?float
    {
        $row = $this->db->table('taux_fiscaux')
            ->where('code', $code)
            ->where('date_effet <=', $date)
            ->groupStart()->where('date_fin IS NULL', null, false)->orWhere('date_fin >=', $date)->groupEnd()
            ->orderBy('date_effet', 'DESC')
            ->get(1)->getRowArray();

        return $row ? (float) $row['valeur'] : null;
    }

    /**
     * Tranches du barème IRSA en vigueur à une date donnée, triées par numéro de tranche.
     *
     * @return list<array<string,mixed>>
     */
    public function bareme(string $date): array
    {
        return $this->db->table('baremes_irsa')
            ->where('date_effet <=', $date)
            ->groupStart()->where('date_fin IS NULL', null, false)->orWhere('date_fin >=', $date)->groupEnd()
            ->orderBy('tranche_numero', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Écritures de la période, indexées par id.
     *
     * @return array<int,array<string,mixed>>
     */
    public function ecritures(): array
    {
        if ($this->ecritures === null) {
            $this->ecritures = [];
            $rows = $this->db->table('ecritures')
                ->where('societe_id', $this->societe['id'])
                ->where('periode_id', $this->periode['id'])
                ->orderBy('id')->get()->getResultArray();
            foreach ($rows as $r) {
                $this->ecritures[(int) $r['id']] = $r;
            }
        }

        return $this->ecritures;
    }

    /**
     * Lignes d'écriture de la période (avec numéro, libellé et rôle fiscal du compte), groupées par écriture.
     *
     * @return array<int,list<array<string,mixed>>>
     */
    public function lignesParEcriture(): array
    {
        if ($this->lignesParEcriture === null) {
            $this->lignesParEcriture = [];
            $rows = $this->db->table('lignes_ecriture l')
                ->select('l.*, p.numero AS compte_numero, p.libelle AS compte_libelle, p.role_fiscal')
                ->join('ecritures e', 'e.id = l.ecriture_id')
                ->join('plan_comptable p', 'p.id = l.compte_id')
                ->where('e.societe_id', $this->societe['id'])
                ->where('e.periode_id', $this->periode['id'])
                ->orderBy('l.id')->get()->getResultArray();
            foreach ($rows as $r) {
                $r['debit']  = (float) $r['debit'];
                $r['credit'] = (float) $r['credit'];
                $this->lignesParEcriture[(int) $r['ecriture_id']][] = $r;
            }
        }

        return $this->lignesParEcriture;
    }

    /**
     * Tiers de la société, indexés par id.
     *
     * @return array<int,array<string,mixed>>
     */
    public function tiers(): array
    {
        if ($this->tiers === null) {
            $this->tiers = [];
            foreach ($this->db->table('tiers')->where('societe_id', $this->societe['id'])->get()->getResultArray() as $t) {
                $this->tiers[(int) $t['id']] = $t;
            }
        }

        return $this->tiers;
    }

    /**
     * Bulletins de paie de la période, avec les informations de l'employé.
     *
     * @return list<array<string,mixed>>
     */
    public function bulletins(): array
    {
        if ($this->bulletins === null) {
            $this->bulletins = $this->db->table('bulletins_paie b')
                ->select('b.*, e.matricule, e.nom, e.prenom, e.nb_charges')
                ->join('employes e', 'e.id = b.employe_id')
                ->where('e.societe_id', $this->societe['id'])
                ->where('b.periode_id', $this->periode['id'])
                ->orderBy('b.id')->get()->getResultArray();
        }

        return $this->bulletins;
    }
}
