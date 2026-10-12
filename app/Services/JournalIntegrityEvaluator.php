<?php

namespace App\Services;

class JournalIntegrityEvaluator
{
    public const VERSION = '2026-10-v1';

    public function criteria(): array
    {
        return [
            'issn' => ['question' => 'Apakah ISSN jurnal cocok dengan Portal ISSN?', 'detail' => 'Periksa format, nama terbitan, dan penerbit pada Portal ISSN. Kecocokan identitas membantu menghindari jurnal dengan nama serupa.', 'risk' => 'no', 'weight' => 3, 'source' => 'https://portal.issn.org/'],
            'editorial_board' => ['question' => 'Apakah dewan editor dan afiliasinya dapat diperiksa?', 'detail' => 'Cari nama lengkap dan afiliasi editor; periksa bahwa mereka dapat ditemukan melalui institusi atau profil profesional.', 'risk' => 'no', 'weight' => 3, 'source' => 'https://thinkchecksubmit.org/journals/'],
            'peer_review' => ['question' => 'Apakah proses peer review dijelaskan dengan jelas?', 'detail' => 'Cari model review, peran reviewer, tahap keputusan, dan pengecualian jenis artikel.', 'risk' => 'no', 'weight' => 3, 'source' => 'https://doi.org/10.24318/cope.2019.1.12'],
            'fees' => ['question' => 'Apakah seluruh biaya dan waktu penagihannya diumumkan sebelum submit?', 'detail' => 'Periksa nominal, mata uang, jenis biaya, waktu penagihan, serta aturan waiver bila tersedia.', 'risk' => 'no', 'weight' => 3, 'source' => 'https://thinkchecksubmit.org/journals/'],
            'indexing' => ['question' => 'Apakah klaim indeksasi dapat diverifikasi di situs indeks resminya?', 'detail' => 'Cari judul atau ISSN langsung di direktori resmi indeks, bukan hanya di situs penerbit.', 'risk' => 'no', 'weight' => 3, 'source' => 'https://thinkchecksubmit.org/journals/'],
            'rapid_acceptance' => ['question' => 'Apakah jurnal menjanjikan penerimaan atau review sangat singkat tanpa dasar yang jelas?', 'detail' => 'Janji penerimaan dijamin atau tenggat review yang tidak wajar perlu diperiksa terhadap kebijakan dan artikel terbit.', 'risk' => 'yes', 'weight' => 4, 'source' => 'https://doi.org/10.24318/cope.2019.1.12'],
            'mass_solicitation' => ['question' => 'Apakah Anda menerima undangan massal yang menekan untuk segera mengirim artikel?', 'detail' => 'Cocokkan pesan dengan scope, kebijakan, dan kontak pada situs resmi jurnal; tekanan waktu bukan bukti tunggal masalah.', 'risk' => 'yes', 'weight' => 2, 'source' => 'https://thinkchecksubmit.org/journals/'],
            'publisher_identity' => ['question' => 'Apakah identitas penerbit dan cara menghubunginya jelas?', 'detail' => 'Periksa nama penerbit, alamat, kontak editorial, kepemilikan atau afiliasi, dan konsistensinya di sumber lain.', 'risk' => 'no', 'weight' => 3, 'source' => 'https://thinkchecksubmit.org/journals/'],
        ];
    }

    public function evaluate(array $answers): array
    {
        $criteria = $this->criteria();
        $score = 0;
        $unknown = 0;
        $flags = [];

        foreach ($criteria as $key => $criterion) {
            $answer = $answers[$key];
            if ($answer === 'unknown') {
                $unknown++;
            } elseif ($answer === $criterion['risk']) {
                $score += $criterion['weight'];
                $flags[] = $key;
            }
        }

        $outcome = $score >= 8
            ? 'Banyak tanda yang perlu ditinjau'
            : (($score > 0 || $unknown > 0) ? 'Perlu diperiksa lebih lanjut' : 'Tidak ditemukan masalah pada kriteria yang dijawab');

        return ['score' => $score, 'unknown_count' => $unknown, 'flags' => $flags, 'outcome' => $outcome, 'version' => self::VERSION];
    }
}
