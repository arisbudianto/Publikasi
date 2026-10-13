<?php

namespace App\Services;

class AuthorshipEvaluator
{
    public const VERSION = '2026-10-v1';

    public function criteria(): array
    {
        return [
            'c1' => 'Kontribusi substantif pada konsepsi/desain atau akuisisi, analisis, maupun interpretasi data.',
            'c2' => 'Menyusun naskah atau meninjau secara kritis konten intelektual penting.',
            'c3' => 'Menyetujui versi final yang akan diterbitkan.',
            'c4' => 'Bersedia bertanggung jawab atas semua aspek karya dan memastikan pertanyaan tentang akurasi atau integritas ditangani.',
        ];
    }

    public function roles(): array
    {
        return [
            'conceptualization' => 'Conceptualization · Konseptualisasi',
            'data_curation' => 'Data curation · Kurasi data',
            'formal_analysis' => 'Formal analysis · Analisis formal',
            'funding_acquisition' => 'Funding acquisition · Perolehan pendanaan',
            'investigation' => 'Investigation · Investigasi / pengumpulan data',
            'methodology' => 'Methodology · Metodologi',
            'project_administration' => 'Project administration · Administrasi proyek',
            'resources' => 'Resources · Sumber daya',
            'software' => 'Software · Perangkat lunak',
            'supervision' => 'Supervision · Supervisi',
            'validation' => 'Validation · Validasi',
            'visualization' => 'Visualization · Visualisasi',
            'writing_original_draft' => 'Writing – original draft · Penulisan draf awal',
            'writing_review_editing' => 'Writing – review & editing · Peninjauan dan penyuntingan',
        ];
    }

    public function evaluate(array $people): array
    {
        $criteria = $this->criteria();
        $roles = $this->roles();
        $acknowledgementOnlyRoles = ['funding_acquisition', 'project_administration', 'supervision'];
        $evaluated = [];

        foreach (array_values($people) as $index => $person) {
            $selectedCriteria = array_values(array_intersect(array_keys($criteria), $person['criteria'] ?? []));
            $selectedRoles = array_values(array_intersect(array_keys($roles), $person['roles'] ?? []));
            $missing = array_diff_key($criteria, array_flip($selectedCriteria));
            $onlyAcknowledgementRoles = $selectedRoles !== []
                && array_diff($selectedRoles, $acknowledgementOnlyRoles) === [];
            $meetsAll = $missing === [];

            if ($meetsAll && $onlyAcknowledgementRoles) {
                $status = 'review';
                $label = 'Tinjau kembali syarat pertama';
                $note = 'Peran pendanaan, supervisi umum, atau administrasi saja tidak memenuhi kontribusi substantif pada syarat pertama ICMJE. Pastikan jawaban dan kontribusinya ditinjau bersama.';
            } elseif ($meetsAll) {
                $status = 'author_candidate';
                $label = 'Memenuhi empat syarat ICMJE';
                $note = 'Kandidat untuk dicantumkan sebagai penulis berdasarkan jawaban yang diberikan.';
            } else {
                $status = 'acknowledgement';
                $label = 'Belum memenuhi semua syarat';
                $note = 'Menurut ICMJE, kontributor yang belum memenuhi keempat syarat tidak dicantumkan sebagai penulis; pertimbangkan ucapan terima kasih dengan izin tertulis.';
                if ($onlyAcknowledgementRoles) {
                    $note .= ' Pendanaan, supervisi umum, atau administrasi saja tidak memenuhi syarat pertama.';
                }
            }

            $evaluated[] = [
                'name' => trim($person['name']),
                'criteria' => $selectedCriteria,
                'missing_criteria' => array_keys($missing),
                'roles' => $selectedRoles,
                'role_labels' => array_map(fn ($role) => $roles[$role], $selectedRoles),
                'status' => $status,
                'status_label' => $label,
                'note' => $note,
                'role_count' => count($selectedRoles),
                'input_order' => $index + 1,
            ];
        }

        $authorOrder = array_values(array_filter($evaluated, fn ($person) => $person['status'] === 'author_candidate'));
        usort($authorOrder, fn ($left, $right) => $right['role_count'] <=> $left['role_count'] ?: $left['input_order'] <=> $right['input_order']);
        foreach ($authorOrder as $index => &$person) {
            $person['suggested_order'] = $index + 1;
        }
        unset($person);

        $byOrder = [];
        foreach ($authorOrder as $person) {
            $byOrder[$person['input_order'] - 1] = $person;
        }
        foreach ($evaluated as $index => &$person) {
            if (isset($byOrder[$index])) {
                $person['suggested_order'] = $byOrder[$index]['suggested_order'];
            }
        }
        unset($person);

        $statement = [];
        foreach ($evaluated as $person) {
            if ($person['role_labels'] !== []) {
                $statement[] = $person['name'].': '.implode('; ', $person['role_labels']).'.';
            }
        }

        return [
            'version' => self::VERSION,
            'people' => $evaluated,
            'author_order' => array_column($authorOrder, 'name'),
            'contribution_statement' => implode("\n", $statement),
        ];
    }
}
