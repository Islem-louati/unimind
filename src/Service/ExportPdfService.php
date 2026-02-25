<?php
// src/Service/ExportPdfService.php

namespace App\Service;

use Knp\Snappy\Pdf;
use Twig\Environment;
use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * TCPDF + FPDI étendu pour générer des PDFs avec champs éditables.
 */
class EditableTcpdf extends TcpdfFpdi
{
    public function Header(): void {}
    public function Footer(): void {}
}

class ExportPdfService
{
    public function __construct(
        private Pdf         $snappy,
        private Environment $twig
    ) {}

    // =========================================================
    // PDF ENTIÈREMENT ÉDITABLE — toutes les cellules modifiables
    // =========================================================

    public function generateEditableUsersPdf(array $users): string
    {
        // Couleurs UniMind
        $primary   = [94,  114, 228];
        $lightGray = [248, 249, 250];
        $white     = [255, 255, 255];
        $darkText  = [50,  50,  93];

        $roleColors = [
            'Etudiant'             => [17,  205, 239],
            'Psychologue'          => [251, 99,  64],
            'Responsable Etudiant' => [45,  206, 137],
        ];
        $statutColors = [
            'actif'      => [45,  206, 137],
            'en_attente' => [251, 99,  64],
            'inactif'    => [245, 54,  92],
            'rejeté'     => [150, 150, 150],
        ];

        $pdf = new EditableTcpdf('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('UniMind');
        $pdf->SetAuthor('UniMind Administration');
        $pdf->SetTitle('Liste des utilisateurs - UniMind');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage('L', 'A4');

        $pageW = $pdf->getPageWidth() - 20; // 277mm en A4 landscape

        // ── EN-TÊTE ────────────────────────────────────────────────────
        $pdf->SetFillColor(...$primary);
        $pdf->Rect(10, 10, $pageW, 14, 'F');
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(10, 11);
        $pdf->Cell($pageW, 12, 'UniMind — Liste des Utilisateurs', 0, 0, 'C');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(150, 150, 180);
        $pdf->SetXY(10, 25);
        $pdf->Cell($pageW / 2, 5, 'Généré le ' . date('d/m/Y à H:i'), 0, 0, 'L');
        $pdf->SetXY(10 + $pageW / 2, 25);
        $pdf->Cell($pageW / 2, 5, 'Total : ' . count($users) . ' utilisateur(s)', 0, 0, 'R');
        $pdf->Ln(8);

        // ── COLONNES ──────────────────────────────────────────────────
        $cols = [
            ['key' => 'id',          'label' => 'ID',          'w' => 12,  'align' => 'C'],
            ['key' => 'nom',         'label' => 'Nom',         'w' => 28,  'align' => 'L'],
            ['key' => 'prenom',      'label' => 'Prénom',      'w' => 28,  'align' => 'L'],
            ['key' => 'email',       'label' => 'Email',       'w' => 55,  'align' => 'L'],
            ['key' => 'cin',         'label' => 'CIN',         'w' => 22,  'align' => 'C'],
            ['key' => 'role',        'label' => 'Rôle',        'w' => 30,  'align' => 'C'],
            ['key' => 'statut',      'label' => 'Statut',      'w' => 24,  'align' => 'C'],
            ['key' => 'inscription', 'label' => 'Inscription', 'w' => 22,  'align' => 'C'],
            ['key' => 'actif',       'label' => 'Actif',       'w' => 14,  'align' => 'C'],
            ['key' => 'verifie',     'label' => 'Vérifié',     'w' => 14,  'align' => 'C'],
            ['key' => 'notes',       'label' => '✎ Notes',     'w' => 28,  'align' => 'L'],
        ];

        $rowH    = 8;
        $headerH = 9;

        // ── EN-TÊTE TABLEAU ───────────────────────────────────────────
        $this->drawTableHeader($pdf, $cols, $primary, $headerH);

        // ── LIGNES DE DONNÉES ─────────────────────────────────────────
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(...$darkText);
        $pdf->SetDrawColor(210, 210, 230);
        $pdf->SetLineWidth(0.2);

        foreach ($users as $i => $user) {
            // Nouvelle page si besoin
            if ($pdf->GetY() + $rowH + 4 > $pdf->getPageHeight() - 15) {
                $pdf->AddPage('L', 'A4');
                $this->drawTableHeader($pdf, $cols, $primary, $headerH);
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(...$darkText);
            }

            $fillRow = ($i % 2 === 0);
            $bg      = $fillRow ? $lightGray : $white;
            $pdf->SetFillColor(...$bg);

            $y = $pdf->GetY();
            $x = 10;

            $role   = $user->getRole()->value;
            $statut = $user->getStatut();

            // Style commun pour tous les champs éditables
            $fieldStyle = [
                'lineWidth'   => 0.2,
                'strokeColor' => [200, 200, 220],
                'fillColor'   => $bg,
                'textColor'   => $darkText,
                'fontSize'    => 7,
            ];

            // ── Champ : ID ────────────────────────────────────────────
            $col = $cols[0];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            $pdf->TextField('id_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle, ['v' => (string)$user->getUserId()], $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Nom ───────────────────────────────────────────
            $col = $cols[1];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'L', $fillRow);
            $pdf->TextField('nom_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle, ['v' => $user->getNom()], $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Prénom ────────────────────────────────────────
            $col = $cols[2];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'L', $fillRow);
            $pdf->TextField('prenom_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle, ['v' => $user->getPrenom()], $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Email ─────────────────────────────────────────
            $col = $cols[3];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'L', $fillRow);
            $pdf->TextField('email_' . $i, $col['w'] - 1, $rowH - 1,
                array_merge($fieldStyle, ['fontSize' => 6.5]),
                ['v' => $user->getEmail()], $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : CIN ───────────────────────────────────────────
            $col = $cols[4];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            $pdf->TextField('cin_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle, ['v' => $user->getCin() ?? ''], $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Rôle (avec badge coloré + éditable) ───────────
            $col = $cols[5];
            $rc  = $roleColors[$role] ?? $primary;
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            // Badge couleur derrière
            $pdf->SetFillColor(...$rc);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 6.5);
            $pdf->SetXY($x + 2, $y + 2);
            $pdf->Cell($col['w'] - 4, $rowH - 4, mb_substr($role, 0, 14), 0, 0, 'C', true);
            // Champ éditable par-dessus
            $pdf->TextField('role_' . $i, $col['w'] - 1, $rowH - 1,
                array_merge($fieldStyle, ['fillColor' => [0, 0, 0, 0]]),
                ['v' => $role], $x + 0.5, $y + 0.5);
            $pdf->SetFillColor(...$bg);
            $pdf->SetTextColor(...$darkText);
            $pdf->SetFont('helvetica', '', 7.5);
            $x += $col['w'];

            // ── Champ : Statut (avec badge coloré + éditable) ─────────
            $col = $cols[6];
            $sc  = $statutColors[$statut] ?? [150, 150, 150];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            $pdf->SetFillColor(...$sc);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 6.5);
            $pdf->SetXY($x + 2, $y + 2);
            $pdf->Cell($col['w'] - 4, $rowH - 4, mb_substr($statut, 0, 12), 0, 0, 'C', true);
            $pdf->TextField('statut_' . $i, $col['w'] - 1, $rowH - 1,
                array_merge($fieldStyle, ['fillColor' => [0, 0, 0, 0]]),
                ['v' => $statut], $x + 0.5, $y + 0.5);
            $pdf->SetFillColor(...$bg);
            $pdf->SetTextColor(...$darkText);
            $pdf->SetFont('helvetica', '', 7.5);
            $x += $col['w'];

            // ── Champ : Date inscription ──────────────────────────────
            $col = $cols[7];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            $pdf->TextField('date_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle,
                ['v' => $user->getCreatedAt()->format('d/m/Y')],
                $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Actif ─────────────────────────────────────────
            $col = $cols[8];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            $pdf->TextField('actif_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle,
                ['v' => $user->isIsActive() ? 'Oui' : 'Non'],
                $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Vérifié ───────────────────────────────────────
            $col = $cols[9];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'C', $fillRow);
            $pdf->TextField('verifie_' . $i, $col['w'] - 1, $rowH - 1,
                $fieldStyle,
                ['v' => $user->isIsVerified() ? 'Oui' : 'Non'],
                $x + 0.5, $y + 0.5);
            $x += $col['w'];

            // ── Champ : Notes (fond jaune) ────────────────────────────
            $col = $cols[10];
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $rowH, '', 1, 0, 'L', $fillRow);
            $pdf->TextField('notes_' . $i, $col['w'] - 1, $rowH - 1,
                array_merge($fieldStyle, ['fillColor' => [255, 255, 210]]),
                ['v' => '', 'dv' => 'Écrire ici…'],
                $x + 0.5, $y + 0.5);

            $pdf->SetXY(10, $y + $rowH);
        }

        // ── PIED DE PAGE ──────────────────────────────────────────────
        $pdf->Ln(5);
        $footY = $pdf->GetY();
        $pdf->SetFillColor(...$primary);
        $pdf->Rect(10, $footY, $pageW, 8, 'F');
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(10, $footY);
        $pdf->Cell($pageW, 8, 'UniMind — Cliquez sur n\'importe quelle cellule pour modifier son contenu', 0, 0, 'C');

        return $pdf->Output('', 'S');
    }

    /**
     * Dessine l'en-tête du tableau.
     */
    private function drawTableHeader(EditableTcpdf $pdf, array $cols, array $color, int $h): void
    {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(...$color);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(180, 180, 210);
        $pdf->SetLineWidth(0.3);

        $y = $pdf->GetY();
        $x = 10;
        foreach ($cols as $col) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($col['w'], $h, $col['label'], 1, 0, 'C', true);
            $x += $col['w'];
        }
        $pdf->Ln($h);
    }

    // =========================================================
    // PDF SIMPLE (non éditable) — conservé pour compatibilité
    // =========================================================

    public function generatePdf(string $template, array $data = [], array $options = []): string
    {
        $html = $this->twig->render($template, $data);

        return $this->snappy->getOutputFromHtml($html, array_merge([
            'orientation'              => 'landscape',
            'page-size'                => 'A4',
            'margin-top'               => 20,
            'margin-right'             => 10,
            'margin-bottom'            => 20,
            'margin-left'              => 10,
            'encoding'                 => 'utf-8',
            'enable-local-file-access' => true,
        ], $options));
    }
}
